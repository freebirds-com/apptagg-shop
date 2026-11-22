<?php

namespace Freebirds\ApptaggShop\Console;

use Freebirds\ApptaggShop\ApptaggClient;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'apptagg:status';

    protected $description = 'Check the Apptagg setup: API URL, shop key, shop status, mapper and order handler';

    public function handle(ApptaggClient $client): int
    {
        $rows = [
            ['API URL', config('apptagg.api_url') ?: '— not set (APPTAGG_API_URL)'],
            ['Shop key', $client->keyPrefix() ? $client->keyPrefix() . '…' : '— not set (APPTAGG_SHOP_KEY)'],
            ['Image base', config('apptagg.image_base') ?: '— not set'],
            ['Product mapper', config('apptagg.product_mapper') ?: '— not set (required)'],
            ['Order handler', config('apptagg.order_handler') ?: 'none (orders stay on Apptagg)'],
            ['Auto sync', config('apptagg.auto_sync') ? 'on' : 'off'],
        ];

        $ok = true;
        if ($client->enabled()) {
            try {
                $shop = $client->shop();
                $rows[] = ['Shop', ($shop['name'] ?? '?') . ' — ' . ($shop['status_label'] ?? '?')];
                if (($shop['status'] ?? null) !== 1) {
                    $rows[] = ['Note', 'Products show in the app only after Apptagg admin approves the shop.'];
                }
            } catch (\Throwable $e) {
                $rows[] = ['Shop', 'ERROR: ' . $e->getMessage()];
                $ok = false;
            }
        } else {
            $ok = false;
        }

        if ($mapper = config('apptagg.product_mapper')) {
            try {
                $rows[] = ['Products to sync', (string) app($mapper)->query()->count()];
            } catch (\Throwable $e) {
                $rows[] = ['Products to sync', 'ERROR: ' . $e->getMessage()];
                $ok = false;
            }
        } else {
            $ok = false;
        }

        $this->table(['', ''], $rows);
        $ok ? $this->info('Ready.') : $this->warn('Not ready yet, see above.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
