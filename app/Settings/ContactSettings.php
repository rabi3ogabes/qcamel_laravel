<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/** Part 4.4 ContactSettings (+ D-084 publish flag, F-PUB-012 support number). */
class ContactSettings extends Settings
{
    public ?string $admin_phone;

    /** @var list<string> */
    public array $admin_emails;

    public bool $publish_admin_phone;

    public ?string $support_whatsapp;

    public static function group(): string
    {
        return 'contact';
    }

    /** Primary admin e-mail (R-096 validated on save). */
    public function primaryAdminEmail(): ?string
    {
        foreach ($this->admin_emails as $e) {
            if (is_string($e) && preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', trim($e))) {
                return trim($e);
            }
        }

        return null;
    }

    /** @return list<string> */
    public function validAdminEmails(): array
    {
        return array_values(array_filter(array_map('trim', $this->admin_emails), fn ($e) => preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $e) === 1));
    }
}
