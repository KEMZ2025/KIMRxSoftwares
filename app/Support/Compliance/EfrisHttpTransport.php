<?php

namespace App\Support\Compliance;

use App\Models\ClientSetting;
use App\Models\EfrisDocument;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class EfrisHttpTransport
{
    public function submit(EfrisDocument $document, array $payload): array
    {
        $settings = $this->settingsForDocument($document);
        $endpoint = $this->endpointForDocument($settings, $document);
        $token = $this->resolveToken($settings, $payload);

        $request = Http::acceptJson()
            ->asJson()
            ->timeout(max(1, (int) config('efris.timeout_seconds', 20)))
            ->connectTimeout(max(1, (int) config('efris.connect_timeout_seconds', 10)));

        if ($token) {
            $request = $request->withToken($token);
        }

        $headers = array_filter([
            'X-EFRIS-Client-Id' => $settings->efris_client_id,
            'X-EFRIS-Action' => $document->next_action,
        ], fn ($value) => filled($value));

        if ($headers !== []) {
            $request = $request->withHeaders($headers);
        }

        $response = $request->post($endpoint, $this->submissionPayload($document, $payload));
        $response->throw();

        $body = $response->json();
        if (!is_array($body)) {
            $body = ['raw_body' => $response->body()];
        }

        return [
            'transport' => 'http',
            'environment' => Arr::get($payload, 'environment', $document->environment ?: 'sandbox'),
            'status' => $this->resolveStatus($response->status(), $body),
            'action' => $document->next_action,
            'message' => $this->resolveMessage($body, $response->status()),
            'tracking_number' => $this->extractValue($body, [
                'tracking_number',
                'trackingNo',
                'reference_number',
                'invoice_number',
                'data.tracking_number',
                'data.reference_number',
                'data.invoice_number',
                'result.tracking_number',
                'result.reference_number',
                'result.invoice_number',
            ]),
            'processed_at' => now()->toIso8601String(),
            'http_status' => $response->status(),
            'request_url' => $endpoint,
            'response_body' => $body,
        ];
    }

    private function settingsForDocument(EfrisDocument $document): ClientSetting
    {
        return ClientSetting::query()->firstOrCreate(
            ['client_id' => $document->client_id],
            ['business_mode' => 'both']
        );
    }

    private function endpointForDocument(ClientSetting $settings, EfrisDocument $document): string
    {
        $endpoint = $document->next_action === 'submit_reversal'
            ? $settings->efris_reversal_url
            : $settings->efris_submission_url;

        if (blank($endpoint)) {
            throw new RuntimeException(
                $document->next_action === 'submit_reversal'
                    ? 'Configure the EFRIS reversal endpoint URL in Settings before processing reversal documents.'
                    : 'Configure the EFRIS submission endpoint URL in Settings before processing sale documents.'
            );
        }

        return $endpoint;
    }

    private function resolveToken(ClientSetting $settings, array $payload): ?string
    {
        if (blank($settings->efris_auth_url)) {
            return null;
        }

        $response = Http::acceptJson()
            ->asJson()
            ->timeout(max(1, (int) config('efris.timeout_seconds', 20)))
            ->connectTimeout(max(1, (int) config('efris.connect_timeout_seconds', 10)))
            ->post($settings->efris_auth_url, array_filter([
                'username' => $settings->efris_username,
                'password' => $settings->efris_password,
                'client_id' => $settings->efris_client_id,
                'client_secret' => $settings->efris_client_secret,
                'environment' => Arr::get($payload, 'environment', $settings->efris_environment ?: 'sandbox'),
                'tin' => Arr::get($payload, 'tin'),
            ], fn ($value) => filled($value)));

        $response->throw();

        $body = $response->json();
        if (!is_array($body)) {
            throw new RuntimeException('EFRIS authentication did not return a valid JSON response.');
        }

        $token = $this->extractValue($body, [
            'access_token',
            'token',
            'data.access_token',
            'data.token',
            'result.access_token',
            'result.token',
            'response.access_token',
        ]);

        if (blank($token)) {
            throw new RuntimeException('EFRIS authentication did not return an access token.');
        }

        return $token;
    }

    private function submissionPayload(EfrisDocument $document, array $payload): array
    {
        return $payload + [
            'connector_action' => $document->next_action,
            'reference_number' => $document->reference_number,
            'document_kind' => $document->document_kind,
        ];
    }

    private function resolveStatus(int $httpStatus, array $body): string
    {
        $remoteStatus = strtolower((string) ($this->extractValue($body, [
            'status',
            'data.status',
            'result.status',
            'response.status',
            'messageCode',
        ]) ?? ''));

        if (in_array($remoteStatus, ['failed', 'error', 'rejected', 'invalid'], true)) {
            return 'failed';
        }

        if ($httpStatus === 202 || in_array($remoteStatus, ['queued', 'submitted', 'pending', 'processing'], true)) {
            return 'submitted';
        }

        return 'accepted';
    }

    private function resolveMessage(array $body, int $httpStatus): string
    {
        return (string) ($this->extractValue($body, [
            'message',
            'description',
            'data.message',
            'result.message',
            'response.message',
        ]) ?? ('EFRIS HTTP request completed with status ' . $httpStatus . '.'));
    }

    private function extractValue(array $payload, array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);

            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
