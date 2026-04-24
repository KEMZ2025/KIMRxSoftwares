<?php

namespace App\Support\Compliance;

use App\Models\ClientSetting;
use App\Models\EfrisDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class EfrisSyncProcessor
{
    public function processClient(int $clientId, string $scope = 'ready', ?int $limit = null): array
    {
        $documents = $this->queryForScope($scope)
            ->where('client_id', $clientId)
            ->limit($limit ?? $this->defaultLimit())
            ->get();

        return $this->processDocuments($documents);
    }

    public function processAll(string $scope = 'ready', ?int $limit = null): array
    {
        $documents = $this->queryForScope($scope)
            ->limit($limit ?? $this->defaultLimit())
            ->get();

        return $this->processDocuments($documents);
    }

    public function process(EfrisDocument $document): EfrisDocument
    {
        $document = $document->fresh() ?? $document;
        $document->loadMissing(['sale.customer', 'sale.items.product', 'sale.items.batch']);

        if ($document->next_action === 'complete') {
            return $document;
        }

        $document->attempt_count = (int) $document->attempt_count + 1;
        $document->last_attempt_at = now();
        $document->save();

        $payload = is_array($document->payload_snapshot) ? $document->payload_snapshot : [];
        $validationErrors = $this->validationErrors($document, $payload);

        if ($validationErrors !== []) {
            return $this->markFailed($document, implode(' ', $validationErrors), [
                'transport' => config('efris.transport', 'simulate'),
                'validation_errors' => $validationErrors,
            ]);
        }

        try {
            $result = $this->dispatch($document, $payload);
        } catch (\Throwable $exception) {
            return $this->markFailed($document, $exception->getMessage(), [
                'transport' => config('efris.transport', 'simulate'),
                'exception' => $exception::class,
            ]);
        }

        $status = (string) ($result['status'] ?? 'accepted');

        if ($status === 'failed') {
            return $this->markFailed(
                $document,
                (string) ($result['message'] ?? 'EFRIS processing failed.'),
                $result
            );
        }

        $document->status = $status;
        $document->submitted_at = $document->submitted_at ?: now();
        $document->accepted_at = $status === 'accepted' ? now() : null;
        $document->last_error_message = null;
        $document->response_snapshot = $result;

        if ($status === 'accepted') {
            $document->next_action = 'complete';
        }

        $document->save();

        return $document->fresh();
    }

    private function processDocuments(Collection $documents): array
    {
        $summary = [
            'processed' => 0,
            'accepted' => 0,
            'submitted' => 0,
            'failed' => 0,
        ];

        foreach ($documents as $document) {
            $processedDocument = $this->process($document);

            $summary['processed']++;

            if ($processedDocument->status === 'accepted') {
                $summary['accepted']++;
            } elseif ($processedDocument->status === 'submitted') {
                $summary['submitted']++;
            } else {
                $summary['failed']++;
            }
        }

        return $summary;
    }

    private function queryForScope(string $scope): Builder
    {
        $query = EfrisDocument::query()
            ->where('next_action', '!=', 'complete')
            ->orderByRaw("case when status = 'failed' then 0 else 1 end")
            ->orderBy('prepared_at')
            ->orderBy('id');

        return match ($scope) {
            'failed' => $query->where('status', 'failed'),
            'all' => $query->whereIn('status', ['ready', 'failed']),
            default => $query->where('status', 'ready'),
        };
    }

    private function validationErrors(EfrisDocument $document, array $payload): array
    {
        $errors = [];
        $settings = $this->settingsForDocument($document);
        $transport = EfrisPreflightChecklist::transportMode($settings);

        if (!$document->sale) {
            $errors[] = 'The linked sale record is missing, so this EFRIS document cannot be processed.';
        }

        if (blank(Arr::get($payload, 'tin'))) {
            $errors[] = 'Add the URA TIN in Settings before processing EFRIS documents.';
        }

        if (blank(Arr::get($payload, 'legal_name'))) {
            $errors[] = 'Add the legal registered name in Settings before processing EFRIS documents.';
        }

        if (blank(Arr::get($payload, 'business_name'))) {
            $errors[] = 'Add the trading / display name in Settings before processing EFRIS documents.';
        }

        if (blank(Arr::get($payload, 'branch_code'))) {
            $errors[] = 'Add the EFRIS branch code in Settings before processing EFRIS documents.';
        }

        if (blank($document->reference_number)) {
            $errors[] = 'This EFRIS document is missing the invoice or receipt reference number.';
        }

        $items = Arr::get($payload, 'items');
        if (!is_array($items) || $items === []) {
            $errors[] = 'This EFRIS document has no sale items prepared for submission.';
        }

        if ($transport === 'http') {
            if ($document->next_action !== 'submit_reversal' && blank($settings->efris_submission_url)) {
                $errors[] = 'Add the EFRIS submission endpoint URL in Settings before processing HTTP sale sync.';
            }

            if ($document->next_action === 'submit_reversal' && blank($settings->efris_reversal_url)) {
                $errors[] = 'Add the EFRIS reversal endpoint URL in Settings before processing HTTP reversal sync.';
            }
        }

        return $errors;
    }

    private function dispatch(EfrisDocument $document, array $payload): array
    {
        $transport = EfrisPreflightChecklist::transportMode($this->settingsForDocument($document));

        return match ($transport) {
            'simulate' => $this->simulate($document, $payload),
            'http' => app(EfrisHttpTransport::class)->submit($document, $payload),
            default => throw new RuntimeException('The selected EFRIS transport is not supported in this build yet. Keep using simulate mode until the live URA connector is finalized.'),
        };
    }

    private function simulate(EfrisDocument $document, array $payload): array
    {
        $trackingPrefix = $document->next_action === 'submit_reversal'
            ? 'SIM-REV'
            : 'SIM-EFRIS';

        return [
            'transport' => 'simulate',
            'environment' => Arr::get($payload, 'environment', $document->environment ?: 'sandbox'),
            'status' => 'accepted',
            'action' => $document->next_action,
            'message' => $document->next_action === 'submit_reversal'
                ? 'Simulated EFRIS reversal accepted.'
                : 'Simulated EFRIS sale accepted.',
            'tracking_number' => $trackingPrefix . '-' . Str::upper(Str::random(10)),
            'processed_at' => now()->toIso8601String(),
        ];
    }

    private function markFailed(EfrisDocument $document, string $message, array $response): EfrisDocument
    {
        $document->status = 'failed';
        $document->accepted_at = null;
        $document->last_error_message = $message;
        $document->response_snapshot = $response;
        $document->save();

        return $document->fresh();
    }

    private function defaultLimit(): int
    {
        return max(1, (int) config('efris.batch_limit', 25));
    }

    private function settingsForDocument(EfrisDocument $document): ClientSetting
    {
        return ClientSetting::query()->firstOrCreate(
            ['client_id' => $document->client_id],
            ['business_mode' => 'both']
        );
    }
}
