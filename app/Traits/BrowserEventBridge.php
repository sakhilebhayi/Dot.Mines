<?php

namespace App\Traits;

trait BrowserEventBridge
{
    /**
     * Bridge method so older Livewire components (or app code) can call
     * $this->dispatchBrowserEvent(...) even if the Component class doesn't
     * implement it. We forward to the existing Livewire dispatch(...) method
     * with the payload as a single parameter.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatchBrowserEvent(string $event, array $payload = []): mixed
    {
        if (method_exists($this, 'dispatch')) {
            return $this->dispatch($event, $payload);
        }

        // Fallback: throw so caller can see it if dispatch isn't available
        throw new \BadMethodCallException(sprintf('Method %s::dispatchBrowserEvent does not exist and dispatch fallback is not available.', static::class));
    }
}
