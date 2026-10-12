<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/** Branding image slots (logo, hero, before_footer, header_bg, og_image). */
class SiteAsset extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
