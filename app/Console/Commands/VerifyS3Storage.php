<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VerifyS3Storage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:verify-s3 {--disk=s3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify configured S3 disk by uploading a small test file, generating a temporary URL, and cleaning up.';

    public function handle(): int
    {
        $rawDisk = $this->option('disk');
        $disk = is_string($rawDisk) && $rawDisk !== '' ? $rawDisk : 's3';

        $this->info("Verifying storage disk: {$disk}");

        if (! config("filesystems.disks.{$disk}")) {
            $this->error("Disk '{$disk}' is not configured in filesystems.php");

            return self::FAILURE;
        }

        $testPath = 'verify-s3/'.uniqid('verify_').'.txt';
        $contents = 'This is a storage verification file. '.now()->toIsoString();

        try {
            Storage::disk($disk)->put($testPath, $contents);
            $this->info("Uploaded test file to: {$testPath}");

            // Try to generate a temporary URL (S3) or public URL
            $url = null;
            if (method_exists(Storage::disk($disk), 'temporaryUrl')) {
                try {
                    $url = Storage::disk($disk)->temporaryUrl($testPath, now()->addMinutes(10));
                } catch (\Exception $e) {
                    $this->warn('Could not generate temporaryUrl: '.$e->getMessage());
                }
            }

            if (($url === null || $url === '' || $url === '0')) {
                try {
                    $url = Storage::disk($disk)->url($testPath);
                } catch (\Exception $e) {
                    $this->warn('Could not generate public URL: '.$e->getMessage());
                }
            }

            if (($url !== null && $url !== '' && $url !== '0')) {
                $this->info('URL to access test file (may be temporary or require credentials):');
                $this->line($url);
            } else {
                $this->warn('No URL could be generated for the uploaded file; file exists on disk but may be private.');
            }

            // Cleanup
            Storage::disk($disk)->delete($testPath);
            $this->info('Deleted test file from storage.');

            $this->info('S3 storage verification completed successfully.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Storage verification failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
