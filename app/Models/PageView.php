<?php

namespace App\Models;

use App\Support\QatarClock;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Part 4.4 page_views — grouped by Qatar date (R-010, T-125). */
class PageView extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_new_visitor' => 'boolean', 'viewed_at' => 'immutable_datetime', 'viewed_on_qatar' => 'date:Y-m-d'];
    }

    protected static function booted(): void
    {
        static::creating(function (PageView $v) {
            $v->viewed_at ??= now();
            $v->viewed_on_qatar = QatarClock::dateOf($v->viewed_at);
        });
    }
}
