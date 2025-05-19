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

        try {
            // Media header
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

                // Filename for documents
                if ($mediaType === 'document' && $filename) {
                    $header['parameters'][0][$mediaType]['filename'] = $filename;
                }

                $components[] = $header;
            }

            // Body parameters
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

            $response = Http::withToken($this->accessToken)->post($url, $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response->json(),
                    'status' => $response->status()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTrace()
            ];
        }
    }


}
