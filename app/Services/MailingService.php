<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;

class ExternalMailService
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

        $response = Http::acceptJson()
            ->timeout((int) config('services.external_mail.request_timeout', 30))
            ->connectTimeout((int) config('services.external_mail.connect_timeout', 10))
            ->retry(2, 300)
            ->withHeaders([
                config('services.external_mail.api_key', 'x-api-key') => config('services.external_mail.api_key'),
            ])
            ->post(config('services.external_mail.endpoint'), $payload);

        $response->throw();

        return [
            'success' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->json() ?: ['raw' => $response->body()],
        ];
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