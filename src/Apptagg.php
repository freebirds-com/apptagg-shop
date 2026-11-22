<?php

namespace Freebirds\ApptaggShop;

use Illuminate\Support\Facades\Log;

/**
 * Queues changes during a request and sends them to Apptagg once, after the
 * response (so the shop's admin never waits on Apptagg and related rows saved
 * later in the same request are included). Failures are logged, never thrown;
 * the scheduled full sync catches up.
 *
 *   Apptagg::productChanged($product->id);
 *   Apptagg::orderStatusChanged($order->apptagg_order_no, 'shipped', 'Invoice 1234');
 */
class Apptagg
{
    /** @var array<int|string, true> */
    protected static $products = [];

    /** @var array<string, array{0:string, 1:?string}> */
    protected static $orders = [];

    /** @var bool */
    protected static $flushRegistered = false;

    public static function enabled(): bool
    {
        return app(ApptaggClient::class)->enabled();
    }

    public static function autoSync(): bool
    {
        return (bool) config('apptagg.auto_sync') && static::enabled() && config('apptagg.product_mapper');
    }

    /** Queue one or more product ids (the mapper's model keys) for a push. */
    public static function productChanged(...$ids): void
    {
        if (!static::autoSync()) {
            return;
        }
        foreach ($ids as $id) {
            foreach ((array) $id as $one) {
                if ($one !== null && $one !== '' && $one !== 0) {
                    static::$products[$one] = true;
                }
            }
        }
        static::registerFlush();
    }

    /** Queue an order status for Apptagg (pending, on_hold, confirmed, processing, shipped, delivered, cancelled). */
    public static function orderStatusChanged(?string $orderNo, ?string $status, ?string $note = null): void
    {
        if (!$orderNo || !$status || !static::enabled()) {
            return;
        }
        static::$orders[$orderNo] = [$status, $note];
        static::registerFlush();
    }

    /** Sends everything queued now. Called automatically when the request ends. */
    public static function flush(): void
    {
        $orders = static::$orders;
        $products = array_keys(static::$products);
        static::$orders = [];
        static::$products = [];
        static::$flushRegistered = false;

        $client = app(ApptaggClient::class);
        foreach ($orders as $no => [$status, $note]) {
            try {
                $client->setOrderStatus($no, $status, $note);
            } catch (\Throwable $e) {
                Log::error('Apptagg status push failed', ['order' => $no, 'status' => $status, 'error' => $e->getMessage()]);
            }
        }

        if ($products && config('apptagg.product_mapper')) {
            try {
                $r = app(ProductSync::class)->only($products);
                if ($r['failed']) {
                    Log::warning('Apptagg sync: some products failed', $r['failed']);
                }
            } catch (\Throwable $e) {
                Log::error('Apptagg sync failed: ' . $e->getMessage(), ['ids' => $products]);
            }
        }
    }

    /** Pending work, for tests. */
    public static function pending(): array
    {
        return ['products' => array_keys(static::$products), 'orders' => static::$orders];
    }

    protected static function registerFlush(): void
    {
        if (static::$flushRegistered) {
            return;
        }
        static::$flushRegistered = true;
        app()->terminating(function () {
            static::flush();
        });
    }
}
