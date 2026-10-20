<?php

namespace App\Jobs;

use App\Models\MensajeWhatsapp;
use App\Notifications\Aviso;
use App\Services\WhatsApp\WhatsAppCliente;
use App\Services\WhatsApp\WhatsAppError;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class EnviarWhatsapp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(public int $mensajeId) {}

    /** Un mensaje a la vez por número: mandar ráfagas es lo que hace que WhatsApp bloquee. */
    public function middleware(): array
    {
        $m = MensajeWhatsapp::find($this->mensajeId);

        return $m ? [(new WithoutOverlapping("whatsapp-{$m->remitente_id}"))->releaseAfter(5)->expireAfter(120)] : [];
    }

    public function handle(WhatsAppCliente $wa): void
    {
        $m = MensajeWhatsapp::find($this->mensajeId);
        if (! $m || $m->estado === 'enviado') {
            return;
        }

        $m->increment('intentos');

        try {
            $wa->enviar($m->remitente_id, $m->telefono, $m->mensaje,
                $m->adjunto ? ['ruta' => $m->adjunto, 'nombre' => $m->adjunto_nombre ?? basename($m->adjunto)] : null);
        } catch (WhatsAppError $e) {
            // Sin vincular o número malo: reintentar no sirve; queda para reintentar a mano.
            if ($e->permanente || $e->noVinculado || $this->attempts() >= $this->tries) {
                $this->fallo($m, $e->getMessage());

                return;
            }
            $m->update(['error' => $e->getMessage()]);
            $this->release($this->backoff[$this->attempts() - 1] ?? 120);

            return;
        }

        $m->update(['estado' => 'enviado', 'error' => null, 'enviado_en' => now()]);

        // Espaciar los envíos del mismo número.
        if (! app()->runningUnitTests()) {
            sleep(random_int(2, 5));
        }
    }

    private function fallo(MensajeWhatsapp $m, string $error): void
    {
        $m->update(['estado' => 'fallido', 'error' => $error]);

        $m->remitente->notify(new Aviso(
            'No se pudo enviar un WhatsApp',
            "A {$m->telefono}: {$error}",
            '/whatsapp',
        ));
    }
}
