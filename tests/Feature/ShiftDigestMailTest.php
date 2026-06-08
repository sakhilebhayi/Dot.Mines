<?php

namespace Tests\Feature;

use App\Mail\ShiftDigestMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShiftDigestMailTest extends TestCase
{
    use RefreshDatabase;

    private function makeShiftDigest(string $shift = 'A'): ShiftDigestMail
    {
        return new ShiftDigestMail(
            shift: $shift,
            teamName: 'Alpha Mine',
            stats: [
                'active_machines' => 12,
                'fuel_dispensed' => 1500,
                'total_tonnage' => 320,
                'alerts_triggered' => 3,
                'feed_posts' => 7,
            ],
            topPosts: [
                ['author' => 'John Doe', 'body' => 'Crusher unit back online.', 'content' => 'Crusher unit back online.', 'category' => 'maintenance', 'likes' => 2, 'comments' => 1],
            ],
            pendingApprovals: [],
            recipientUserId: null,
        );
    }

    #[Test]
    public function shift_digest_mail_is_queued_to_notifications_queue(): void
    {
        $mail = $this->makeShiftDigest();

        $this->assertSame('notifications', $mail->queue);
    }

    #[Test]
    public function shift_digest_subject_includes_team_name_and_shift_label(): void
    {
        $mail = $this->makeShiftDigest('B');
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Alpha Mine', $envelope->subject);
        $this->assertStringContainsString('Shift B', $envelope->subject);
    }

    #[Test]
    public function shift_digest_renders_with_stats(): void
    {
        $mail = $this->makeShiftDigest('C');
        $rendered = $mail->render();

        $this->assertStringContainsString('Alpha Mine', $rendered);
    }

    #[Test]
    public function shift_digest_has_plain_text_view(): void
    {
        $mail = $this->makeShiftDigest();
        $content = $mail->content();

        $this->assertNotNull($content->text);
    }

    #[Test]
    public function shift_digest_includes_unsubscribe_url_when_user_id_provided(): void
    {
        $mail = new ShiftDigestMail(
            shift: 'A',
            teamName: 'Mine Co',
            stats: [],
            topPosts: [],
            pendingApprovals: [],
            recipientUserId: 99,
        );

        $content = $mail->content();

        $this->assertArrayHasKey('unsubscribeUrl', $content->with);
        $this->assertNotNull($content->with['unsubscribeUrl']);
    }

    #[Test]
    public function shift_digest_has_null_unsubscribe_url_when_no_user_id(): void
    {
        $mail = $this->makeShiftDigest();
        $content = $mail->content();

        $this->assertArrayHasKey('unsubscribeUrl', $content->with);
        $this->assertNull($content->with['unsubscribeUrl']);
    }

    #[Test]
    public function shift_digest_has_tracking_header(): void
    {
        $mail = $this->makeShiftDigest();
        $envelope = $mail->envelope();

        $this->assertNotEmpty($envelope->using);
    }
}
