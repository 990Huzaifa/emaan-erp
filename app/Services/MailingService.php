<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

class MailingService
{
    public function sendView(
        string|array $to,
        string $subject,
        string $view,
        array $data = [],
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?array $smtpOverride = null,
    ): array {
        if (! View::exists($view)) {
            throw new InvalidArgumentException("Mail view [{$view}] not found.");
        }

        $html = view($view, $data)->render();

        $recipients = is_array($to) ? $to : [$to];
        $results = [];

        foreach ($recipients as $email) {
            $results[] = $this->sendHtml(
                to: $email,
                subject: $subject,
                html: $html,
                fromEmail: $fromEmail,
                fromName: $fromName,
                smtpOverride: $smtpOverride,
            );
        }

        return [
            'success' => collect($results)->every(fn ($item) => $item['success'] === true),
            'results' => $results,
        ];
    }

    public function sendHtml(
        string $to,
        string $subject,
        string $html,
        ?string $fromEmail = null,
        ?string $fromName = null,
        ?array $smtpOverride = null,
    ): array {
        $this->validateConfig();

        $payload = [
            'smtp' => $smtpOverride ?: [
                'host' => config('services.external_mail.smtp.host'),
                'port' => (int) config('services.external_mail.smtp.port'),
                'username' => config('services.external_mail.smtp.username'),
                'password' => config('services.external_mail.smtp.password'),
                'security' => config('services.external_mail.smtp.security', 'starttls'),
                'timeout' => (int) config('services.external_mail.smtp.timeout', 30),
            ],
            'email' => [
                'from_email' => $fromEmail ?: config('services.external_mail.from_email'),
                'from_name' => $fromName ?: config('services.external_mail.from_name'),
                'to_email' => $to,
                'subject' => $subject,
                'body' => $html,
                'body_type' => 'html',
            ],
        ];

            $url = 'http://72.60.114.133:8005/send-email';

            $jsonPayload = json_encode($payload);

            // API key for authentication
            $apiKey = 'ziiklzezflcaJJmoyrsev2aiqjlne';

            // cURL setup
            $ch = curl_init($url);

            // Set cURL options
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
            ]);

            // Execute the cURL request
            $response = curl_exec($ch);

            // Close the cURL session
            curl_close($ch);

            // Decode the response
            $response = json_decode($response, true);


        if (isset($response['ok']) && $response['ok'] === true) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            // Handle error responses from FastAPI
            return ['success' => false, 'error' => 'Failed to send email', 'response' => $response];
        }
    }

    protected function validateConfig(): void
    {
        $required = [
            'services.external_mail.endpoint',
            'services.external_mail.api_key',
            'services.external_mail.from_email',
            'services.external_mail.smtp.host',
            'services.external_mail.smtp.port',
            'services.external_mail.smtp.username',
            'services.external_mail.smtp.password',
        ];

        foreach ($required as $key) {
            if (blank(config($key))) {
                throw new InvalidArgumentException("Missing config value: {$key}");
            }
        }
    }
}