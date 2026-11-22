<?php

namespace Freebirds\ApptaggShop\Console;

use Freebirds\ApptaggShop\ApptaggClient;
use Freebirds\ApptaggShop\OrderSync;
use Freebirds\ApptaggShop\ProductSync;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'apptagg:sync {ids?* : Only these product ids (default: all)}';

    protected $description = 'Push products to the Apptagg app (create/update; hidden or deleted ones are hidden there too)';

    public function handle(ApptaggClient $client): int
    {
        if (!$client->enabled() || !config('apptagg.product_mapper')) {
            $this->error('Apptagg is not set up. Run: php artisan apptagg:status');

            return self::FAILURE;
        }

        $shop = $client->shop();
        $this->line("Shop: {$shop['name']} ({$shop['status_label']})");

        if (config('apptagg.order_handler') && app(OrderSync::class)->pushDeliveryZones()) {
            $this->line('Delivery charges updated on Apptagg.');
        }

        $totals = app(ProductSync::class)->all($this->argument('ids') ?: null, function ($r) {
            $this->line("  chunk: +{$r['created']} new, {$r['updated']} updated, " . count($r['failed']) . ' failed');
        });

        $this->info("Created {$totals['created']}, updated {$totals['updated']}, failed " . count($totals['failed']));
        foreach ($totals['failed'] as $f) {
            $this->warn('  ' . ($f['sku'] ?? '?') . ': ' . ($f['message'] ?? ''));
        }

        return $totals['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
