<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TurnstileService
{
    public function enabled(): bool
    {
        return (bool) config('turnstile.enabled');
    }

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        $secret = trim((string) config('turnstile.secret_key'));

        if ($secret === '') {
            Log::critical('Turnstile está habilitado sin una clave secreta configurada.');

            return false;
        }

        $payload = [
            'secret' => $secret,
            'response' => $token,
            'idempotency_key' => (string) Str::uuid(),
        ];

        if (filled($remoteIp)) {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout((int) config('turnstile.timeout_seconds', 7))
                ->retry(2, 200, throw: false)
                ->post((string) config('turnstile.verify_url'), $payload);
        } catch (Throwable $exception) {
            Log::warning('No fue posible validar Turnstile con Cloudflare.', [
                'exception' => $exception::class,
            ]);

            return false;
        }

        return $this->responseIsValid($response);
    }

    private function responseIsValid(Response $response): bool
    {
        $result = $response->json();

        if (! $response->successful() || ! is_array($result) || ($result['success'] ?? false) !== true) {
            Log::warning('Cloudflare rechazó la validación de Turnstile.', [
                'status' => $response->status(),
                'error_codes' => is_array($result) ? ($result['error-codes'] ?? []) : [],
            ]);

            return false;
        }

        $expectedAction = trim((string) config('turnstile.expected_action'));
        $receivedAction = (string) ($result['action'] ?? '');

        if ($expectedAction !== '' && ! hash_equals($expectedAction, $receivedAction)) {
            Log::warning('Turnstile devolvió una acción inesperada.', [
                'received_action' => $receivedAction,
            ]);

            return false;
        }

        $expectedHostname = strtolower(trim((string) config('turnstile.expected_hostname')));
        $receivedHostname = strtolower((string) ($result['hostname'] ?? ''));

        if ($expectedHostname !== '' && ! hash_equals($expectedHostname, $receivedHostname)) {
            Log::warning('Turnstile devolvió un dominio inesperado.', [
                'received_hostname' => $receivedHostname,
            ]);

            return false;
        }

        return true;
    }
}
