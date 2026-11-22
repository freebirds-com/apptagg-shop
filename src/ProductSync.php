<?php

namespace Freebirds\ApptaggShop;

use Freebirds\ApptaggShop\Contracts\ProductMapper;
use Illuminate\Support\Collection;

/** Maps products with the shop's ProductMapper and upserts them on Apptagg in chunks. */
class ProductSync
{
    /** @var ApptaggClient */
    protected $client;

    /** @var ProductMapper */
    protected $mapper;

    /** @var int */
    protected $chunk;

    public function __construct(ApptaggClient $client, ProductMapper $mapper, int $chunk = 100)
    {
        $this->client = $client;
        $this->mapper = $mapper;
        $this->chunk = max(1, min(200, $chunk));
    }

    /**
     * Syncs the whole catalogue, or only $ids.
     *
     * @param  callable|null  $progress  fn(array $chunkTotals)
     * @return array{created:int, updated:int, failed:array}
     */
    public function all(?array $ids = null, ?callable $progress = null): array
    {
        $totals = $this->empty();
        $query = $this->mapper->query();
        if ($ids) {
            $query->whereIn($query->getModel()->getQualifiedKeyName(), $ids);
        }

        $query->chunkById($this->chunk, function ($products) use (&$totals, $progress) {
            $r = $this->push($products);
            $totals = $this->add($totals, $r);
            if ($progress) {
                $progress($r);
            }
        });

        return $totals;
    }

    /**
     * Syncs the given products (models or ids).
     *
     * @return array{created:int, updated:int, failed:array}
     */
    public function only($products): array
    {
        $products = $products instanceof Collection ? $products : collect($products);
        if ($products->isEmpty()) {
            return $this->empty();
        }
        if (!is_object($products->first())) {
            return $this->all($products->unique()->values()->all());
        }

        $totals = $this->empty();
        foreach ($products->chunk($this->chunk) as $chunk) {
            $totals = $this->add($totals, $this->push($chunk->values()));
        }

        return $totals;
    }

    protected function push(Collection $products): array
    {
        $this->mapper->prepare($products);
        $payload = $products->map(function ($p) {
            return $this->mapper->map($p);
        })->values()->all();

        return $this->client->upsertProducts($payload);
    }

    protected function empty(): array
    {
        return ['created' => 0, 'updated' => 0, 'failed' => []];
    }

    protected function add(array $a, array $b): array
    {
        return [
            'created' => $a['created'] + $b['created'],
            'updated' => $a['updated'] + $b['updated'],
            'failed'  => array_merge($a['failed'], $b['failed']),
        ];
    }
}
