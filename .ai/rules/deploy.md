---
paths:
  - '.github/workflows/*.yml'
---

# Deployment

## The account's crontab is written only by deploy workflows, each touching only its own marker lines, with the write verified
On 2026-08-31 a sibling app's setup workflow piped `crontab -l | grep -v ... ; echo ...` into `crontab -`; the read side failed under `set -euo pipefail`, the subshell produced nothing, and `crontab -` installed an EMPTY crontab. Dot.Mines' `schedule:run` line (added by hand, owned by nothing) vanished and the scheduler was dead until 2026-10-16: no queue drain, no integration syncs, no guardian beat. Rules, enforced by the block in deploy.yml (the same block is used by every Dot deploy): (1) read the crontab to a file and STOP if the read fails for any reason other than "no crontab for user"; (2) remove and re-add only lines carrying this app's marker (`# dot-mines`); (3) after `crontab <file>`, read it back and refuse to finish, restoring the previous crontab, unless every other app's line count is unchanged and ours is exactly the expected count; (4) never add a cron line by hand, and never from a pipeline into `crontab -`. cron runs with a minimal PATH that resolves a different `php`, so lines carry the absolute PHP path and keep their last output in `storage/logs/cron-scheduler.log`. `server-logs.yml` prints the crontab, the schedule list and the scheduler's output; `scheduler-watchdog.yml` fails every 15 minutes while the scheduler or queue is not healthy, which emails the owner.
- `crontab -l` on the host can transiently answer empty (observed 2026-10-16, right after a write, under the lock). The block retries reads up to five times and trusts only a non-empty answer or an explicit "no crontab for". Never compute a verification count from a single unchecked `crontab -l`.
