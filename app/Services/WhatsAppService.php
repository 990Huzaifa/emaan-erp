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
        $url = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";

        $components = [];

        // Add media header if applicable
        if ($mediaType && $mediaUrl) {
            $header = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => $mediaType,
                        $mediaType => [
                            'link' => $mediaUrl
                        ]
                    ]
                ]
            ];

            // Add filename for document
            if ($mediaType === 'document' && $filename) {
                $header['parameters'][0][$mediaType]['filename'] = $filename;
            }

            $components[] = $header;
        }

        // Add body text parameters
        if (!empty($bodyParams)) {
            $bodyComponent = [
                'type' => 'body',
                'parameters' => []
            ];

            foreach ($bodyParams as $param) {
                $bodyComponent['parameters'][] = [
                    'type' => 'text',
                    'text' => $param
                ];
            }

            $components[] = $bodyComponent;
        }

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

        return Http::withToken($this->accessToken)
            ->post($url, $payload)
            ->json();
    }

}
