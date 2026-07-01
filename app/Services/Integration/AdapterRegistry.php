<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerAdapterInterface;

/**
 * Registry that resolves the correct OEM adapter for a given provider slug.
 *
 * To add a new OEM: implement ManufacturerAdapterInterface, bind it in
 * AppServiceProvider, and add its slug → class mapping here.
 */
class AdapterRegistry
{
    /** @var array<string, class-string<ManufacturerAdapterInterface>> */
    private array $adapters = [
        'bell' => Adapters\BellEquipmentAdapter::class,
        'komatsu' => Adapters\GenericOemAdapter::class,
        'volvo' => Adapters\GenericOemAdapter::class,
        'cat' => Adapters\GenericOemAdapter::class,
        'ctrack' => Adapters\GenericOemAdapter::class,
        'john-deere' => Adapters\GenericOemAdapter::class,
        'sandvik' => Adapters\GenericOemAdapter::class,
        'epiroc' => Adapters\GenericOemAdapter::class,
        'liebherr' => Adapters\GenericOemAdapter::class,
        'hitachi' => Adapters\GenericOemAdapter::class,
        'hyundai' => Adapters\GenericOemAdapter::class,
        'atlas-copco' => Adapters\GenericOemAdapter::class,
        'doosan' => Adapters\GenericOemAdapter::class,
        'jcb' => Adapters\GenericOemAdapter::class,
        'case' => Adapters\GenericOemAdapter::class,
    ];

    /**
     * Display metadata for each provider (shown in the manufacturer grid).
     *
     * @var array<string, array{name: string, icon: string, description: string}>
     */
    private array $metadata = [
        'bell' => ['name' => 'Bell Equipment',  'icon' => '🔔', 'description' => 'ADT & Graders (ISO 15143-3 / Fleetmatic)'],
        'komatsu' => ['name' => 'Komatsu',         'icon' => '🟡', 'description' => 'SmartConstruction fleet telemetry'],
        'volvo' => ['name' => 'Volvo CE',        'icon' => '🔵', 'description' => 'CareTrack connected machines'],
        'cat' => ['name' => 'Caterpillar',     'icon' => '🐱', 'description' => 'VisionLink fleet management'],
        'ctrack' => ['name' => 'CTrack',          'icon' => '📡', 'description' => 'South African telematics provider'],
        'john-deere' => ['name' => 'John Deere',      'icon' => '🦌', 'description' => 'JDLink Operations Center'],
        'sandvik' => ['name' => 'Sandvik',         'icon' => '⛏️',  'description' => 'Mining & rock technology'],
        'epiroc' => ['name' => 'Epiroc',          'icon' => '💎', 'description' => 'Underground & surface drilling'],
        'liebherr' => ['name' => 'Liebherr',        'icon' => '🏗️',  'description' => 'Mining & material handling'],
        'hitachi' => ['name' => 'Hitachi',         'icon' => '🔴', 'description' => 'ConSite OIL & fleet'],
        'hyundai' => ['name' => 'Hyundai CE',      'icon' => '🟢', 'description' => 'Hi-MATE remote management'],
        'atlas-copco' => ['name' => 'Atlas Copco',     'icon' => '🔧', 'description' => 'Mining & compressor fleet'],
        'doosan' => ['name' => 'Doosan',          'icon' => '🏭', 'description' => 'DoosanConnect telematics'],
        'jcb' => ['name' => 'JCB',             'icon' => '🟠', 'description' => 'LiveLink machine monitoring'],
        'case' => ['name' => 'CASE',            'icon' => '🔶', 'description' => 'SiteWatch fleet management'],
    ];

    public function has(string $provider): bool
    {
        return isset($this->adapters[$provider]);
    }

    public function resolve(string $provider): ManufacturerAdapterInterface
    {
        $class = $this->adapters[$provider]
            ?? Adapters\GenericOemAdapter::class;

        return app($class);
    }

    /**
     * All registered providers with display metadata + credential schema.
     *
     * @return array<string, array{name: string, icon: string, description: string, credential_schema: list<array<string, mixed>>}>
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->metadata as $slug => $meta) {
            $adapter = $this->resolve($slug);
            $result[$slug] = array_merge($meta, [
                'credential_schema' => $adapter->credentialSchema(),
            ]);
        }

        return $result;
    }
}
