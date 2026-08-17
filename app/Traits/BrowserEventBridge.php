<?php

namespace App\Traits;

/**
 * Livewire 2 → 3 polyfill: dispatchBrowserEvent() maps to dispatch().
 */
trait BrowserEventBridge
{
    /** @param array<string, mixed> $data */
    public function dispatchBrowserEvent(string $event, array $data = []): void
    {
        $this->dispatch($event, ...$data);
    }
}
