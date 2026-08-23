<?php

namespace App\Livewire;

use App\Services\OpenApiGenerator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Documentation extends Component
{
    public string $activeSection = 'getting-started';

    /**
     * @param  string  $section
     */
    public function setSection($section): void
    {
        $this->activeSection = $section;
    }

    /**
     * The API reference, grouped by area for rendering.
     *
     * Generated from the route table (see OpenApiGenerator) rather than
     * written by hand, so the reference cannot fall behind the code the way
     * the old two-endpoint section did. Built only when that section is open;
     * cached for real traffic but never in development, where a stale
     * reference would defeat the point.
     *
     * @return array<string, array{description: string, operations: list<array{method: string, path: string, summary: string, permission: string, path_params: list<string>, query_params: list<string>, body_params: list<string>}>}>
     */
    public function getApiReferenceProperty(): array
    {
        if ($this->activeSection !== 'api-reference') {
            return [];
        }

        $generator = app(OpenApiGenerator::class);

        if (! app()->isProduction()) {
            return $generator->reference();
        }

        /** @var array<string, array{description: string, operations: list<array{method: string, path: string, summary: string, permission: string, path_params: list<string>, query_params: list<string>, body_params: list<string>}>}> */
        return Cache::remember('api.openapi.reference', now()->addHour(), fn (): array => $generator->reference());
    }

    public function render(): View
    {
        return view('livewire.documentation');
    }
}
