<?php

namespace App\Enums;

enum AutomationChannel: string
{
    use Concerns;

    case Whatsapp = 'whatsapp';
    case EmailWebhook = 'email_webhook';

    public static function langKey(): string
    {
        return 'automation_channel';
    }
}
