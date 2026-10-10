<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ChooseTomorrowNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{title: string, body: string, url: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => '¿Elegimos las 3 de mañana?',
            'body' => 'Podés elegir hasta 3 cosas para mañana, o dejarlo vacío.',
            'url' => '/today/tomorrow',
        ];
    }
}
