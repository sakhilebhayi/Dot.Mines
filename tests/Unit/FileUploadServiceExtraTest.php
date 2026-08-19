<?php

namespace Tests\Unit;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class FileUploadServiceExtraTest extends TestCase
{
    public function test_validate_zip_rejects_disallowed_entry_type()
    {
        $tmp = sys_get_temp_dir();
        $zipPath = tempnam($tmp, 'testzip');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        $zip->addFromString('evil.php', "<?php echo 'pwned'; ?>");
        $zip->close();

        $uploaded = new UploadedFile($zipPath, 'test.zip', null, null, true);

        $svc = new FileUploadService;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('disallowed file types');

        $svc->validateFile($uploaded);
    }

    public function test_validate_zip_respects_uncompressed_limit()
    {
        $tmp = sys_get_temp_dir();
        $zipPath = tempnam($tmp, 'testzip');
        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::OVERWRITE);
        // create a file slightly larger than our small test limit
        $large = str_repeat('A', 2048);
        $zip->addFromString('large.txt', $large);
        $zip->close();

        $uploaded = new UploadedFile($zipPath, 'test.zip', null, null, true);

        $svc = new FileUploadService;
        $svc->setMaxUncompressedSize(1024); // 1 KB to force fail

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('uncompressed size exceeds');

        $svc->validateFile($uploaded);
    }
}
