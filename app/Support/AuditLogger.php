<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AuditLogger
{
    public static function record(string $event, string $description, ?Model $auditable = null, array $metadata = [], ?Request $request = null, ?User $actor = null): void
    {
        try {
            if (! Schema::hasTable('audit_logs')) {
                return;
            }

            $request ??= request();
            $actor ??= $request?->user();

            AuditLog::create([
                'user_id' => $actor?->id,
                'actor_name' => $actor?->name ?: $actor?->username,
                'actor_role' => $actor?->role?->value,
                'event' => $event,
                'description' => $description,
                'auditable_type' => $auditable ? $auditable::class : null,
                'auditable_id' => $auditable?->getKey(),
                'metadata' => self::cleanMetadata($metadata),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private static function cleanMetadata(array $metadata): array
    {
        array_walk_recursive($metadata, function (&$value, $key) {
            if (str_contains(strtolower((string) $key), 'password')) {
                $value = '[disembunyikan]';
            }
        });

        return $metadata;
    }
}
