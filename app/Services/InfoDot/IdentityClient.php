<?php

namespace App\Services\InfoDot;

use App\Support\ApiPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks InfoDot (the identity hub) to prove a handoff token.
 *
 * Every Dot app has its own database, so the single-use token InfoDot minted is not in ours. It is sent to InfoDot's
 * redeem endpoint, server to server, with this platform's own secret as the bearer. The token never appears in a URL
 * and is never logged here.
 *
 * Returns the identity array on success, null when InfoDot refuses the token (4xx), and throws InfoDotUnavailable when
 * InfoDot cannot be reached or errors (so the caller can say "try again" instead of "forbidden").
 */
class IdentityClient
{
    public function enabled(): bool
    {
        return ApiPayload::str(config('ecosystem.infodot.secret')) !== '' && ApiPayload::str(config('ecosystem.infodot.redeem_url')) !== '';
    }

    /**
     * @return array<string, mixed>|null
     */
    public function redeem(string $token): ?array
    {
        try {
            $response = Http::withToken(ApiPayload::str(config('ecosystem.infodot.secret')))
                ->acceptJson()
                ->timeout(ApiPayload::int(config('ecosystem.infodot.timeout'), 5))
                ->post(ApiPayload::str(config('ecosystem.infodot.redeem_url')), [
                    'token' => $token,
                    'platform' => ApiPayload::str(config('ecosystem.infodot.platform')),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('InfoDot redeem unreachable.', ['error' => class_basename($e)]);

            throw new InfoDotUnavailable('InfoDot could not be reached.', 0, $e);
        }

        if ($response->serverError() || $response->status() === 429) {
            Log::warning('InfoDot redeem failed.', ['status' => $response->status()]);

            throw new InfoDotUnavailable('InfoDot is not available.');
        }

        if (! $response->successful()) {
            // 401 means this platform's secret is wrong: an operator problem, worth shouting about.
            Log::log($response->status() === 401 ? 'error' : 'info', 'InfoDot refused a handoff token.', ['status' => $response->status()]);

            return null;
        }

        $data = ApiPayload::assoc($response->json());
        $user = ApiPayload::assoc($data['user'] ?? null);

        return isset($user['email']) ? $data : null;
    }
}
