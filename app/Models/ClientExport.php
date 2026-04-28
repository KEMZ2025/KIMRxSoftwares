<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientExport extends Model
{
    use HasFactory;

    public const TYPE_CLIENT_EXPORT = 'client_export';

    public const STATUS_READY = 'ready';
    public const STATUS_MISSING = 'missing';

    protected $fillable = [
        'client_id',
        'filename',
        'disk_path',
        'export_type',
        'status',
        'total_size_bytes',
        'database_tables_count',
        'database_rows_count',
        'storage_files_count',
        'storage_bytes',
        'created_by',
        'notes',
        'manifest_json',
    ];

    protected function casts(): array
    {
        return [
            'total_size_bytes' => 'integer',
            'database_tables_count' => 'integer',
            'database_rows_count' => 'integer',
            'storage_files_count' => 'integer',
            'storage_bytes' => 'integer',
            'manifest_json' => 'array',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function absolutePath(): string
    {
        return storage_path('app/' . ltrim((string) $this->disk_path, '/\\'));
    }

    public function fileExists(): bool
    {
        return is_file($this->absolutePath());
    }

    public function formattedSize(): string
    {
        $bytes = (int) ($this->total_size_bytes ?? 0);

        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $size = $bytes / 1024;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return number_format($size, 2) . ' ' . $units[$unitIndex];
    }

    public function displayStatus(): string
    {
        return match ($this->status) {
            self::STATUS_MISSING => 'Missing File',
            default => 'Ready',
        };
    }
}
