<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Aviso de la campanita: título, texto y a dónde lleva al hacer clic. */
class Aviso extends Notification
{
    public function __construct(public string $titulo, public string $mensaje, public ?string $url = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['titulo' => $this->titulo, 'mensaje' => $this->mensaje, 'url' => $this->url];
    }
}
