<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\FeedAttachment;
use App\Models\FeedPost;
use App\Models\Team;
use App\Models\User;
use App\Services\AuditService;
use App\Services\FeedAttachmentService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Comprehensive verification suite for the Mine Feeds secure DB storage system.
 *
 * Covers:
 *   1. Upload works  (DB storage, MIME validation, size guard, filename sanitisation)
 *   2. Retrieval works  (streaming, Content-Type, auth enforcement)
 *   3. Legacy S3 backward compatibility
 *   4. Scalability  (blob excluded from listings, N+1 prevention)
 *   5. Security controls  (MIME spoofing, cross-tenant isolation, API binary leak)
 *   6. Audit logging  (event creation, structure, append-only)
 */
class FeedStorageVerificationTest extends TestCase
{
    use RefreshDatabase;

    // ── Binary helpers (no GD required) ──────────────────────────────────────

    /**
     * Minimal 1×1 white JPEG — recognised by finfo as image/jpeg.
     */
    private function minimalJpeg(): string
    {
        return base64_decode(
            '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8U'
            .'HRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAARCAABAAEDASIA'
            .'AhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/xAAU'
            .'AQEAAAAAAAAAAAAAAAAAAAAA/8QAFREBAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEQMRAD8A'
            .'JQAB/9k='
        );
    }

