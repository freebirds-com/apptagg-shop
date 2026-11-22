<?php

namespace Freebirds\ApptaggShop\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Turns this shop's products into Apptagg products.
 * Extend Freebirds\ApptaggShop\ProductMapper for the helpers.
 */
interface ProductMapper
{
    /**
     * Every product to keep in sync, hidden and soft-deleted ones included
     * (they are sent with status 0 so they leave the app).
     */
    public function query(): Builder;

    /** Runs once per chunk before map(), e.g. to eager load images. */
    public function prepare(Collection $products): void;

    /**
     * One product -> Apptagg fields.
     * Required: sku (stable, unique), name, price. Optional: old_price, category, brand,
     * short_description, description, stock, unit, images (URLs), features, is_featured,
     * status (1 shown, 0 hidden).
     *
     * @param  \Illuminate\Database\Eloquent\Model  $product
     */
    public function map($product): array;
}
