<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Livewire\LivewireManager;

class VerifyLivewireComponents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'livewire:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify all Livewire components are properly registered';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Verifying Livewire Components...');
        $this->newLine();

        // Components that should exist in app/Livewire
        $appComponents = [
            'alerts',
            'dashboard',
            'documentation',
            'fleet',
            'fuel-management',
            'geofence-detail',
            'geofence-manager',
            'integration-manager',
            'live-map',
            'machine-assignment-manager',
            'machine-detail',
            'maintenance-dashboard',
            'mine-plan-uploader',
            'navbar',
            'report-generator',
            'reports',
            'settings',
            'sidebar',
        ];

        // Jetstream components that should be auto-registered
        $jetstreamComponents = [
            'navigation-menu',
            'profile.update-profile-information-form',
            'profile.update-password-form',
            'profile.two-factor-authentication-form',
            'profile.logout-other-browser-sessions-form',
            'profile.delete-user-form',
            'api.api-token-manager',
            'teams.create-team-form',
            'teams.update-team-name-form',
            'teams.team-member-manager',
            'teams.delete-team-form',
        ];

        $livewireManager = app(LivewireManager::class);
        $errors = [];

        $this->info('App Components:');
        foreach ($appComponents as $component) {
            // Convert kebab-case to PascalCase class name
            $className = 'App\\Livewire\\'.str_replace(' ', '', ucwords(str_replace('-', ' ', $component)));

            if (class_exists($className)) {
                $this->line("  <fg=green>✓</> {$component} ({$className})");
            } else {
                $this->line("  <fg=red>✗</> {$component} - Class {$className} not found");
                $errors[] = $component;
            }
        }

        $this->newLine();
        $this->info('Jetstream Components:');
        foreach ($jetstreamComponents as $component) {
            // Check if Jetstream components are registered
            $componentParts = explode('.', $component);
            if (count($componentParts) > 1) {
                // profile.update-profile-information-form -> UpdateProfileInformationForm
                $className = 'Laravel\\Jetstream\\Http\\Livewire\\'.
                    str_replace(' ', '', ucwords(str_replace('-', ' ', $componentParts[1])));
            } else {
                // navigation-menu -> NavigationMenu
                $className = 'Laravel\\Jetstream\\Http\\Livewire\\'.
                    str_replace(' ', '', ucwords(str_replace('-', ' ', $component)));
            }

            if (class_exists($className)) {
                $this->line("  <fg=green>✓</> {$component} ({$className})");
            } else {
                $this->line("  <fg=red>✗</> {$component} - Class {$className} not found");
                $errors[] = $component;
            }
        }

        $this->newLine();
        if (count($errors) > 0) {
            $this->error('Found '.count($errors).' component(s) with errors!');

            return self::FAILURE;
        }

        $this->info('✓ All components are properly registered!');

        return self::SUCCESS;
    }
}
