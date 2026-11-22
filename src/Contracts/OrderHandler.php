<?php

namespace Freebirds\ApptaggShop\Contracts;

/**
 * Brings orders placed in the Apptagg app into this shop's own system.
 */
interface OrderHandler
{
    /**
     * Creates the order here and returns this system's order id.
     * Must be safe to call twice for the same $order['order_no']: return the existing id.
     *
     * $order: order_no, customer_name, phone, address, delivery_zone, delivery_zone_label, note,
     * subtotal, delivery_charge, total, bonus_used, coin_used, cod_amount (cash to collect),
     * item_count, items[] {product_id, sku, name, price, qty, line_total}, created_at.
     */
    public function import(array $order): string;

    /**
     * Delivery charges for the Apptagg checkout, or null to keep Apptagg's defaults.
     * Keys: inside_dhaka, sub_dhaka, outside_dhaka.
     *
     * @return array<int, array{key:string, label?:string, charge:float}>|null
     */
    public function deliveryZones(): ?array;
}
