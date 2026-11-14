{{-- The signed-in landing: the state of the mine in one sentence, its age, and
     the one primary action. Not lazy, so all of it is in the first response.
     Re-reads on the board's own 30s visible-only cadence. Every figure is the
     current count for this team, shown as it is. The brand mark lives in the
     sidebar, once. --}}
<div wire:poll.visible.30s="loadState">
    @if ($teamName !== '')
        @php
            $primary = $this->primaryAction;
            $setupOpen = $totalMineAreas === 0 || $totalMachines === 0;
            $freshAt = $this->telemetryFreshAt;
            $linkClass = 'dm-link';
        @endphp

        <section aria-labelledby="landing-headline" class="dm-lede" data-landing>
            <x-dm-contours class="dm-lede__art" />
            <div class="dm-lede__text">
                <h2 id="landing-headline" data-landing-state>{{ $this->stateSentence }}</h2>
                <p class="dm-lede__sub">
                    {{ $teamName }}
                    &middot;
                    @if ($freshAt)
                        latest telemetry recorded <time datetime="{{ $freshAt->toIso8601String() }}" @class(['dm-signal' => $freshAt->diffInMinutes(now()) > \App\Livewire\Dashboard::STALE_AFTER_MINUTES])>{{ $freshAt->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}</time> ago
                    @else
                        no telemetry recorded yet
                    @endif
                </p>
            </div>
            <a href="{{ route($primary['route']) }}" data-primary-action class="dm-btn dm-btn--primary dm-lede__act">{{ $primary['label'] }}</a>
        </section>

        @if ($setupOpen)
            {{-- Honest empty state: what is missing, in order, and who can fix it. --}}
            <div class="dm-setup" data-landing-setup>
                <p class="dm-soft">
                    @if ($canCreateMineAreas || $canCreateMachines)
                        Setup is not finished. What is left:
                    @else
                        Setup is not finished. An administrator or fleet manager still has to do what is left.
                    @endif
                </p>
                <ol>
                    <li>
                        <a href="{{ route('mine-areas') }}" class="{{ $linkClass }} dm-link--tap">Create a mine area</a>
                        <span class="dm-num dm-soft">{{ $totalMineAreas > 0 ? 'done' : 'to do' }}</span>
                    </li>
                    <li>
                        <a href="{{ route('fleet') }}" class="{{ $linkClass }} dm-link--tap">Add your first machine</a>
                        <span class="dm-num dm-soft">{{ $totalMachines > 0 ? 'done' : 'to do' }}</span>
                    </li>
                    <li>
                        <a href="{{ route('teams.show', auth()->user()->currentTeam) }}" class="{{ $linkClass }} dm-link--tap">Invite your team</a>
                        <span class="dm-num dm-soft">any time</span>
                    </li>
                </ol>
            </div>
        @endif
    @endif
</div>
