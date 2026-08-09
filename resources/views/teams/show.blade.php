<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-[var(--stone)] leading-tight">
            {{ __('Team Settings') }}
        </h2>
    </x-slot>

    <div>
        <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
            <p class="text-sm text-[var(--sand)] mb-6">
                Looking for general team settings or notification preferences? Go to <a href="{{ route('settings') }}" class="text-[var(--gold)] hover:text-[var(--gold-soft)] underline">Settings</a>.
            </p>

            @livewire('teams.update-team-name-form', ['team' => $team])

            @livewire('teams.team-member-manager', ['team' => $team])

            @if (Gate::check('delete', $team) && ! $team->personal_team)
                <x-section-border />

                <div class="mt-10 sm:mt-0">
                    @livewire('teams.delete-team-form', ['team' => $team])
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
