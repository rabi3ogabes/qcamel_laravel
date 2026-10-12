<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Part 4.3 customers — one snapshot row per order (R-038); person_key and
 * phone_e164 give the de-duplicated directory (D-037).
 */
class Customer extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'phone_e164', 'country_code', 'nationality', 'id_number', 'person_key', 'notes'];

    protected $hidden = ['id_number'];

    protected static function booted(): void
    {
        static::saving(function (Customer $c) {
            $c->email ??= '';
            $c->phone_e164 = PhoneNumber::e164($c->phone, $c->country_code ?: '+974');
            $c->person_key = PhoneNumber::personKey($c->id_number, $c->phone);
        });
    }

    /** D-094: '' → null. */
    public function getEmailAttribute(?string $value): ?string
    {
        return ($value === null || trim($value) === '') ? null : $value;
    }

    public function holders(): HasManyThrough
    {
        return $this->hasManyThrough(TicketHolder::class, Order::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function fullPhone(): string
    {
        return ($this->country_code ?: '+974').PhoneNumber::digits($this->phone);
    }
}
