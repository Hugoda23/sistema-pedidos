<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Aviso de la campanita: título, texto, a dónde lleva y, si aplica,
 * botones de WhatsApp ([{texto, url}]) para avisarle a otra persona.
 */
class Aviso extends Notification
{
    public function __construct(
        public string $titulo,
        public string $mensaje,
        public ?string $url = null,
        public array $whatsapp = [],
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['titulo' => $this->titulo, 'mensaje' => $this->mensaje, 'url' => $this->url, 'whatsapp' => $this->whatsapp];
    }
}
