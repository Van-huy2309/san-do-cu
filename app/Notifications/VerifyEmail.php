<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmail extends Notification
{
    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mã xác thực Relic: ' . $this->code)
            ->greeting('Xin chào ' . ($notifiable->name ?? '') . '!')
            ->line('Mã xác thực email Relic của bạn là:')
            ->line('**' . $this->code . '**')
            ->line('Nhập mã này trên trang Relic (máy tính hoặc điện thoại đang mở website). Mã hết hạn sau 15 phút.')
            ->line('Không cần bấm link localhost trên điện thoại.')
            ->line('Nếu bạn không đăng ký Relic, hãy bỏ qua email này.');
    }
}
