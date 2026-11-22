<?php

namespace Freebirds\ApptaggShop;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for the Apptagg Shop API (seller endpoints, X-Shop-Key auth).
 * Every call returns the response `data`; failures throw ApptaggException.
 */
class ApptaggClient
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function enabled(): bool
    {
        return !empty($this->config['shop_key']) && !empty($this->config['api_url']);
    }

    public function keyPrefix(): ?string
    {
        return empty($this->config['shop_key']) ? null : substr($this->config['shop_key'], 0, 10);
    }

    /* ---------------- shop ---------------- */

    /** Shop info: name, status, status_label, delivery_zones... */
    public function shop(): array
    {
        return $this->get('seller/shop');
    }

    /** e.g. ['delivery_zones' => [['key' => 'inside_dhaka', 'label' => 'Inside Dhaka', 'charge' => 60]]] */
    public function updateShop(array $fields): array
    {
        return $this->post('seller/shop', $fields);
    }

    /* ---------------- products ---------------- */

    /**
     * Creates or updates products by sku. Invalid items are skipped by Apptagg.
     *
     * @return array{created:int, updated:int, failed:array}
     */
    public function upsertProducts(array $products): array
    {
        $res = $this->send('post', 'seller/products/bulk', ['products' => array_values($products)]);
        // 422 = every item was invalid; the body still lists them in data.failed.
        if (!$res->successful() && $res->status() !== 422) {
            $this->fail($res, 'Product sync failed');
        }

        return [
            'created' => (int) $res->json('data.created', 0),
            'updated' => (int) $res->json('data.updated', 0),
            'failed'  => (array) $res->json('data.failed', []),
        ];
    }

    /* ---------------- orders ---------------- */

    /** Orders not yet taken into this shop's system, oldest first. */
    public function newOrders(int $perPage = 50): array
    {
        return (array) ($this->get('seller/orders', ['imported' => 0, 'per_page' => $perPage])['items'] ?? []);
    }

    public function order(string $orderNo): array
    {
        return $this->get('seller/orders/' . rawurlencode($orderNo));
    }

    /** Marks an order as taken; it leaves the new-orders list. */
    public function ackOrder(string $orderNo, string $externalId): array
    {
        return $this->post('seller/orders/' . rawurlencode($orderNo) . '/ack', ['external_id' => $externalId]);
    }

    /** status: pending, on_hold, confirmed, processing, shipped, delivered, cancelled */
    public function setOrderStatus(string $orderNo, string $status, ?string $note = null): array
    {
        return $this->post('seller/orders/' . rawurlencode($orderNo) . '/status', array_filter([
            'status' => $status,
            'note'   => $note !== null ? Str::limit($note, 188, '') : null,
        ]));
    }

    /* ---------------- raw ---------------- */

    public function get(string $path, array $query = []): array
    {
        return $this->data($this->send('get', $path, $query), "GET {$path} failed");
    }

    public function post(string $path, array $body = []): array
    {
        return $this->data($this->send('post', $path, $body), "POST {$path} failed");
    }

    protected function send(string $method, string $path, array $payload): Response
    {
        if (!$this->enabled()) {
            throw new ApptaggException('Apptagg is not set up: put APPTAGG_API_URL and APPTAGG_SHOP_KEY in .env.');
        }

        $tries = 1 + max(0, (int) ($this->config['retries'] ?? 0));
        for ($i = 1; ; $i++) {
            try {
                return $this->http()->{$method}($path, $payload);
            } catch (ConnectionException $e) {
                if ($i >= $tries) {
                    throw new ApptaggException('Could not reach Apptagg: ' . $e->getMessage(), 0, $e);
                }
                usleep(300000 * $i);
            }
        }
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->config['api_url'])
            ->acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->withHeaders(['X-Shop-Key' => $this->config['shop_key']]);
    }

    protected function data(Response $res, string $what): array
    {
        if (!$res->successful() || $res->json('status') === false) {
            $this->fail($res, $what);
        }

        return (array) $res->json('data');
    }

    protected function fail(Response $res, string $what): void
    {
        $msg = $res->json('message') ?: Str::limit(trim(strip_tags($res->body())), 200);

        throw new ApptaggException("{$what} (HTTP {$res->status()}): {$msg}", $res->status());
    }
}
