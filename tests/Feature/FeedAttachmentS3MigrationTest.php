<?php

namespace Tests\Feature;

use App\Jobs\MigrateFeedAttachmentsToS3Job;
use App\Models\FeedAttachment;
use App\Models\FeedPost;
use App\Models\Team;
use App\Models\User;
use App\Services\FeedAttachmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for FeedAttachmentService S3 storage routing and MigrateFeedAttachmentsToS3Job.
 *
 * Covers:
 * - Service uses DB storage when S3 is not configured (default)
 * - Service uses S3 storage when AWS credentials are configured
 * - Service falls back to DB if S3 upload fails
 * - isS3Configured() returns correct result based on env
 * - Migration job skips records already on S3
 * - Migration job handles missing S3 credentials gracefully
 */
class FeedAttachmentS3MigrationTest extends TestCase
{
    use RefreshDatabase;

    private function minimalPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA'
            .'WjR9awAAAABJRU5ErkJggg=='
        );
    }

    private function makeFakeFile(string $contents, string $filename, string $mime): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ftest_');
        file_put_contents($tmp, $contents);

        return new UploadedFile($tmp, $filename, $mime, null, true);
    }

    private function makeTeamAndUser(): array
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);

        return [$team, $user];
    }

    private function makePost(Team $team, User $user): FeedPost
    {
        return FeedPost::create([
            'team_id' => $team->id,
            'author_id' => $user->id,
            'category' => 'general',
            'priority' => 'normal',
            'body' => 'Test post.',
        ]);
    }

    #[Test]
    public function service_uses_db_storage_when_s3_not_configured(): void
    {
        Queue::fake();
        Config::set('filesystems.disks.s3.key', '');
        Config::set('filesystems.disks.s3.secret', '');
        Config::set('filesystems.disks.s3.bucket', '');

        [$team, $user] = $this->makeTeamAndUser();
        $post = $this->makePost($team, $user);

        $service = new FeedAttachmentService;
        $this->assertFalse($service->isS3Configured());

        $file = $this->makeFakeFile($this->minimalPng(), 'photo.png', 'image/png');
        $attachment = $service->store($file, $post, $user);

        $this->assertEquals('db', $attachment->storage_type);
        $this->assertNotNull($attachment->file_data);
        $this->assertNull($attachment->file_url);
    }

    #[Test]
    public function service_uses_s3_storage_when_configured(): void
    {
        Queue::fake();
        Storage::fake('s3');

        Config::set('filesystems.disks.s3.key', 'fake-key');
        Config::set('filesystems.disks.s3.secret', 'fake-secret');
        Config::set('filesystems.disks.s3.bucket', 'fake-bucket');

        [$team, $user] = $this->makeTeamAndUser();
        $post = $this->makePost($team, $user);

        $service = new FeedAttachmentService;
        $this->assertTrue($service->isS3Configured());

        $file = $this->makeFakeFile($this->minimalPng(), 'photo.png', 'image/png');
        $attachment = $service->store($file, $post, $user);

        $this->assertEquals('s3', $attachment->storage_type);
        $this->assertNotNull($attachment->file_url);
        $this->assertNull($attachment->file_data);

        // For Storage::fake('s3'), url() returns http://localhost/storage/{path}
        // The actual file is stored at feeds/{post_id}/{uuid}.{ext}
        $urlPath = parse_url($attachment->file_url, PHP_URL_PATH);
        $s3Path = preg_replace('#^/?storage/#', '', ltrim((string) $urlPath, '/'));
        Storage::disk('s3')->assertExists($s3Path);
    }

    #[Test]
    public function service_falls_back_to_db_when_s3_upload_fails(): void
    {
        Queue::fake();

        // Configure S3 credentials but do NOT fake the disk — any real S3 call will fail
        Config::set('filesystems.disks.s3.key', 'fake-key');
        Config::set('filesystems.disks.s3.secret', 'fake-secret');
        Config::set('filesystems.disks.s3.bucket', 'fake-bucket');
        Config::set('filesystems.disks.s3.region', 'us-east-1');

        // Force S3 driver to throw on put()
        Storage::shouldReceive('disk->put')->andThrow(new \RuntimeException('S3 connection refused'));

        [$team, $user] = $this->makeTeamAndUser();
        $post = $this->makePost($team, $user);

        $file = $this->makeFakeFile($this->minimalPng(), 'photo.png', 'image/png');
        $attachment = (new FeedAttachmentService)->store($file, $post, $user);

        // Should have fallen back to DB storage
        $this->assertEquals('db', $attachment->storage_type);
        $this->assertNotNull($attachment->file_data);
    }

    #[Test]
    public function is_s3_configured_returns_false_when_credentials_missing(): void
    {
        Config::set('filesystems.disks.s3.key', '');
        Config::set('filesystems.disks.s3.secret', 'secret');
        Config::set('filesystems.disks.s3.bucket', 'bucket');

        $this->assertFalse((new FeedAttachmentService)->isS3Configured());

        Config::set('filesystems.disks.s3.key', 'key');
        Config::set('filesystems.disks.s3.secret', '');
        Config::set('filesystems.disks.s3.bucket', 'bucket');

        $this->assertFalse((new FeedAttachmentService)->isS3Configured());

        Config::set('filesystems.disks.s3.key', 'key');
        Config::set('filesystems.disks.s3.secret', 'secret');
        Config::set('filesystems.disks.s3.bucket', '');

        $this->assertFalse((new FeedAttachmentService)->isS3Configured());
    }

    #[Test]
    public function is_s3_configured_returns_true_when_all_credentials_present(): void
    {
        Config::set('filesystems.disks.s3.key', 'AKIAIOSFODNN7EXAMPLE');
        Config::set('filesystems.disks.s3.secret', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');
        Config::set('filesystems.disks.s3.bucket', 'mines-platform-files');

        $this->assertTrue((new FeedAttachmentService)->isS3Configured());
    }

    #[Test]
    public function migration_job_skips_records_already_on_s3(): void
    {
        Queue::fake();
        Storage::fake('s3');

        Config::set('filesystems.disks.s3.key', 'fake-key');
        Config::set('filesystems.disks.s3.secret', 'fake-secret');
        Config::set('filesystems.disks.s3.bucket', 'fake-bucket');

        [$team, $user] = $this->makeTeamAndUser();
        $post = $this->makePost($team, $user);

        // Pre-existing S3 record
        $s3Attachment = FeedAttachment::create([
            'post_id' => $post->id,
            'uploader_id' => $user->id,
            'file_name' => 'existing.png',
            'file_type' => 'image/png',
            'file_size' => 100,
            'uploaded_at' => now(),
            'storage_type' => 's3',
            'file_url' => 'https://s3.amazonaws.com/bucket/feeds/1/existing.png',
            'file_data' => null,
        ]);

        (new MigrateFeedAttachmentsToS3Job)->handle();

        // S3 record should be untouched
        $this->assertEquals('s3', $s3Attachment->fresh()->storage_type);
        $this->assertEquals('https://s3.amazonaws.com/bucket/feeds/1/existing.png', $s3Attachment->fresh()->file_url);
    }

    #[Test]
    public function migration_job_aborts_gracefully_when_s3_not_configured(): void
    {
        Config::set('filesystems.disks.s3.key', '');
        Config::set('filesystems.disks.s3.secret', '');
        Config::set('filesystems.disks.s3.bucket', '');

        [$team, $user] = $this->makeTeamAndUser();
        $post = $this->makePost($team, $user);

        // Create a DB attachment
        FeedAttachment::create([
            'post_id' => $post->id,
            'uploader_id' => $user->id,
            'file_name' => 'file.png',
            'file_type' => 'image/png',
            'file_size' => 100,
            'uploaded_at' => now(),
            'storage_type' => 'db',
            'file_url' => null,
            'file_data' => 'binary-content',
        ]);

        // Job should run without exception and leave DB record untouched
        (new MigrateFeedAttachmentsToS3Job)->handle();

        $this->assertEquals(1, FeedAttachment::where('storage_type', 'db')->count());
    }

    #[Test]
    public function migration_job_migrates_db_attachments_to_s3(): void
    {
        Queue::fake();
        Storage::fake('s3');

        Config::set('filesystems.disks.s3.key', 'fake-key');
        Config::set('filesystems.disks.s3.secret', 'fake-secret');
        Config::set('filesystems.disks.s3.bucket', 'fake-bucket');

        [$team, $user] = $this->makeTeamAndUser();
        $post = $this->makePost($team, $user);

        $attachment = FeedAttachment::create([
            'post_id' => $post->id,
            'uploader_id' => $user->id,
            'file_name' => 'migrate_me.png',
            'file_type' => 'image/png',
            'file_size' => strlen($this->minimalPng()),
            'uploaded_at' => now(),
            'storage_type' => 'db',
            'file_url' => null,
            'file_data' => $this->minimalPng(),
        ]);

        (new MigrateFeedAttachmentsToS3Job)->handle();

        $fresh = $attachment->fresh();
        $this->assertEquals('s3', $fresh->storage_type);
        $this->assertNotNull($fresh->file_url);
        $this->assertNull($fresh->file_data);
    }
}
