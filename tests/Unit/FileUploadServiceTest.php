<?php

namespace Tests\Unit;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('zip')]
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
}
