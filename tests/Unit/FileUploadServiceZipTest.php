<?php

namespace Tests\Unit;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('zip')]
class FileUploadServiceZipTest extends TestCase
{
    public function test_rejects_zip_with_traversal_entry()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ziptest');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE);
        $zip->addFromString('../evil.php', '<?php echo "pwn";');
        $zip->close();

        $uploaded = new UploadedFile($tmp, 'evil.zip', null, null, true);

        $svc = new FileUploadService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Archive contains unsafe file paths.');
        $svc->validateFile($uploaded);
    }

    public function test_rejects_zip_with_oversized_entry()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ziptest');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE);
        // Create a 60MB string to exceed default per-file 50MB limit
        $big = str_repeat('A', 60 * 1024 * 1024);
        $zip->addFromString('big.bin', $big);
        $zip->close();

        $uploaded = new UploadedFile($tmp, 'big.zip', null, null, true);

        $svc = new FileUploadService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('entry larger than the per-file');
        $svc->validateFile($uploaded);
    }

    public function test_rejects_zip_with_mismatched_mime()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ziptest');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::CREATE);
        // Add a file with image extension but PHP content
        $zip->addFromString('image.jpg', "<?php echo 'x'; ?>");
        $zip->close();

        $uploaded = new UploadedFile($tmp, 'mismatch.zip', null, null, true);

        $svc = new FileUploadService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('mismatched MIME');
        $svc->validateFile($uploaded);
    }
}
