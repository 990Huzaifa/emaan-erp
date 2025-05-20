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

    public function sendTemplateMessage($to, $templateName, array $bodyParams = [], $mediaType = null, $mediaUrl = null, $filename = null, $language = 'en_US')
    {
        $url = "https://graph.facebook.com/v22.0/{$this->phoneNumberId}/messages";

        $components = [];

        // Header with media (e.g., document)
        if ($mediaType && $mediaUrl) {
            $headerParam = [
                'type' => $mediaType,
                $mediaType => [
                    'link' => $mediaUrl
                ]
            ];

            if ($mediaType === 'document' && $filename) {
                $headerParam[$mediaType]['filename'] = $filename;
            }

            $components[] = [
                'type' => 'header',
                'parameters' => [$headerParam]
            ];
        }

        // Body text parameters
        if (!empty($bodyParams)) {
            $bodyComponent = [
                'type' => 'body',
                'parameters' => array_map(function ($text) {
                    return [
                        'type' => 'text',
                        'text' => $text
                    ];
                }, $bodyParams)
            ];

            $components[] = $bodyComponent;
        }

        // Final payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components
            ]
        ];

        // Send the request using Laravel's HTTP client
        return Http::withToken($this->accessToken)
            ->post($url, $payload)
            ->json();
    }


}
