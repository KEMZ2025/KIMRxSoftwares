<?php

namespace App\Support;

use App\Models\PlatformSetting;

class PlatformSupportSettings
{
    public function record(): ?PlatformSetting
    {
        return PlatformSetting::query()->first();
    }

    public function resolved(): array
    {
        $record = $this->record();

        return [
            'company_name' => $this->value($record?->company_name, config('support.company_name', 'KIM SOFTWARE SYSTEMS')),
            'contact_person' => $this->value($record?->contact_person, config('support.contact_person')),
            'phone_primary' => $this->value($record?->phone_primary, config('support.phone_primary')),
            'phone_secondary' => $this->value($record?->phone_secondary, config('support.phone_secondary')),
            'email' => $this->value($record?->email, config('support.email')),
            'whatsapp' => $this->value($record?->whatsapp, config('support.whatsapp')),
            'website' => $this->value($record?->website, config('support.website')),
            'hours' => $this->value($record?->hours, config('support.hours', 'Monday - Saturday, 8:00 AM - 6:00 PM')),
            'response_note' => $this->value(
                $record?->response_note,
                config('support.response_note', 'Share the screen, branch, error message, and time the issue happened so support can help faster.')
            ),
        ];
    }

    public function save(array $attributes): PlatformSetting
    {
        $record = $this->record() ?? new PlatformSetting();
        $record->fill($attributes);
        $record->save();

        return $record->fresh();
    }

    private function value(mixed $stored, mixed $fallback): ?string
    {
        $storedValue = $this->normalize($stored);

        if ($storedValue !== null) {
            return $storedValue;
        }

        return $this->normalize($fallback);
    }

    private function normalize(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string !== '' ? $string : null;
    }
}
