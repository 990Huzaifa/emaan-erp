<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected $phoneNumberId;
    protected $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = env('WHATSAPP_PHONE_NUMBER_ID');
        $this->accessToken = env('WHATSAPP_ACCESS_TOKEN');
    }

    public function sendTextMessage($to, $message)
    {
        $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

        return Http::withToken($this->accessToken)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ])->json();
    }

    public function sendDocument($to, $documentUrl, $filename)
    {
        $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

        return Http::withToken($this->accessToken)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
            ],
        ])->json();
    }
}
