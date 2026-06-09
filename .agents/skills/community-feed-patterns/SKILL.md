---
name: community-feed-patterns
description: >
  Mines platform community feed patterns. Use when: creating or moderating feed posts, debugging
  comment or mention parsing, working with FeedApproval workflow, testing FeedAdminPanel, writing
  tests for feed isolation or attachments, understanding FeedAcknowledgement or DigestSubscription,
  or wiring feed events into the notification pipeline.
argument-hint: 'Describe the community feed task you need help with'
esm-layer: operational
esm-feeds-to:
  - incident-safety-patterns
  - compliance-agent
  - notification-system
  - audit-logging-patterns
esm-consumes-from:
  - notification-system
  - file-storage-patterns
  - rbac-patterns
---

# Community Feed Patterns

## When to Use

- Creating, updating, or moderating FeedPost records
- Debugging FeedApproval workflow (posts stuck in pending)
- Working with FeedMention / MentionParser
- Testing feed comment threading or FeedLike toggling
- Building or debugging Feed / FeedAdminPanel Livewire components
- Debugging DigestSubscription email delivery
- Wiring a feed event into the notification pipeline

---

## Core Models

```
FeedPost            — the main post (text + optional attachment)
FeedComment         — threaded reply on a post
FeedLike            — user like on a post (pivot)
FeedMention         — @user reference parsed from post/comment body
FeedApproval        — approval record for posts requiring moderation
FeedAcknowledgement — acknowledgement of a post by a required reader
FeedAttachment      — S3-backed file attached to a post
DigestSubscription  — user preference for digest email cadence
UserFeedPreference  — per-user feed display settings
```

---

## Post Lifecycle

```
User creates post
       ↓
FeedPost::created → FeedPostCreated::dispatch($post)
       ↓
  Does team require approval?
  yes → status = 'pending_approval'
      → SendFeedApprovalNotification → managers notified
  no  → status = 'published'
      → SendFeedPostNotification → followers notified
       ↓
Post published → FeedPostStatusChanged::dispatch($post)
       ↓
DigestSubscription members → included in next digest email
```

---

## Pattern — Creating a Post via API

```php
POST /api/v1/feed
{
    "body": "Production target achieved this shift! @operator_john great work.",
    "type": "update",        // update|safety|announcement|recognition
    "visibility": "team",    // team|all
    "requires_acknowledgement": false
}
// MentionParser resolves @operator_john → FeedMention created automatically
```

---

## Pattern — Approving a Post

```php
POST /api/v1/feed/{post}/approve
// Requires manage_feed permission
// Changes status: pending_approval → published
// Fires FeedPostStatusChanged
// Triggers SendFeedPostNotification
```

---

## MentionParser Usage

```php
use App\Services\MentionParser;

$parser  = app(MentionParser::class);
$mentions = $parser->parse($post->body, $post->team_id);
// Returns: Collection of User models matched by username within team
// Each becomes a FeedMention record linked to the post
// Mentioned users receive a 'mention' notification
```

---

## Pattern — Feed Test Setup

```php
#[Test]
public function feed_post_requires_approval_if_team_setting_enabled(): void
{
    $user = $this->adminUser();
    $user->currentTeam->update(['feed_requires_approval' => true]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/feed', ['body' => 'Test post', 'type' => 'update'])
        ->assertCreated();

    $this->assertDatabaseHas('feed_posts', [
        'team_id' => $user->current_team_id,
        'status'  => 'pending_approval',
    ]);
}

#[Test]
public function mention_creates_feed_mention_record(): void
{
    $user    = $this->adminUser();
    $mentioned = User::factory()->create();
    $user->currentTeam->users()->attach($mentioned);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/feed', [
            'body' => "Good work @{$mentioned->username}",
            'type' => 'recognition',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('feed_mentions', [
        'user_id' => $mentioned->id,
    ]);
}

#[Test]
public function feed_posts_are_isolated_between_teams(): void
{
    $userA = $this->adminUser();
    $userB = $this->createUserInSeparateTeam();
    FeedPost::factory()->published()->create(['team_id' => $userA->current_team_id]);

    $this->actingAs($userB, 'sanctum')
        ->getJson('/api/v1/feed')
        ->assertJsonCount(0, 'data');
}
```

---

## Feed Livewire Components

```
app/Livewire/Feed.php
  — main feed reader (paginated, filterable by type)
  — real-time: listens to FeedPostCreated via Echo
  — #[On('echo-private:team.{teamId}.feed,feed.post.created')]

app/Livewire/FeedAdminPanel.php
  — moderation queue (pending_approval posts)
  — approve / reject / flag actions
  — Requires: manage_feed permission
```

---

## Safety Intelligence Handoff

Feed posts of type `safety` or containing keywords (hazard, incident, near miss, unsafe):
- **incident-safety-patterns**: flag for incident review
- **compliance-reporting-patterns**: may be evidence for compliance records
- **audit-logging-patterns**: log moderation actions

---

## Commands Reference

```bash
# Run feed tests
php artisan test --compact tests/Feature/FeedTest.php

# Check posts stuck in approval
php artisan tinker --execute '
App\Models\FeedPost::where("status","pending_approval")
    ->where("created_at","<",now()->subHours(2))
    ->get(["id","team_id","body","created_at"]);
'

# Check digest subscriptions
php artisan tinker --execute '
App\Models\DigestSubscription::with("user")->get(["user_id","frequency","last_sent_at"]);
'
```
