<?php

namespace App\Http\Middleware;

use App\Services\InfoDot\PlatformAvailability;
use App\Support\ApiPayload;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-61: while InfoDot says Dot.Mines is not available (maintenance), a signed-in person's WEB screens answer 503.
 *
 * OFF unless config('ecosystem.availability.check') is true: with it off this returns $next before touching the route,
 * the session, the cache or the network, so merging it changes nothing. It reads `available` only. Dot.Mines decides its
 * own access, so `entitled` is never consulted and nobody is refused for what their company holds.
 *
 * Only the interactive signed-in web area is guarded: routes behind the auth middleware, plus Livewire's update and
 * upload endpoints. Never guarded, whatever InfoDot says: the sanctum/device/telemetry API (routes/api*.php), the
 * Paystack webhook, signed URLs, the guardian-token route, health checks, sign-in, sign-out, 2FA, the InfoDot handoff,
 * the account-security pages, the data-subject (gdpr.*) routes, downloads, broadcasting auth and every public page.
 * A team with no InfoDot company, and a personal team, is never asked about and never blocked.
 */
class EnsurePlatformAvailable
{
    /** Signed-in routes that stay reachable by name (patterns allowed). */
    private const ALWAYS_OPEN = [
        'logout', 'current-team.update', 'ecosystem.auth',
        // Account security and sign-in adjuncts: 2FA, password confirmation, profile (where admin 2FA is enrolled), verification.
        'profile.show', 'two-factor.*', 'password.*', 'user-password.update', 'user-profile-information.update', 'verification.*',
        'team-invitations.accept',
        // Data-subject rights stay reachable.
        'gdpr.*',
        // Downloads: reading and exporting a person's own records is never switched off.
        'reports.signed-download', 'mineplans.signed-download', 'operators.documents.download',
    ];

    /** Endpoints with no auth middleware of their own that carry a signed-in person's screen actions. */
    private const SIGNED_IN_ENDPOINTS = ['livewire/update', 'livewire/upload-file', 'livewire/preview-file/*'];

    /** Pages a Livewire component may sit on and still work while Mines is unavailable (account security, data rights). */
    private const OPEN_LIVEWIRE_PATHS = ['user/*', 'gdpr', 'gdpr/*', 'email/*', 'team-invitations/*'];

    public function __construct(private PlatformAvailability $availability) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->availability->enabled() || ! $this->guarded($request)) {
            return $next($request);
        }

        $user = $request->user();
        $team = $user?->currentTeam;

        if ($team === null) {
            return $next($request);
        }

        // A personal team, or any team with no InfoDot company, is never asked about.
        $companyUuid = ApiPayload::str($team->infodot_team_uuid);

        if ((bool) $team->getAttributeValue('personal_team') || trim($companyUuid) === '') {
            return $next($request);
        }

        $answer = $this->availability->of($companyUuid);

        if ($answer !== null && ! $answer['available']) {
            return $this->unavailable($request, $answer['message']);
        }

        return $next($request);
    }

    private function guarded(Request $request): bool
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return false;
        }

        $name = $route->getName();

        if (is_string($name) && $this->matches($name, self::ALWAYS_OPEN)) {
            return false;
        }

        if ($request->is(...self::SIGNED_IN_ENDPOINTS)) {
            return $request->user() !== null && ! $this->livewireOnOpenPage($request);
        }

        /** @psalm-suppress MixedAssignment */
        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && ($middleware === 'auth' || str_starts_with($middleware, 'auth:') || str_starts_with($middleware, Authenticate::class))) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $patterns */
    private function matches(string $name, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $name || (str_ends_with($pattern, '*') && str_starts_with($name, substr($pattern, 0, -1)))) {
                return true;
            }
        }

        return false;
    }

    /** True only when every Livewire component in the request sits on an open page (read from the signed snapshot path). */
    private function livewireOnOpenPage(Request $request): bool
    {
        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        /** @psalm-suppress MixedAssignment */
        foreach ($components as $component) {
            $snapshot = json_decode(ApiPayload::str(ApiPayload::assoc($component)['snapshot'] ?? null), true);
            $path = ApiPayload::str(ApiPayload::assoc(ApiPayload::assoc($snapshot)['memo'] ?? null)['path'] ?? null);

            if ($path === '' || ! Str::is(self::OPEN_LIVEWIRE_PATHS, trim($path, '/'))) {
                return false;
            }
        }

        return true;
    }

    private function unavailable(Request $request, ?string $message): Response
    {
        $message = $message ?? 'Dot.Mines is not available right now.';

        if ($request->expectsJson() || $request->is('livewire/*')) {
            return response()->json(['message' => $message, 'reason' => 'platform_unavailable'], 503)->header('Retry-After', '60');
        }

        return response()->view('errors.platform-unavailable', ['message' => $message], 503)
            ->header('Cache-Control', 'no-store, private')->header('Retry-After', '60');
    }
}
