<?php

namespace App\View\Composers;

use App\Support\PublicSettings;
use Illuminate\View\View;

/** Shares the public brand projection with every layout (F-PUB-007 rebuild). */
class BrandComposer
{
    public function compose(View $view): void
    {
        $attrs = request()->attributes;
        if (! $attrs->has('_brand')) {
            $attrs->set('_brand', PublicSettings::current());
        }
        $view->with('brand', $attrs->get('_brand'));
    }
}
