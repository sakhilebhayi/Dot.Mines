<?php

namespace Tests\Unit;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class FileUploadServiceTest extends TestCase
{
    public function test_validate_zip_rejects_path_traversal()
    {
        $tmp = sys_get_temp_dir();
        $zipPath = tempnam($tmp, 'testzip');
        // Create zip with a path traversal entry
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('../evil.php', "<?php echo 'pwned'; ?>");
        $zip->close();

        $uploaded = new UploadedFile($zipPath, 'test.zip', null, null, true);

        $svc = new FileUploadService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('unsafe file paths');

        $svc->validateFile($uploaded);
    }

    /**
     * The extension and browser-claimed MIME are attacker-controlled; only
     * sniffed content counts. A PHP payload named report.pdf passes the
     * extension allowlist but must fail content verification.
     */
    public function test_rejects_a_file_whose_content_does_not_match_its_extension(): void
    {
        $tmp = tmpfile();
        $path = stream_get_meta_data($tmp)['uri'];
        fwrite($tmp, "<?php system(\$_GET['cmd']); ?>");

        $uploaded = new UploadedFile($path, 'report.pdf', null, null, true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('content does not match');

        (new FileUploadService)->validateFile($uploaded);
    }

    public function test_accepts_a_file_whose_content_matches_its_extension(): void
    {
        $tmp = tmpfile();
        $path = stream_get_meta_data($tmp)['uri'];
        fwrite($tmp, "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $uploaded = new UploadedFile($path, 'plan.pdf', null, null, true);

        (new FileUploadService)->validateFile($uploaded);

        $this->assertTrue(true, 'A genuine PDF passes content verification.');
    }
}
