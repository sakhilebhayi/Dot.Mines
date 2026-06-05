<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class FileUploadService
{
    /** @var array<int, string> */
    protected array $allowedExtensions = [
        'pdf', 'dwg', 'dxf', 'kml', 'kmz', 'shp', 'zip', 'gz', 'tar',
        'png', 'jpg', 'jpeg', 'gif', 'tif', 'tiff',
    ];

    public function sanitizeFilename(string $originalName): string
    {
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        // Remove non-alphanumeric, keep dots, dashes and underscores
        $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name) ?? '';
        $safe = Str::limit($safe, 120, '');
        $hash = Str::random(8);

        return $safe.'_'.$hash.'.'.strtolower($ext);
    }

    /**
     * Maximum allowed uncompressed size for archive contents in bytes.
     * Default to 200 MB but can be adjusted in tests.
     */
    protected int $maxUncompressedSize = 209715200;

    /**
     * Maximum allowed size per-entry inside archives (bytes). Default 50 MB.
     */
    protected int $maxPerFileSize = 52428800;

    /**
     * Setter for max uncompressed size to ease testing.
     */
    public function setMaxUncompressedSize(int $bytes): void
    {
        $this->maxUncompressedSize = $bytes;
    }

    public function setMaxPerFileSize(int $bytes): void
    {
        $this->maxPerFileSize = $bytes;
    }

    public function validateFile(UploadedFile $file): void
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, $this->allowedExtensions)) {
            throw new \Exception('File type not allowed.');
        }

        // Size check already handled by validation rules, but double-check (max 50MB)
        if ($file->getSize() > 51200 * 1024) {
            throw new \Exception('File too large.');
        }

        // For archive types, inspect zip contents for dangerous file types
        $suspicious = ['php', 'phtml', 'exe', 'sh', 'bat', 'pl', 'py', 'jar', 'com'];
        if ($ext === 'zip' && class_exists(\ZipArchive::class)) {
            $real = $file->getRealPath();
            if ($real && file_exists($real)) {
                $zip = new \ZipArchive;
                if ($zip->open($real) === true) {
                    $totalUncompressed = 0;
                    $maxUncompressed = $this->maxUncompressedSize; // configurable cap for uncompressed contents
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $name = $zip->getNameIndex($i);

                        if ($name === false) {
                            continue;
                        }

                        // Reject path traversal or absolute paths
                        if (strpos($name, '..') !== false || str_starts_with($name, '/') || str_starts_with($name, '\\')) {
                            $zip->close();
                            throw new \Exception('Archive contains unsafe file paths.');
                        }

                        // Detect symlink entries (external_attributes high bits indicate file mode on UNIX)
                        $stat = $zip->statIndex($i);
                        if (isset($stat['external_attributes'])) {
                            $mode = ($stat['external_attributes'] >> 16) & 0xFFFF;
                            // 0xA000 is symlink in unix file mode
                            if (($mode & 0xF000) === 0xA000) {
                                // Skip symlink entries rather than failing entirely; they are not extracted.
                                continue;
                            }
                        }

                        $entryExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        // Reject sensitive filenames commonly used to exfiltrate secrets
                        $lowerName = strtolower($name);
                        $sensitiveNames = ['.env', 'credentials', 'id_rsa', 'id_dsa', 'private.key', '.aws/credentials', '.git-credentials'];
                        foreach ($sensitiveNames as $sn) {
                            if (str_contains($lowerName, $sn)) {
                                $zip->close();
                                throw new \Exception('Archive contains potentially sensitive filenames.');
                            }
                        }
                        if (in_array($entryExt, $suspicious, true)) {
                            $zip->close();
                            throw new \Exception('Archive contains disallowed file types.');
                        }

                        // Sum uncompressed sizes and enforce per-file limits
                        $entrySize = isset($stat['size']) ? (int) $stat['size'] : 0;
                        if ($entrySize > $this->maxPerFileSize) {
                            $zip->close();
                            throw new \Exception('Archive contains an entry larger than the per-file allowed limit.');
                        }
                        $totalUncompressed += $entrySize;
                        if ($totalUncompressed > $maxUncompressed) {
                            $zip->close();
                            throw new \Exception('Archive uncompressed size exceeds allowed limit.');
                        }

                        // Basic MIME sniff: read first bytes of file stream
                        $stream = $zip->getStream($name);
                        if ($stream) {
                            $probe = fread($stream, 8192);
                            fclose($stream);
                            if ($probe !== false && strlen($probe) > 0) {
                                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                                $mime = $finfo->buffer($probe);
                                // If the declared extension is an image but MIME is text or php-like, reject
                                $imageExts = ['png', 'jpg', 'jpeg', 'gif', 'tif', 'tiff'];
                                if (in_array($entryExt, $imageExts, true) && str_starts_with($mime, 'text/')) {
                                    $zip->close();
                                    throw new \Exception('Archive contains files with mismatched MIME types.');
                                }

                                // Disallow any entry that claims to be an executable or PHP script
                                $suspiciousMimePrefixes = ['application/x-php', 'application/x-sh', 'application/x-msdownload', 'application/x-python', 'text/x-php'];
                                foreach ($suspiciousMimePrefixes as $prefix) {
                                    if (str_starts_with($mime, $prefix)) {
                                        $zip->close();
                                        throw new \Exception('Archive contains potentially executable content.');
                                    }
                                }
                            }
                        }
                    }
                    $zip->close();
                }
            }
        }
    }

    protected function attemptVirusScan(UploadedFile $file): void
    {
        // Try clamdscan or clamscan if available; if not present, skip
        $path = $file->getRealPath();
        if (! $path || ! file_exists($path)) {
            return;
        }

        // Only run virus scanning when explicitly enabled in config/env to avoid
        // accidental command execution in restricted environments.
        if (! (bool) config('scanning.virus.enabled', env('VIRUS_SCAN_ENABLED', false))) {
            return;
        }

        // Ensure the file is an uploaded temp file and its realpath is inside the system temp dir.
        $real = realpath($path);
        $tmpDir = realpath(sys_get_temp_dir());
        if ($real === false || $tmpDir === false) {
            // Not a normal uploaded temp file; skip scanning for safety.
            return;
        }
        if (strpos($real, $tmpDir) !== 0) {
            return;
        }

        // Prefer talking to a clamd daemon via socket (INSTREAM) to avoid
        // spawning external processes. Configuration may provide a unix socket
        // path (`scanning.clamav.socket`) or a host/port (`scanning.clamav.host`, `scanning.clamav.port`).
        $socketPath = config('scanning.clamav.socket', env('CLAMD_SOCKET', ''));
        $host = config('scanning.clamav.host', env('CLAMD_HOST', '127.0.0.1'));
        $port = config('scanning.clamav.port', env('CLAMD_PORT', 3310));

        $scanned = false;

        // Try UNIX domain socket first
        if (! empty($socketPath) && file_exists($socketPath)) {
            try {
                $fp = @stream_socket_client('unix://'.$socketPath, $errno, $errstr, 5);
                if ($fp) {
                    $this->sendClamdInstream($fp, $real);
                    fclose($fp);
                    $scanned = true;
                }
            } catch (\Throwable $e) {
                Log::warning('clamd unix socket scan failed', ['error' => $e->getMessage()]);
            }
        }

        // Try TCP socket (host:port)
        if (! $scanned && ! empty($host) && ! empty($port)) {
            try {
                $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 5);
                if ($fp) {
                    $this->sendClamdInstream($fp, $real);
                    fclose($fp);
                    $scanned = true;
                }
            } catch (\Throwable $e) {
                Log::warning('clamd tcp scan failed', ['error' => $e->getMessage()]);
            }
        }

        if ($scanned) {
            return;
        }

        // Fall back to local clamdscan/clamscan commands if daemon is not available
        if ($this->commandExists('clamdscan')) {
            $proc = new Process(['clamdscan', '--fdpass', $real]);
        } elseif ($this->commandExists('clamscan')) {
            $proc = new Process(['clamscan', '--no-summary', $real]);
        } else {
            return;
        }

        $proc->setTimeout(60);
        $proc->run();
        $out = trim($proc->getOutput() ?: $proc->getErrorOutput());
        Log::debug('virus-scan output', ['cmd' => $proc->getCommandLine(), 'output' => $out]);

        if (! $proc->isSuccessful()) {
            throw new \Exception('Virus scan failed: file may be infected.');
        }
    }

    /**
     * Send file data over an open clamd socket using the INSTREAM command.
     *
     * @param  resource  $fp
     *
     * @throws \Exception
     */
    protected function sendClamdInstream($fp, string $filePath): void
    {
        // Send INSTREAM command
        fwrite($fp, "INSTREAM\n");

        $handle = fopen($filePath, 'rb');
        if (! $handle) {
            throw new \Exception('Unable to open file for scanning');
        }

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) {
                    break;
                }
                $len = pack('N', strlen($chunk));
                fwrite($fp, $len.$chunk);
            }
            // Send zero-length chunk to indicate EOF
            fwrite($fp, pack('N', 0));

            // Read response
            stream_set_timeout($fp, 10);
            $response = stream_get_contents($fp);
            Log::debug('clamd response', ['response' => $response]);

            if ($response === false) {
                throw new \Exception('No response from clamd');
            }

            // clamd responds with something like: stream: OK or stream: <virus> FOUND
            if (stripos($response, 'FOUND') !== false || stripos($response, 'ERR') !== false) {
                throw new \Exception('Virus scan failed: '.trim($response));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<mixed>
     */
    public function storeMinePlan(UploadedFile $file, int $teamId, int $mineAreaId, ?string $disk = null): array
    {
        $this->validateFile($file);

        // Optional virus scan (best-effort)
        try {
            $this->attemptVirusScan($file);
        } catch (\Exception $e) {
            // Bubble up to caller to handle
            throw $e;
        }

        $safeName = $this->sanitizeFilename($file->getClientOriginalName());

        // Prefer S3 if configured, otherwise use local 'local' disk private path
        $defaultDisk = config('filesystems.disks.s3.bucket') ? 's3' : 'local';
        $disk = $disk ?: $defaultDisk;

        // Whitelist configured disks only
        $configured = array_keys(config('filesystems.disks', []));
        if (! in_array($disk, $configured, true)) {
            throw new \Exception('Requested storage disk is not configured or allowed.');
        }

        // Ensure directory path is canonical and does not contain traversal
        $directory = trim("mine-plans/{$teamId}/{$mineAreaId}", '/');
        if (strpos($directory, '..') !== false) {
            throw new \Exception('Invalid storage path.');
        }

        // Put file using putFileAs to preserve name (Storage handles safe writes)
        // Always write using Laravel Storage API and mark as private (non-executable by default).
        $options = ['visibility' => 'private'];
        $path = Storage::disk($disk)->putFileAs($directory, $file, $safeName, $options);

        return [
            'disk' => $disk,
            'path' => $path,
            'file_name' => $safeName,
            'size' => $file->getSize(),
        ];
    }

    public function temporaryUrl(string $path, string $disk = 's3', int $minutes = 60): string
    {
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        if (! $storage->exists($path)) {
            throw new \Exception('File not found');
        }

        if ($disk === 's3') {
            return $storage->temporaryUrl($path, now()->addMinutes($minutes));
        }

        // For local/private disk, create a signed route elsewhere; fallback to local URL
        return $storage->url($path);
    }

    /**
     * Check whether an executable exists in PATH without invoking a shell.
     */
    protected function commandExists(string $cmd): bool
    {
        $paths = explode(PATH_SEPARATOR, getenv('PATH') ?: '');
        foreach ($paths as $p) {
            $candidate = $p.DIRECTORY_SEPARATOR.$cmd;
            if (is_executable($candidate)) {
                return true;
            }
            // On Windows check PATHEXT
            if (DIRECTORY_SEPARATOR !== '/') {
                $exts = array_filter(array_map('strtolower', preg_split('/;/', getenv('PATHEXT') ?: '.EXE') ?: []));
                foreach ($exts as $ext) {
                    if (is_executable($candidate.$ext)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
