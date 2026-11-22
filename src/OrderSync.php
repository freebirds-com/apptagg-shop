<?php

namespace Freebirds\ApptaggShop;

use Freebirds\ApptaggShop\Contracts\OrderHandler;
use Illuminate\Support\Facades\Log;

/** Imports new Apptagg orders through the shop's OrderHandler and acknowledges them. */
class OrderSync
{
    /** @var ApptaggClient */
    protected $client;

    /** @var OrderHandler */
    protected $handler;

    public function __construct(ApptaggClient $client, OrderHandler $handler)
    {
        $this->client = $client;
        $this->handler = $handler;
    }

    /** @return array{imported:int, failed:array} */
    public function pull(int $maxRounds = 20): array
    {
        $imported = 0;
        $failed = [];
        $seenFailed = [];

        // Page 1 again each round: acknowledged orders drop out of the list.
        for ($round = 0; $round < $maxRounds; $round++) {
            $items = $this->client->newOrders(50);
            $progress = false;

            foreach ($items as $o) {
                $no = (string) ($o['order_no'] ?? '');
                if ($no === '' || isset($seenFailed[$no])) {
                    continue;
                }
                try {
                    $externalId = (string) $this->handler->import($o);
                    $this->client->ackOrder($no, $externalId);
                    $imported++;
                    $progress = true;
                } catch (\Throwable $e) {
                    $seenFailed[$no] = true;
                    $failed[] = ['order_no' => $no, 'message' => $e->getMessage()];
                    Log::error('Apptagg order import failed', ['order' => $no, 'error' => $e->getMessage()]);
                }
            }

            if (!$progress) {
                break;
            }
        }

        return compact('imported', 'failed');
    }

    /** Sends the handler's delivery charges; returns false when it has none. */
    public function pushDeliveryZones(): bool
    {
        $zones = $this->handler->deliveryZones();
        if ($zones === null) {
            return false;
        }
        $this->client->updateShop(['delivery_zones' => array_values($zones)]);

        return true;
    }
}
