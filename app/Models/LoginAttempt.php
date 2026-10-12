<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** Part 4.1 login_attempts — audit + lockout source (R-142…R-147). */
class LoginAttempt extends Model
{
    use HasUuids, MassPrunable;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['success' => 'boolean', 'attempted_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }

    public function prunable(): Builder
    {
        return static::where('attempted_at', '<', now()->subDays((int) config('qcamelmc.auth.login_attempt_retention_days', 7)));
    }
}
