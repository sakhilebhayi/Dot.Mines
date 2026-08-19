<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Data Retention Windows
    |--------------------------------------------------------------------------
    |
    | metrics_days: machine_metrics rows older than this are moved to
    | machine_metrics_archive by the nightly ArchiveOldMetricsJob.
    |
    | soft_delete_grace_days: soft-deleted operational records (production
    | records/targets, shifts, mine areas) are permanently purged by the
    | weekly PurgeExpiredSoftDeletesJob once deleted_at is older than this.
    |
    | Read via config() so the values survive `php artisan config:cache` --
    | env() calls outside config files silently return defaults in cached
    | production environments.
    |
    */

    'metrics_days' => (int) env('METRICS_RETENTION_DAYS', 90),

    'soft_delete_grace_days' => (int) env('SOFT_DELETE_GRACE_DAYS', 30),

];
