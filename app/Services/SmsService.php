<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    public function send(string $phone, string $message): bool
    {
        $driver = config('services.sms.driver', 'log');

        return match ($driver) {
            'twilio' => $this->twilio($phone, $message),
            'esms' => $this->esms($phone, $message),
            default => $this->log($phone, $message),
        };
    }

    private function log(string $phone, string $message): bool
    {
        Log::info('OTP SMS (log driver)', compact('phone', 'message'));

        return true;
    }

    private function twilio(string $phone, string $message): bool
    {
        $sid = config('services.sms.twilio_sid');
        $token = config('services.sms.twilio_token');
        $from = config('services.sms.twilio_from');
        if (! $sid || ! $token || ! $from) {
            return $this->log($phone, $message);
        }

        $to = str_starts_with($phone, '0') ? '+84' . substr($phone, 1) : $phone;
        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => $from,
                'To' => $to,
                'Body' => $message,
            ]);

        if (! $response->successful()) {
            Log::error('Twilio SMS failed', ['body' => $response->body()]);

            return false;
        }

        return true;
    }

    private function esms(string $phone, string $message): bool
    {
        $key = config('services.sms.esms_api_key');
        $secret = config('services.sms.esms_secret');
        if (! $key || ! $secret) {
            return $this->log($phone, $message);
        }

        $response = Http::get('https://rest.esms.vn/MainService.svc/json/SendMultipleMessage_V4_get', [
            'ApiKey' => $key,
            'SecretKey' => $secret,
            'Phone' => $phone,
            'Content' => $message,
            'SmsType' => 2,
        ]);

        return $response->successful();
    }
}
