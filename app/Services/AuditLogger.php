<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditLogger
{
    private const REDACTED = '[REDACTED]';

    /**
     * Catat aksi penting. Nilai old/new dibatasi ke field aman dan
     * field sensitif selalu diredaksi.
     */
    public static function log(
        string $module,
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        array $metadata = [],
    ): AuditLog {
        $user = auth()->user();
        $request = app()->runningInConsole() ? null : request();

        return AuditLog::create([
            'causer_id' => $user?->getKey(),
            'causer_name' => $user?->name,
            'causer_email' => $user?->email,
            'module' => $module,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'old_values' => self::sanitize($oldValues) ?: null,
            'new_values' => self::sanitize($newValues) ?: null,
            'metadata' => self::sanitize($metadata) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }

    /** Snapshot field tertentu dari model, aman untuk old values. */
    public static function snapshot(Model $model, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $value = $model->getAttribute($field);

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $values[$field] = $value;
        }

        return $values;
    }

    private static function sanitize(array $values): array
    {
        $safe = [];

        foreach ($values as $key => $value) {
            if (self::isSensitive((string) $key)) {
                $safe[$key] = self::REDACTED;

                continue;
            }

            if (is_array($value)) {
                $safe[$key] = self::sanitize($value);

                continue;
            }

            if (is_string($value) && mb_strlen($value) > 2000) {
                $value = mb_substr($value, 0, 2000).'...';
            }

            $safe[$key] = $value;
        }

        return $safe;
    }

    private static function isSensitive(string $key): bool
    {
        return (bool) preg_match(
            '/(password|token|secret|credential|api[_-]?key|authorization|npwp|file_path|proof_file|attachment_path|quotation_path|report_file|bast_file_path)/i',
            $key
        );
    }
}
