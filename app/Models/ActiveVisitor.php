<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/** Part 4.4 active_visitors — presence (R-179, R-193). */
class ActiveVisitor extends Model
{
    use HasUuids, MassPrunable;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_new_visitor' => 'boolean', 'first_seen_at' => 'immutable_datetime', 'last_seen_at' => 'immutable_datetime'];
    }

    public function prunable(): Builder
    {
        return static::where('last_seen_at', '<', now()->subMinutes((int) config('qcamelmc.visitors.prune_minutes')));
    }

    public function scopeActiveNow(Builder $q): Builder
    {
        return $q->where('last_seen_at', '>=', now()->subMinutes((int) config('qcamelmc.visitors.active_minutes')));
    }
}
