<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;
use NotificationChannels\WebPush\WebPushChannel;

class ContractExpiringNotification extends Notification
{
    use Queueable;

    public $employee;
    public $daysRemaining;

    /**
     * Create a new notification instance.
     */
    public function __construct($employee, $daysRemaining)
    {
        $this->employee = $employee;
        $this->daysRemaining = $daysRemaining;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush($notifiable, $notification)
    {
        return (new WebPushMessage)
            ->title('Kontrak Kerja Segera Habis')
            ->icon('/favicon.ico')
            ->body("Kontrak atas nama {$this->employee->name} akan habis dalam {$this->daysRemaining} hari.")
            ->action('Lihat Detail', 'view_contract')
            ->data(['url' => url('/admin/employees/contracts')]);
    }
}
