<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Part 4.3 pos_users — cashier / gate-team roster (no login). */
class PosUser extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /** R-199 icon set, in order. */
    public const ICONS = ['⭐', '🚗', '🌙', '🐉', '🦁', '🦅', '🐺', '🦊', '🐻', '🦋', '🔥', '⚡', '💎', '🎯', '🌟', '🚀', '🎪', '🎭', '🎨', '🏆'];

    protected $fillable = ['name', 'icon', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('name');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    /** R-199: first unused icon, else random. */
    public static function nextIcon(?string $exceptId = null): string
    {
        $used = static::query()->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->pluck('icon')->filter()->all();
        foreach (self::ICONS as $icon) {
            if (! in_array($icon, $used, true)) {
                return $icon;
            }
        }

        return self::ICONS[array_rand(self::ICONS)];
    }

    public function label(): string
    {
        return ($this->icon ?: '⭐').' '.$this->name;
    }
}
