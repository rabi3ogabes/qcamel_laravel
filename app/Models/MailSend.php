<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** NEW mail_sends — DB idempotency for transactional mail (F-NTF-001). */
class MailSend extends Model
{
    use HasUuids;

    protected $guarded = ['id'];
}
