---
name: queue-job-patterns
description: >
  Mines platform queue and background job patterns. Use when: creating a new queued job, adding
  retry logic, writing tests for jobs, debugging failed jobs in Horizon, checking which queue
  a job should go on, implementing a scheduled command, or recovering from queue failures.
argument-hint: 'Describe the queue or job task you need help with'
---

# Queue & Job Patterns

## When to Use

- Creating a new queued job class
- Adding retry/backoff logic to an existing job
- Writing PHPUnit tests for job dispatch and behaviour
- Debugging jobs in the `failed_jobs` table
- Deciding which queue a new job should use
- Adding a new scheduled command

---

## Standard Job Template

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MyNewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;    // seconds between retries
    public int $timeout = 120;   // seconds before timeout

    public function __construct(
        // inject dependencies via constructor — prefer IDs over models for large datasets
        public readonly int $teamId,
    ) {
        $this->onQueue('default'); // change to 'notifications', 'alerts', etc. as needed
    }

    public function handle(): void
    {
        // ... job logic
    }

    public function failed(\Throwable $e): void
    {
        Log::error('MyNewJob failed permanently', [
            'team_id'   => $this->teamId,
            'exception' => $e->getMessage(),
        ]);
    }
}
```

---

## Queue Name Reference

| Queue | Use for |
|---|---|
| `high` | Real-time critical work (location updates, live alerts) |
| `default` | General background work |
| `notifications` | Email delivery (`SendNotificationEmailJob`, `SendFeedNotificationJob`) |
| `alerts` | Alert generation |
| `downloads` | Report exports and file downloads |

---

## Pattern — Test a Job Dispatches

```php
use Illuminate\Support\Facades\Queue;

#[Test]
public function action_dispatches_my_job(): void
{
    Queue::fake();

    // ... trigger the action

    Queue::assertPushed(MyNewJob::class, function ($job) {
        return $job->teamId === $this->team->id;
    });
}
```

---

## Pattern — Test a Job Executes Correctly

```php
#[Test]
public function my_job_processes_correctly(): void
{
    // Don't Queue::fake() — run the job synchronously
    $team = Team::factory()->create();

    MyNewJob::dispatchSync($team->id);

    // Assert side effects
    $this->assertDatabaseHas('some_table', ['team_id' => $team->id]);
}
```

---

## Adding a Scheduled Command

```php
// routes/console.php
use App\Jobs\MyNewJob;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new MyNewJob(teamId: 1))->everyFiveMinutes()->withoutOverlapping();

// OR as an artisan command:
Schedule::command('my:command')->daily()->runInBackground();
```

---

## Recovering Failed Jobs

```bash
# List failed jobs
php artisan queue:failed

# Retry a single job (by ID shown in queue:failed list)
php artisan queue:retry {id}

# Retry all
php artisan queue:retry all

# Flush (delete all failed — only when root cause is fixed)
php artisan queue:flush
```

---

## Horizon Health Check

```bash
php artisan horizon:status   # should say: running
php artisan horizon:terminate # graceful restart (supervisor restarts it)
```
