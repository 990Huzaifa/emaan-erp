<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected $phoneNumberId;
    protected $accessToken;

    public function __construct()
    {
        $this->phoneNumberId = config('whatsapp.phone_number_id','732336233285690');
        $this->accessToken = config('whatsapp.access_token','EAAR1Rq25RVcBOxqBvzDEXF844jUZC5ATC0QTDJnJWpbnb1SE9RZBZAImynjp0fl0BAafRgOuGYsm7CJsYiljYSsDD5CpjwLfgrcf4Aph3xzOEK6ojPu7R3t7qkFS9sduPMuG1ZAXsBE0hCZCm6Fn535G0KxR6aj7PubQ2m4AXYdbcmtdHoiBstMmfZCnMUjuoYzwZDZD');
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
