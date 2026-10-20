<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Habla con el servicio de Node que tiene abierta una sesión de WhatsApp por usuario. */
class WhatsAppCliente
{
    private function http(int $timeout = 10): PendingRequest
    {
        return Http::baseUrl(config('services.whatsapp.url'))
            ->withToken(config('services.whatsapp.token'))
            ->acceptJson()
            ->timeout($timeout);
    }

    /** @return array{status:string, qr:?string, numero:?string, error:?string} */
    public function estado(int $userId): array
    {
        try {
            return $this->http()->get("/sessions/{$userId}")->throw()->json();
        } catch (ConnectionException) {
            return ['status' => 'sin_servicio', 'qr' => null, 'numero' => null, 'error' => 'El servicio de WhatsApp no responde.'];
        }
    }

    /** @return array<string, array> estado por id de usuario de las sesiones abiertas */
    public function todas(): array
    {
        try {
            return $this->http()->get('/sessions')->throw()->json() ?: [];
        } catch (ConnectionException) {
            return [];
        }
    }

    public function iniciar(int $userId): array
    {
        return $this->http()->post("/sessions/{$userId}/start")->throw()->json();
    }

    public function cerrar(int $userId): array
    {
        return $this->http(30)->post("/sessions/{$userId}/logout")->throw()->json();
    }

    /** @throws WhatsAppError */
    public function enviar(int $userId, string $telefono, string $mensaje): void
    {
        try {
            $res = $this->http(40)->post("/sessions/{$userId}/send", ['to' => $telefono, 'body' => $mensaje]);
        } catch (ConnectionException $e) {
            throw new WhatsAppError('El servicio de WhatsApp no responde.');
        }

        if ($res->successful()) {
            return;
        }

        throw new WhatsAppError(
            $res->json('message') ?? "Error {$res->status()} del servicio de WhatsApp.",
            permanente: (bool) $res->json('permanente'),
            noVinculado: $res->status() === 409,
        );
    }
}
