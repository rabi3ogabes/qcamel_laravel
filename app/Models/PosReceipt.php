<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/** Part 4.3 pos_receipts — card-terminal slips (private disk, D-006). */
class PosReceipt extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount_qar' => 'decimal:2',
            'ocr_raw' => 'array',
            'normal_tickets' => 'integer',
            'vip_tickets' => 'integer',
            'parking_tickets' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 5-minute signed URL (T-123). */
    public function temporaryImageUrl(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return URL::temporarySignedRoute('staff.receipts.image', now()->addMinutes(5), ['receipt' => $this->id]);
    }

    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('receipts')->delete($this->image_path);
        }
    }
}
