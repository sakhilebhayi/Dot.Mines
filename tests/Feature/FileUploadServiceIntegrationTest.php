<?php

namespace Tests\Feature;

use App\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileUploadServiceIntegrationTest extends TestCase
{
    public function test_store_mine_plan_uses_storage_and_sets_private_visibility()
    {
        Storage::fake('local');

        $tmp = tmpfile();
        $meta = stream_get_meta_data($tmp);
        $path = $meta['uri'];
        fwrite($tmp, 'hello world');

        $uploaded = new UploadedFile($path, 'plan.pdf', null, null, true);

        $svc = new FileUploadService;
        $result = $svc->storeMinePlan($uploaded, 123, 456, 'local');

        $this->assertArrayHasKey('path', $result);
        $this->assertArrayHasKey('disk', $result);
        $this->assertEquals('local', $result['disk']);

        Storage::disk('local')->assertExists($result['path']);

        $visibility = Storage::disk('local')->getVisibility($result['path']);
        $this->assertEquals('private', $visibility);
    }
}