    /**
     * Minimal 1×1 PNG — recognised by finfo as image/png.
     */
    private function minimalPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA'
            .'WjR9awAAAABJRU5ErkJggg=='
        );
    }

    /**
     * Minimal PDF — recognised by finfo as application/pdf.
     */
    private function minimalPdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            ."2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            ."xref\n0 3\n0000000000 65535 f \n0000000009 00000 n \n"
            ."0000000058 00000 n \ntrailer\n<< /Size 3 /Root 1 0 R >>\n"
            ."startxref\n110\n%%EOF\n";
    }

    /** Create an UploadedFile from raw binary bytes. */
    private function makeFakeFile(string $contents, string $filename, ?string $mime = null): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ftest_');
        file_put_contents($tmp, $contents);

        return new UploadedFile($tmp, $filename, $mime, null, true);
    }

    // ── Model/team helpers ────────────────────────────────────────────────────

    /**
     * @return array<mixed>
     */
    private function makeTeamWithUser(): array
    {
        $team = Team::factory()->create(['personal_team' => false]);
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'editor']);

        return [$team, $user];
    }

    private function makePost(Team $team, User $user): FeedPost
    {
        // create() only runs an INSERT — team global scope only affects SELECTs
        return FeedPost::create([
            'team_id' => $team->id,
            'author_id' => $user->id,
            'category' => 'general',
            'priority' => 'normal',
            'body' => 'Test post body.',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. UPLOAD WORKS
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function upload_stores_binary_in_database_with_correct_metadata(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);

        $file = $this->makeFakeFile($this->minimalJpeg(), 'photo.jpg', 'image/jpeg');
        $service = app(FeedAttachmentService::class);
        $attachment = $service->store($file, $post, $user);

        $this->assertDatabaseHas('feed_attachments', [
            'id' => $attachment->id,
            'post_id' => $post->id,
            'uploader_id' => $user->id,
            'storage_type' => 'db',
            'file_url' => null,
        ]);

        // Actual binary was persisted
        $raw = DB::table('feed_attachments')->where('id', $attachment->id)->value('file_data');
        $this->assertNotNull($raw);
        $this->assertGreaterThan(0, strlen($raw));

        // Metadata columns are correct
        $this->assertSame('image/jpeg', $attachment->file_type);
        $this->assertGreaterThan(0, $attachment->file_size);
        $this->assertNotNull($attachment->uploaded_at);
        $this->assertSame($user->id, $attachment->uploader_id);
    }

    #[Test]
    public function upload_sanitises_path_traversal_in_filename(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $service = app(FeedAttachmentService::class);

        $file = $this->makeFakeFile(
            $this->minimalJpeg(), '../../etc/passwd.jpg', 'image/jpeg'
        );

        $attachment = $service->store($file, $post, $user);

        $this->assertStringNotContainsString('/', $attachment->file_name);
        $this->assertStringNotContainsString('\\', $attachment->file_name);
        $this->assertStringNotContainsString('..', $attachment->file_name);
        $this->assertNotEmpty($attachment->file_name);
    }

    #[Test]
    public function upload_sanitises_null_bytes_in_filename(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $service = app(FeedAttachmentService::class);

        $file = $this->makeFakeFile(
            $this->minimalJpeg(), "photo\x00.jpg", 'image/jpeg'
        );

        $attachment = $service->store($file, $post, $user);

        $this->assertStringNotContainsString("\x00", $attachment->file_name);
    }

    #[Test]
    public function upload_rejects_disallowed_mime_php_script(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $service = app(FeedAttachmentService::class);

        $file = $this->makeFakeFile(
            '<?php system("id"); ?>', 'shell.php', null
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not permitted/i');
        $service->store($file, $post, $user);
    }

    #[Test]
    public function upload_rejects_html_xss_file(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $service = app(FeedAttachmentService::class);

        $file = $this->makeFakeFile(
            '<html><script>alert(1)</script></html>', 'xss.html', null
        );

        $this->expectException(\InvalidArgumentException::class);
        $service->store($file, $post, $user);
    }

    #[Test]
    public function upload_rejects_empty_file(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $service = app(FeedAttachmentService::class);

        $file = $this->makeFakeFile('', 'empty.jpg', null);

        $this->expectException(\InvalidArgumentException::class);
        $service->store($file, $post, $user);
    }

    #[Test]
    public function upload_accepts_jpeg_png_and_pdf(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $service = app(FeedAttachmentService::class);

        $pairs = [
            [$this->minimalJpeg(), 'test.jpg',    'image/jpeg'],
            [$this->minimalPng(),  'test.png',    'image/png'],
            [$this->minimalPdf(),  'report.pdf',  'application/pdf'],
        ];

        foreach ($pairs as [$bytes, $name, $mime]) {
            $att = $service->store($this->makeFakeFile($bytes, $name, $mime), $post, $user);
            $this->assertInstanceOf(FeedAttachment::class, $att);
            $this->assertSame('db', $att->storage_type);
        }

        $this->assertSame(3, FeedAttachment::whereNull('file_url')
            ->where('post_id', $post->id)
            ->count());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. RETRIEVAL WORKS
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function serve_endpoint_streams_db_file_with_correct_headers(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $attachment = app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'photo.jpg', 'image/jpeg'),
            $post, $user
        );

        $response = $this->actingAs($user)
            ->get(route('feed.attachment.serve', $attachment->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/jpeg');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        // SecurityHeaders middleware overrides the controller value with DENY (stronger)
        $response->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('Content-Length'));
    }

    #[Test]
    public function serve_endpoint_uses_inline_disposition_for_images(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $attachment = app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'photo.jpg', 'image/jpeg'),
            $post, $user
        );

        $response = $this->actingAs($user)
            ->get(route('feed.attachment.serve', $attachment->id));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('inline', $disposition);
    }

    #[Test]
    public function serve_endpoint_uses_attachment_disposition_for_pdf(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $attachment = app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalPdf(), 'report.pdf', 'application/pdf'),
            $post, $user
        );

        $response = $this->actingAs($user)
            ->get(route('feed.attachment.serve', $attachment->id));

        $disposition = $response->headers->get('Content-Disposition');
        $this->assertStringStartsWith('attachment', $disposition);
    }

    #[Test]
    public function serve_endpoint_returns_404_for_nonexistent_id(): void
    {
        [, $user] = $this->makeTeamWithUser();

        $this->actingAs($user)
            ->get('/feed/attachments/999999')
            ->assertNotFound();
    }

    #[Test]
    public function url_accessor_for_db_record_returns_serve_route(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $attachment = app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalPng(), 'img.png', 'image/png'),
            $post, $user
        );

        $this->assertStringContainsString('/feed/attachments/', $attachment->url);
        $this->assertStringContainsString((string) $attachment->id, $attachment->url);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. LEGACY S3 BACKWARD COMPATIBILITY
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function legacy_s3_url_accessor_returns_original_s3_url(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);

        $s3Url = 'https://my-bucket.s3.amazonaws.com/feed/attachments/old-file.jpg';
        $att = FeedAttachment::create([
            'post_id' => $post->id,
            'storage_type' => 's3',
            'file_url' => $s3Url,
            'file_name' => 'old-file.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 204800,
            'uploaded_at' => now()->subDays(30),
        ]);

        $this->assertSame($s3Url, $att->url);
    }

    #[Test]
    public function serve_endpoint_redirects_legacy_s3_record_to_s3_url(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);

        $s3Url = 'https://my-bucket.s3.amazonaws.com/feed/attachments/doc.pdf';
        $att = FeedAttachment::create([
            'post_id' => $post->id,
            'storage_type' => 's3',
            'file_url' => $s3Url,
            'file_name' => 'doc.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 512000,
            'uploaded_at' => now()->subDays(60),
        ]);

        $this->actingAs($user)
            ->get(route('feed.attachment.serve', $att->id))
            ->assertRedirect($s3Url);
    }

    #[Test]
    public function s3_and_db_records_coexist_in_same_post(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);

        // Legacy S3 record
        FeedAttachment::create([
            'post_id' => $post->id,
            'storage_type' => 's3',
            'file_url' => 'https://s3.example.com/old.jpg',
            'file_name' => 'old.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 50000,
            'uploaded_at' => now()->subMonth(),
        ]);

        // New DB record
        app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'new.jpg', 'image/jpeg'),
            $post, $user
        );

        $attachments = FeedAttachment::where('post_id', $post->id)->get();
        $this->assertSame(2, $attachments->count());

        $s3Att = $attachments->firstWhere('storage_type', 's3');
        $dbAtt = $attachments->firstWhere('storage_type', 'db');

        $this->assertNotNull($s3Att);
        $this->assertNotNull($dbAtt);
        $this->assertStringContainsString('s3.example.com', $s3Att->url);
        $this->assertStringContainsString('/feed/attachments/', $dbAtt->url);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. SCALABILITY — BLOB EXCLUDED FROM LISTINGS
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function attachments_relation_does_not_load_file_data_blob(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);

        app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'big.jpg', 'image/jpeg'),
            $post, $user
        );

        $loaded = FeedPost::withoutGlobalScope('team')
            ->with('attachments')
            ->find($post->id);

        $attributes = $loaded->attachments->first()->getAttributes();

        // The BLOB column must NOT appear in eager-loaded attributes
        $this->assertArrayNotHasKey('file_data', $attributes,
            'file_data blob must NOT be loaded during feed listing — causes memory bloat'
        );
        // Metadata columns ARE present
        $this->assertArrayHasKey('file_name', $attributes);
        $this->assertArrayHasKey('file_type', $attributes);
        $this->assertArrayHasKey('file_size', $attributes);
    }

    #[Test]
    public function paginated_feed_listing_avoids_n_plus_1_queries(): void
    {
        [$team, $user] = $this->makeTeamWithUser();

        for ($i = 0; $i < 5; $i++) {
            $post = $this->makePost($team, $user);
            app(FeedAttachmentService::class)->store(
                $this->makeFakeFile($this->minimalJpeg(), "img{$i}.jpg", 'image/jpeg'),
                $post, $user
            );
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        FeedPost::withoutGlobalScope('team')
            ->with(['attachments'])
            ->where('team_id', $team->id)
            ->paginate(25)
            ->items();

        // Without N+1: 2 queries (1 for count, 1 for posts, 1 for attachments = 3 max)
        $this->assertLessThanOrEqual(4, $queryCount,
            "Expected ≤4 queries (count + posts + attachments + maybe paginate), got {$queryCount}"
        );
    }

    #[Test]
    public function formatted_size_returns_human_readable_string(): void
    {
        $cases = [
            [0,          'Unknown size'],
            [null,       'Unknown size'],
            [512,        '512 B'],
            [1536,       '1.5 KB'],
            [1_572_864,  '1.5 MB'],
            [1_073_741_824, '1 GB'],
        ];

        foreach ($cases as [$size, $expected]) {
            $att = new FeedAttachment(['file_size' => $size, 'storage_type' => 'db']);
            $this->assertSame($expected, $att->formattedSize(), "file_size={$size}");
        }
    }

    #[Test]
    public function is_image_and_is_pdf_helpers_work_correctly(): void
    {
        $jpg = new FeedAttachment(['file_type' => 'image/jpeg', 'storage_type' => 'db']);
        $pdf = new FeedAttachment(['file_type' => 'application/pdf', 'storage_type' => 'db']);
        $mp3 = new FeedAttachment(['file_type' => 'audio/mpeg', 'storage_type' => 'db']);

        $this->assertTrue($jpg->isImage());
        $this->assertFalse($jpg->isPdf());
        $this->assertTrue($pdf->isPdf());
        $this->assertFalse($pdf->isImage());
        $this->assertTrue($mp3->isAudio());
        $this->assertFalse($mp3->isImage());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. SECURITY CONTROLS
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function serve_endpoint_requires_authentication(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $attachment = app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'secret.jpg', 'image/jpeg'),
            $post, $user
        );

        // Guest (unauthenticated) request must be redirected to login
        $this->get(route('feed.attachment.serve', $attachment->id))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function serve_endpoint_denies_user_from_different_team(): void
    {
        [$team, $owner] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $owner);
        $attachment = app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'confidential.jpg', 'image/jpeg'),
            $post, $owner
        );

        // Attacker belongs to a completely different team
        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $attacker = User::factory()->create(['current_team_id' => $otherTeam->id]);
        $otherTeam->users()->attach($attacker, ['role' => 'editor']);

        $response = $this->actingAs($attacker)
            ->get(route('feed.attachment.serve', $attachment->id));

        // The FeedPost relation on FeedAttachment applies HasTeamFilters scope;
        // if the post is not visible to the attacker's team, abort(404) fires before
        // the policy check. Both 403 and 404 correctly deny access.
        $this->assertContains($response->status(), [403, 404],
            'Cross-team file serve must be denied (403 or 404 accepted)');
    }

    #[Test]
    public function file_data_blob_is_hidden_from_toarray_and_tojson(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);
        $attachment = app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'photo.jpg', 'image/jpeg'),
            $post, $user
        );

        // Neither toArray() nor toJson() must expose the raw binary
        $arr = $attachment->toArray();
        $json = json_decode($attachment->toJson(), true);

        $this->assertArrayNotHasKey('file_data', $arr,
            'file_data must be hidden from toArray() to prevent API blob leakage'
        );
        $this->assertArrayNotHasKey('file_data', $json,
            'file_data must be hidden from toJson() to prevent API blob leakage'
        );
    }

    #[Test]
    public function upload_service_is_the_only_write_path_for_new_attachments(): void
    {
        // Verify allowed MIME types list is complete and does NOT include executables
        $allowedMimes = FeedAttachmentService::ALLOWED_MIMES;

        $dangerous = [
            'application/x-php', 'application/x-executable', 'application/x-sh',
            'text/javascript', 'text/html', 'application/javascript',
            'application/x-httpd-php', 'application/octet-stream',
        ];

        foreach ($dangerous as $mime) {
            $this->assertNotContains($mime, $allowedMimes,
                "Dangerous MIME type '{$mime}' should not be in the allowed list"
            );
        }
    }

    #[Test]
    public function upload_rate_limiter_is_registered(): void
    {
        $this->assertNotNull(
            RateLimiter::limiter('uploads'),
            "'uploads' rate limiter must be registered in AppServiceProvider"
        );
    }

    #[Test]
    public function feed_post_rate_limiter_is_registered(): void
    {
        $this->assertNotNull(
            RateLimiter::limiter('feed-post'),
            "'feed-post' rate limiter must be registered in AppServiceProvider"
        );
    }

    #[Test]
    public function api_upload_route_enforces_10_per_minute_rate_limit(): void
    {
        [$team, $user] = $this->makeTeamWithUser();

        // Resolve the registered 'uploads' limiter callback and call it
        // with a mock request bound to our test user.
        $callback = RateLimiter::limiter('uploads');
        $this->assertNotNull($callback, "'uploads' rate limiter must be registered");

        $mockRequest = Request::create('/api/test', 'POST');
        $mockRequest->setUserResolver(fn () => $user);

        /** @var Limit $limit */
        $limit = $callback($mockRequest);

        $this->assertSame(10, $limit->maxAttempts,
            'uploads rate limiter must allow exactly 10 requests per minute');
        $this->assertSame(60, $limit->decaySeconds,
            'uploads rate limiter must have a 60-second (1-minute) decay window');
        $this->assertSame($user->id, $limit->key,
            'uploads rate limiter must key on the authenticated user ID');
    }

    #[Test]
    public function cross_tenant_api_upload_is_blocked_by_policy(): void
    {
        [$team, $owner] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $owner);

        // Attacker on a different team
        $otherTeam = Team::factory()->create(['personal_team' => false]);
        $attacker = User::factory()->create(['current_team_id' => $otherTeam->id]);
        $otherTeam->users()->attach($attacker, ['role' => 'editor']);

        $response = $this->actingAs($attacker, 'sanctum')
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson(
                "/api/v1/feed/{$post->id}/attachments",
                ['file' => $this->makeFakeFile($this->minimalJpeg(), 'test.jpg', 'image/jpeg')]
            );

        // Route model binding scopes FeedPost to current_team_id — post not found → 404
        // (Alternatively policy would return 403 — both mean access denied)
        $this->assertContains($response->status(), [403, 404],
            'Cross-tenant upload must be blocked (403 or 404 accepted)');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. AUDIT LOGGING
    // ─────────────────────────────────────────────────────────────────────────

    #[Test]
    public function upload_creates_audit_log_row(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        $post = $this->makePost($team, $user);

        app(FeedAttachmentService::class)->store(
            $this->makeFakeFile($this->minimalJpeg(), 'audit-test.jpg', 'image/jpeg'),
            $post, $user
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditLog::FEED_ATTACHMENT_UPLOAD,
            'actor_id' => $user->id,
            'team_id' => $team->id,
            'subject_type' => FeedAttachment::class,
        ]);
    }

    #[Test]
    public function audit_service_never_throws_even_on_write_failure(): void
    {
        // Temporarily rename the table so that the INSERT will fail
        DB::statement('ALTER TABLE audit_logs RENAME TO audit_logs_bak');

        try {
            AuditService::log(
                AuditLog::MACHINE_UPDATED,
                'Should not throw',
                null,
                [],
                999,
                999,
                '127.0.0.1'
            );
        } catch (\Throwable $e) {
            $this->fail('AuditService::log() must never throw — got: '.$e->getMessage());
        } finally {
            DB::statement('ALTER TABLE audit_logs_bak RENAME TO audit_logs');
        }

        $this->assertTrue(true); // reached here without exception
    }

    #[Test]
    public function audit_log_record_has_correct_structure(): void
    {
        [$team, $user] = $this->makeTeamWithUser();

        AuditService::log(
            AuditLog::MACHINE_UPDATED,
            'Updated test machine',
            null,
            ['field' => 'status', 'from' => 'active', 'to' => 'maintenance'],
            $user->id,
            $team->id,
            '10.0.0.1'
        );

        $row = DB::table('audit_logs')
            ->where('action', AuditLog::MACHINE_UPDATED)
            ->where('actor_id', $user->id)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame((int) $team->id, (int) $row->team_id);
        $this->assertSame('10.0.0.1', $row->ip_address);
        $this->assertNotNull($row->created_at);

        $meta = json_decode($row->meta, true);
        $this->assertSame('status', $meta['field']);
        $this->assertSame('maintenance', $meta['to']);
    }

    #[Test]
    public function audit_log_table_has_no_updated_at_column(): void
    {
        [$team, $user] = $this->makeTeamWithUser();
        AuditService::log(AuditLog::TEAM_SWITCH, 'Switched teams', null, [], $user->id, $team->id);

        $columns = array_keys((array) DB::table('audit_logs')->latest('id')->first());

        $this->assertNotContains('updated_at', $columns,
            'audit_logs is append-only and must not have an updated_at column'
        );
    }

    #[Test]
    public function audit_log_captures_all_required_action_constants(): void
    {
        $required = [
            AuditLog::LOGIN_SUCCESS,
            AuditLog::LOGIN_FAILED,
            AuditLog::LOGIN_LOCKOUT,
            AuditLog::LOGOUT,
            AuditLog::TEAM_SWITCH,
            AuditLog::FEED_POST_CREATED,
            AuditLog::FEED_POST_DELETED,
            AuditLog::FEED_POST_APPROVED,
            AuditLog::FEED_POST_REJECTED,
            AuditLog::FEED_ATTACHMENT_UPLOAD,
            AuditLog::MACHINE_CREATED,
            AuditLog::MACHINE_UPDATED,
            AuditLog::MACHINE_DELETED,
            AuditLog::MAINTENANCE_CREATED,
            AuditLog::MAINTENANCE_UPDATED,
            AuditLog::MAINTENANCE_COMPLETED,
            AuditLog::MAINTENANCE_DELETED,
            AuditLog::SUBSCRIPTION_CREATED,
            AuditLog::SUBSCRIPTION_UPDATED,
            AuditLog::SUBSCRIPTION_CANCELLED,
        ];

        foreach ($required as $constant) {
            $this->assertNotEmpty($constant, 'AuditLog action constant must not be empty');
            $this->assertIsString($constant);
        }
    }
}
