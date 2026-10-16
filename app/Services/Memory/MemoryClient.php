<?php

namespace App\Services\Memory;

use App\Services\Memory\Exceptions\MemoryCredentialRejected;
use App\Services\Memory\Exceptions\MemoryRefused;
use App\Services\Memory\Exceptions\MemoryUnavailable;
use App\Support\ApiPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Typed client over Dot.Memory's intelligence-loop API, verified against
 * its code on 2026-10-16. Every failure is a typed exception whose
 * message never contains the token.
 */
final class MemoryClient
{
    public const PLATFORM = 'dot-mines';

    private const STAGE_PATHS = ['event' => 'intelligence/events', 'decision' => 'intelligence/decisions', 'action' => 'intelligence/actions', 'outcome' => 'intelligence/outcomes'];

    public function __construct(private readonly string $baseUrl, private readonly string $token, private readonly int $timeout = 10) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function record(string $stage, array $payload): array
    {
        $path = self::STAGE_PATHS[$stage] ?? throw new InvalidArgumentException("Unknown loop stage '{$stage}'.");

        $request = Http::withToken($this->token)
            ->acceptJson()
            ->withHeaders(['X-Requested-With' => 'DotMines'])
            ->withOptions(['allow_redirects' => false])
            ->connectTimeout(min(5, $this->timeout))
            ->timeout($this->timeout);

        try {
            $response = $request->post(rtrim($this->baseUrl, '/').'/api/'.$path, $payload + ['platform' => self::PLATFORM]);
        } catch (ConnectionException $e) {
            throw new MemoryUnavailable('Dot.Memory could not be reached: '.str_replace($this->token, '[redacted]', $e->getMessage()));
        }

        return $this->data($response);
    }

    /** @return array<string, mixed> */
    private function data(Response $response): array
    {
        $status = $response->status();

        if ($status === 401 || $status === 403) {
            throw new MemoryCredentialRejected('Dot.Memory rejected the credential (HTTP '.$status.').', $status);
        }

        if ($status === 422) {
            throw new MemoryRefused($this->messages($response));
        }

        if (! $response->successful()) {
            throw new MemoryUnavailable('Dot.Memory returned HTTP '.$status.'.', $status);
        }

        $json = ApiPayload::assoc($response->json());
        $data = ApiPayload::assoc($json['data'] ?? null);

        return $data !== [] ? $data : $json;
    }

    /** @return list<string> */
    private function messages(Response $response): array
    {
        $messages = [];

        /** @psalm-suppress MixedAssignment -- validation errors are a map of field => messages; strings() filters each value */
        foreach (ApiPayload::assoc($response->json('errors')) as $field) {
            foreach (ApiPayload::strings($field) as $message) {
                $messages[] = $message;
            }
        }

        return $messages !== [] ? $messages : [ApiPayload::str($response->json('message'), 'record refused')];
    }
}
