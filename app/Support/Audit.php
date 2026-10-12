<?php

namespace App\Support;

use App\Domain\Shared\Actor;
use App\Enums\ActivityType;
use App\Events\ActivityLogged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Server-side audit trail (D-024, D-025, F-ADM-LOG-011 rebuild) on top of
 * spatie/laravel-activitylog with actor type / identifier / IP / UA.
 * Never throws: an audit failure must not break the business action.
 */
final class Audit
{
    /** Keys whose values are always redacted in properties. */
    private const SECRET_KEYS = ['checkout_secret', 'api_login_secret', 'support_pin', 'webhook_url', 'email_webhook_url', 'password', 'passcode', 'secret', 'token'];

    public static function log(ActivityType|string $type, string $description, ?Model $subject = null, array $properties = [], ?Actor $actor = null): void
    {
        try {
            $actor ??= Actor::current();
            $logName = $type instanceof ActivityType ? $type->value : $type;
            $request = app()->runningInConsole() ? null : request();

            $logger = activity($logName)->withProperties(self::redact($properties));
            if ($subject) {
                $logger->performedOn($subject);
            }
            if ($actor->user) {
                $logger->causedBy($actor->user);
            }
            $logger->tap(function ($activity) use ($actor, $request) {
                $activity->actor_type = $actor->actorType()->value;
                $activity->actor_identifier = Text::limit($actor->identifier(), 160);
                $activity->ip_address = $request?->ip();
                $activity->user_agent = Text::limit($request?->userAgent(), 300);
            });
            $activity = $logger->log($description);
            if ($activity) {
                event(new ActivityLogged($activity->getKey(), $logName));
            }
        } catch (Throwable $e) {
            Log::warning('audit log failed', ['type' => (string) ($type instanceof ActivityType ? $type->value : $type), 'error' => $e->getMessage()]);
        }
    }

    public static function redact(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::redact($v);
            } elseif (is_string($k) && in_array(strtolower($k), self::SECRET_KEYS, true) && $v !== null && $v !== '') {
                $data[$k] = '••••';
            }
        }

        return $data;
    }
}
