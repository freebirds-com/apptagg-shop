<?php

namespace Freebirds\ApptaggShop\Console;

use Freebirds\ApptaggShop\ApptaggClient;
use Freebirds\ApptaggShop\OrderSync;
use Illuminate\Console\Command;

class PullOrdersCommand extends Command
{
    protected $signature = 'apptagg:pull-orders';

    protected $description = 'Import new Apptagg app orders through the order handler';

    public function handle(ApptaggClient $client): int
    {
        if (!$client->enabled()) {
            $this->error('Apptagg is not set up. Run: php artisan apptagg:status');

            return self::FAILURE;
        }
        if (!config('apptagg.order_handler')) {
            $this->error('No order handler set (apptagg.order_handler).');

            return self::FAILURE;
        }

        $r = app(OrderSync::class)->pull();
        $this->info("Imported {$r['imported']}, failed " . count($r['failed']));
        foreach ($r['failed'] as $f) {
            $this->warn("  {$f['order_no']}: {$f['message']}");
        }

        return $r['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
