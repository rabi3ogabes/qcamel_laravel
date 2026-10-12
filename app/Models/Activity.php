<?php

namespace App\Models;

use App\Enums\ActivityType;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/** Part 4.4 activity_log with ops columns (actor_type, ip, ua). */
class Activity extends SpatieActivity
{
    public function typeLabel(): string
    {
        $t = ActivityType::tryFrom((string) $this->log_name);
        if ($this->log_name === 'pos_form' && data_get($this->properties, 'action') === 'invoice_download') {
            return ActivityType::InvoiceDownload->label();
        }

        return $t?->label() ?? (string) $this->log_name;
    }
}
