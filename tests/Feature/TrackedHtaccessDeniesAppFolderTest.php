<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Production serves this app from a folder INSIDE the cPanel web root, so the root .htaccess is what refuses every
 * request for .env, the database and the logs. The deploy's guard used to append "Require all denied" to the tracked
 * file after each `git reset --hard`; the reset removed the line first, and for a few seconds after every deploy the web
 * server still answered 200 for those paths (seen on 2026-10-28). With the line committed, the reset leaves the file
 * untouched and the guard finds it already present. This test keeps the line (exactly as the guard writes it) in git.
 */
class TrackedHtaccessDeniesAppFolderTest extends TestCase
{
    public function test_the_tracked_root_htaccess_ends_with_the_deny_line_the_deploy_guard_writes(): void
    {
        $file = file_get_contents(base_path('.htaccess'));

        $this->assertStringEndsWith("\n# Dot: the application folder is never served.\nRequire all denied\n", $file);
    }
}
