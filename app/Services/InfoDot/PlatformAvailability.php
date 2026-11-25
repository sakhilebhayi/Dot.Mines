<?php

namespace App\Services\InfoDot;

use App\Support\ApiPayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whether InfoDot has switched Dot.Mines off (maintenance, unavailable) for a company (R-61 availability contract).
 *
 * AVAILABILITY ONLY. Dot.Mines decides its own access ('access' => 'own' in InfoDot's registry), so the `entitled`
 * and `status` fields of Mines' own row are not read, not stored and can never refuse anyone.
 *
 * Off unless config('ecosystem.availability.check') is true; while off, nothing here runs (no HTTP, no cache).
 *
 * A fresh answer is kept for 60 seconds, so a switch made at InfoDot holds within about a minute. Every clear answer is also
 * remembered for six hours. Anything other than a clear answer (InfoDot unreachable, secret missing or refused, unknown
 * company, a body that does not name this platform) is "unknown": the last clear answer under six hours old is used, so a
 * platform last told "unavailable" stays unavailable; with none, the caller carries on. Nothing in here throws.
 */
class PlatformAvailability
{
    private const ANSWER_SECONDS = 60;

    /** How long the last clear answer is used when InfoDot cannot be reached. */
    private const LAST_KNOWN_SECONDS = 21600;

    private const FAILURE_SECONDS = 60;

    public function enabled(): bool
    {
        return config('ecosystem.availability.check') === true;
    }

    /**
     * @return array{available: bool, message: ?string}|null null when InfoDot gave no clear answer and none is remembered
     */
    public function of(string $companyUuid): ?array
    {
        $key = 'infodot:availability:'.$this->platform().':'.$companyUuid;

        try {
            $cached = ApiPayload::assoc(Cache::get($key));
        } catch (Throwable) {
            $cached = [];
        }

        if (array_key_exists('answer', $cached)) {
            return $this->clean($cached['answer']);
        }

        $answer = $this->ask($companyUuid);

        try {
            if ($answer !== null) {
                Cache::put($key.':last', ['answer' => $answer, 'at' => now()->getTimestamp()], self::LAST_KNOWN_SECONDS);
            } else {
                $last = ApiPayload::assoc(Cache::get($key.':last'));
                $answer = now()->getTimestamp() - ApiPayload::int($last['at'] ?? 0) < self::LAST_KNOWN_SECONDS
                    ? $this->clean($last['answer'] ?? null)
                    : null;
            }

            Cache::put($key, ['answer' => $answer], $answer === null ? self::FAILURE_SECONDS : self::ANSWER_SECONDS);
        } catch (Throwable) {
            // No cache, no memory of the answer: the next request asks again.
        }

        return $answer;
    }

    private function platform(): string
    {
        return ApiPayload::str(config('ecosystem.infodot.platform'), 'mines') ?: 'mines';
    }

    /**
     * @return array{available: bool, message: ?string}|null
     */
    private function clean(mixed $answer): ?array
    {
        $fields = ApiPayload::assoc($answer);

        if (! isset($fields['available']) || ! is_bool($fields['available'])) {
            return null;
        }

        return ['available' => $fields['available'], 'message' => isset($fields['message']) && is_string($fields['message']) ? $fields['message'] : null];
    }

    /** InfoDot's read endpoint, on the same host this app redeems sign-in codes at. */
    private function url(string $companyUuid): ?string
    {
        $configured = ApiPayload::str(config('ecosystem.availability.url'));

        if ($configured !== '') {
            return str_replace('{uuid}', rawurlencode($companyUuid), $configured);
        }

        $redeem = parse_url(ApiPayload::str(config('ecosystem.infodot.redeem_url')));

        if ($redeem === false || ! isset($redeem['scheme'], $redeem['host'])) {
            return null;
        }

        return $redeem['scheme'].'://'.$redeem['host'].(isset($redeem['port']) ? ':'.$redeem['port'] : '')
            .'/api/ecosystem/companies/'.rawurlencode($companyUuid).'/platforms';
    }

    /**
     * @return array{available: bool, message: ?string}|null
     */
    private function ask(string $companyUuid): ?array
    {
        $bearer = ApiPayload::str(config('ecosystem.infodot.secret'));
        $url = $this->url($companyUuid);

        if ($bearer === '' || $url === null) {
            return $this->unknown('not configured');
        }

        try {
            $response = Http::withToken($bearer)
                ->acceptJson()
                ->withHeaders(['X-Dot-Platform' => $this->platform()])
                ->timeout(max(1, ApiPayload::int(config('ecosystem.availability.timeout'), 3)))
                ->get($url, ['platform' => $this->platform()]);

            if (! $response->successful()) {
                return $this->unknown('status '.$response->status());
            }

            $body = ApiPayload::assoc($response->json());
        } catch (Throwable $e) {
            return $this->unknown(class_basename($e));
        }

        $company = ApiPayload::assoc($body['company'] ?? null);

        if (! isset($body['platforms']) || ! is_array($body['platforms']) || ($company['uuid'] ?? null) !== $companyUuid) {
            return $this->unknown('malformed body');
        }

        foreach (ApiPayload::rows($body['platforms']) as $row) {
            if (($row['key'] ?? null) === $this->platform()) {
                // An InfoDot from before contract 2 gives no `available`, which is not a switch-off. `entitled` is never read.
                $message = isset($row['message']) && is_string($row['message']) ? $row['message'] : '';

                return [
                    'available' => ($row['available'] ?? true) !== false,
                    'message' => $message !== '' ? mb_substr($message, 0, 400) : null,
                ];
            }
        }

        return $this->unknown('own row missing');
    }

    /** Logged where the failure is stored, so once per cache window. Never the secret, never a person. */
    private function unknown(string $why): null
    {
        Log::warning('InfoDot availability check gave no answer; carrying on.', ['platform' => $this->platform(), 'reason' => $why]);

        return null;
    }
}
