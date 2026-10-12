<?php

namespace App\Mcp\Tools\Concerns;

use App\Domain\Shared\Actor;
use App\Support\QatarClock;
use Laravel\Mcp\Response;

/** Shared helpers: permission gate per tool and Qatar-time ISO formatting. */
trait ReadOnlyTool
{
    protected function deny(string $permission): ?Response
    {
        return Actor::current()->can($permission) ? null : Response::error("Your account does not have the {$permission} permission.");
    }

    protected static function qatar(mixed $value): ?string
    {
        return $value ? QatarClock::local($value)?->toIso8601String() : null;
    }

    protected static function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
