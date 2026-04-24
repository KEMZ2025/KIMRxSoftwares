<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AuditTrail
{
    public function recordSafely(
        ?User $actor,
        string $eventKey,
        string $module,
        string $action,
        string $summary,
        array $options = []
    ): ?AuditLog {
        if (! Schema::hasTable('audit_logs')) {
            return null;
        }

        try {
            return $this->record($actor, $eventKey, $module, $action, $summary, $options);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    public function record(
        ?User $actor,
        string $eventKey,
        string $module,
        string $action,
        string $summary,
        array $options = []
    ): AuditLog {
        $subject = $options['subject'] ?? null;
        $subjectType = $subject instanceof Model ? $subject::class : ($options['subject_type'] ?? null);
        $subjectId = $subject instanceof Model ? $subject->getKey() : ($options['subject_id'] ?? null);
        $subjectLabel = $options['subject_label'] ?? $this->subjectLabel($subject);
        $request = app()->bound('request') ? request() : null;

        return AuditLog::query()->create([
            'client_id' => $this->normalizeInteger(
                $options['client_id']
                    ?? ($subject instanceof Model ? $subject->getAttribute('client_id') : null)
                    ?? $actor?->client_id
            ),
            'branch_id' => $this->normalizeInteger(
                $options['branch_id']
                    ?? ($subject instanceof Model ? $subject->getAttribute('branch_id') : null)
                    ?? $actor?->branch_id
            ),
            'user_id' => $actor?->id,
            'module' => trim($module),
            'action' => trim($action),
            'event_key' => trim($eventKey),
            'summary' => trim($summary),
            'reason' => filled($options['reason'] ?? null) ? trim((string) $options['reason']) : null,
            'subject_type' => $subjectType,
            'subject_id' => $this->normalizeInteger($subjectId),
            'subject_label' => $subjectLabel,
            'old_values' => $this->normalizeDataBlock($options['old_values'] ?? null),
            'new_values' => $this->normalizeDataBlock($options['new_values'] ?? null),
            'context' => $this->normalizeDataBlock($options['context'] ?? null),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent()
                ? Str::limit((string) $request->userAgent(), 1024, '')
                : null,
        ]);
    }

    private function normalizeInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }

    private function normalizeDataBlock(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        $normalized = $this->normalizeValue($value);

        if ($normalized === null) {
            return null;
        }

        if (! is_array($normalized)) {
            $normalized = ['value' => $normalized];
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof Model) {
            return [
                'id' => $value->getKey(),
                'type' => class_basename($value),
                'label' => $this->subjectLabel($value),
            ];
        }

        if (is_array($value)) {
            $normalized = [];

            foreach ($value as $key => $item) {
                $normalized[$key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function subjectLabel(mixed $subject): ?string
    {
        if (! $subject instanceof Model) {
            return null;
        }

        foreach ([
            'invoice_number',
            'receipt_number',
            'batch_number',
            'name',
            'code',
            'title',
            'reference_number',
        ] as $attribute) {
            $value = $subject->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($subject) . ' #' . $subject->getKey();
    }
}
