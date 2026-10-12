<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** NEW mail_suppressions — hard bounces / complaints (F-NTF-020). */
class MailSuppression extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public static function isSuppressed(?string $email): bool
    {
        return $email !== null && static::query()->where('email', mb_strtolower(trim($email)))->exists();
    }
}
