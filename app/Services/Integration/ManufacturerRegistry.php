<?php

namespace App\Services\Integration;

use App\Contracts\ManufacturerServiceInterface;
use App\Models\Integration;

/**
 * The single authority for provider -> service resolution and the
 * manufacturer catalog (refactor program R2: extracted from
 * IntegrationService so "which service handles this integration?" has
 * exactly one answer with no sync orchestration around it).
 */
final class ManufacturerRegistry
{
    /**
     * Get service instance for an integration
     */
    public function resolveFor(Integration $integration): ?ManufacturerServiceInterface
    {
        // Integration::credentials is already cast to an array ('json' cast
        // in the model) -- json_decode()-ing it again threw a TypeError
        // ("Argument #1 ($json) must be of type string, array given") on
        // every single test/sync attempt, for every manufacturer, before
        // any manufacturer-specific code ever ran.
        $credentials = $integration->credentials ?? [];

        return match ($integration->provider) {
            'volvo' => app(VolvoService::class, ['credentials' => $credentials]),
            'cat' => app(CATService::class, ['credentials' => $credentials]),
            'komatsu' => app(KomatsuService::class, ['credentials' => $credentials]),
            'bell' => app(BellService::class, ['credentials' => $credentials]),
            'hitachi' => app(HitachiService::class, ['credentials' => $credentials]),
            'john-deere' => app(JohnDeereService::class, ['credentials' => $credentials]),
            'liebherr' => app(LiebherrService::class, ['credentials' => $credentials]),
            'hyundai' => app(HyundaiService::class, ['credentials' => $credentials]),
            'doosan' => app(DoosanService::class, ['credentials' => $credentials]),
            'jcb' => app(JCBService::class, ['credentials' => $credentials]),
            'case' => app(CASEService::class, ['credentials' => $credentials]),
            'sany' => app(SanyService::class, ['credentials' => $credentials]),
            'xcmg' => app(XCMGService::class, ['credentials' => $credentials]),
            'kobelco' => app(KobelcoService::class, ['credentials' => $credentials]),
            'new-holland' => app(NewHollandService::class, ['credentials' => $credentials]),
            'takeuchi' => app(TakeuchiService::class, ['credentials' => $credentials]),
            'kubota' => app(KubotaService::class, ['credentials' => $credentials]),
            'bobcat' => app(BobcatService::class, ['credentials' => $credentials]),
            'yanmar' => app(YanmarService::class, ['credentials' => $credentials]),
            'atlas-copco' => app(AtlasCopcoService::class, ['credentials' => $credentials]),
            'sandvik' => app(SandvikService::class, ['credentials' => $credentials]),
            'epiroc' => app(EpirocService::class, ['credentials' => $credentials]),
            'ctrack' => app(CTrackService::class, ['credentials' => $credentials]),
            'roundebult' => app(RoundebultService::class, ['credentials' => $credentials]),
            'kawasaki' => app(KawasakiService::class, ['credentials' => $credentials]),
            default => null,
        };
    }

    /**
     * Get available manufacturers
     *
     * @return array<string, array{name: string, description: string, status: string}>
     */
    public function catalog(): array
    {
        // 'status' reflects whether the manufacturer's service class actually
        // attempts a real API call (only verifiable this way -- these are
        // third-party APIs this app can't reach in CI/testing to confirm
        // credentials genuinely work end to end). 8 of the 25 have no real
        // implementation at all: their testConnection() always returned
        // true regardless of what credentials were entered, until that was
        // fixed to honestly report 'not yet available' instead.
        return [
            'volvo' => ['name' => 'Volvo', 'description' => 'Volvo Heavy Equipment', 'status' => 'available'],
            'cat' => ['name' => 'Caterpillar', 'description' => 'Caterpillar Heavy Equipment', 'status' => 'available'],
            'komatsu' => ['name' => 'Komatsu', 'description' => 'Komatsu Heavy Equipment', 'status' => 'available'],
            'bell' => ['name' => 'Bell', 'description' => 'Bell Equipment ISO 15143-3 Fleet API', 'status' => 'available'],
            'hitachi' => ['name' => 'Hitachi', 'description' => 'Hitachi Construction Machinery', 'status' => 'available'],
            'john-deere' => ['name' => 'John Deere', 'description' => 'John Deere Equipment', 'status' => 'coming_soon'],
            'liebherr' => ['name' => 'Liebherr', 'description' => 'Liebherr Mining Equipment', 'status' => 'available'],
            'hyundai' => ['name' => 'Hyundai', 'description' => 'Hyundai Construction Equipment', 'status' => 'available'],
            'doosan' => ['name' => 'Doosan', 'description' => 'Doosan Heavy Equipment', 'status' => 'available'],
            'jcb' => ['name' => 'JCB', 'description' => 'JCB Construction Equipment', 'status' => 'available'],
            'case' => ['name' => 'CASE', 'description' => 'CASE Construction Equipment', 'status' => 'coming_soon'],
            'sany' => ['name' => 'Sany', 'description' => 'Sany Heavy Equipment', 'status' => 'available'],
            'xcmg' => ['name' => 'XCMG', 'description' => 'XCMG Construction Equipment', 'status' => 'available'],
            'kobelco' => ['name' => 'Kobelco', 'description' => 'Kobelco Construction Machinery', 'status' => 'available'],
            'new-holland' => ['name' => 'New Holland', 'description' => 'New Holland Equipment', 'status' => 'coming_soon'],
            'takeuchi' => ['name' => 'Takeuchi', 'description' => 'Takeuchi Compact Equipment', 'status' => 'coming_soon'],
            'kubota' => ['name' => 'Kubota', 'description' => 'Kubota Construction Equipment', 'status' => 'available'],
            'bobcat' => ['name' => 'Bobcat', 'description' => 'Bobcat Compact Equipment', 'status' => 'coming_soon'],
            'yanmar' => ['name' => 'Yanmar', 'description' => 'Yanmar Mini Excavators', 'status' => 'coming_soon'],
            'atlas-copco' => ['name' => 'Atlas Copco', 'description' => 'Atlas Copco Drilling Equipment', 'status' => 'coming_soon'],
            'sandvik' => ['name' => 'Sandvik', 'description' => 'Sandvik Mining Equipment', 'status' => 'coming_soon'],
            'epiroc' => ['name' => 'Epiroc', 'description' => 'Epiroc Drilling Equipment', 'status' => 'available'],
            'ctrack' => ['name' => 'C-Track', 'description' => 'C-Track GPS Tracking', 'status' => 'available'],
            'roundebult' => ['name' => 'Roundebult', 'description' => 'Roundebult Mining Machines', 'status' => 'available'],
            'kawasaki' => ['name' => 'Kawasaki', 'description' => 'Kawasaki Mining Equipment', 'status' => 'available'],
        ];
    }
}
