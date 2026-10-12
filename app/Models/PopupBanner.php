<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Part 4.2 popup_banners (kept for data parity, F-PUB-013). Rebuilt as an
 * optional home announcement banner with a date window (Tier C4).
 */
class PopupBanner extends Model
{
    use HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    public function scopeActive(Builder $q): Builder
    {
        $now = now();

        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}
