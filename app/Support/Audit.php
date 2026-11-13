<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes one append-only audit row. Never pass secrets (passwords, PINs, tokens) in $properties.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, ?Model $subject = null, array $properties = [], ?User $actor = null): AuditLog
    {
        $request = request();
        $actor ??= $request->user();

        $log = new AuditLog;
        $log->forceFill([
            'user_id' => $actor instanceof User ? $actor->id : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'terminal_id' => app(TerminalContext::class)->current($request)?->id,
        ])->save();

        return $log;
    }
}
