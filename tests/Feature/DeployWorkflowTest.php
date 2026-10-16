<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * A green CI run on main deploys itself; nothing else does. Pins the
 * trigger so a later edit cannot quietly turn pushes into deploys without
 * the gate, or drop the manual path.
 */
class DeployWorkflowTest extends TestCase
{
    public function test_deploy_triggers_on_successful_ci_or_confirmed_dispatch(): void
    {
        /** @var array{on: array{workflow_run: array{workflows: list<string>, types: list<string>, branches: list<string>}, workflow_dispatch?: mixed, push?: mixed}, jobs: array{deploy: array{if: string}}} $deploy */
        $deploy = Yaml::parseFile(base_path('.github/workflows/deploy.yml'));

        $this->assertSame(['CI - Security & Static Analysis'], $deploy['on']['workflow_run']['workflows']);
        $this->assertSame(['completed'], $deploy['on']['workflow_run']['types']);
        $this->assertSame(['main'], $deploy['on']['workflow_run']['branches']);
        $this->assertArrayHasKey('workflow_dispatch', $deploy['on']);
        $this->assertArrayNotHasKey('push', $deploy['on']);
        $this->assertStringContainsString("workflow_run.conclusion == 'success'", $deploy['jobs']['deploy']['if']);
    }

    public function test_scheduler_watchdog_runs_every_fifteen_minutes_and_deploy_verifies_the_crontab(): void
    {
        /** @var array{on: array{schedule: list<array{cron: string}>}} $watchdog */
        $watchdog = Yaml::parseFile(base_path('.github/workflows/scheduler-watchdog.yml'));
        $this->assertSame('*/15 * * * *', $watchdog['on']['schedule'][0]['cron']);

        $deploy = (string) file_get_contents(base_path('.github/workflows/deploy.yml'));
        $this->assertStringContainsString('MARK="# dot-mines"', $deploy);
        $this->assertStringContainsString('crontab verification failed', $deploy);
        $this->assertStringNotContainsString('| crontab -', $deploy, 'never pipe into crontab -');
    }
}
