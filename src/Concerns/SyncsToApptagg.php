<?php

namespace Freebirds\ApptaggShop\Concerns;

use Freebirds\ApptaggShop\Apptagg;

/**
 * Add to the product model: saving, deleting or restoring it queues a push to Apptagg.
 * For related rows (images, sizes) call Apptagg::productChanged($productId) yourself.
 */
trait SyncsToApptagg
{
    public static function bootSyncsToApptagg(): void
    {
        $mark = function ($model) {
            Apptagg::productChanged($model->getKey());
        };

        static::saved($mark);
        static::deleted($mark);
        if (method_exists(static::class, 'restored')) {
            static::restored($mark);
        }
    }
}
