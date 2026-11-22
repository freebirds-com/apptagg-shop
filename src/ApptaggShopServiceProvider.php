<?php

namespace Freebirds\ApptaggShop;

use Freebirds\ApptaggShop\Contracts\OrderHandler;
use Freebirds\ApptaggShop\Contracts\ProductMapper;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class ApptaggShopServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/apptagg.php', 'apptagg');

        $this->app->singleton(ApptaggClient::class, function ($app) {
            return new ApptaggClient($app['config']->get('apptagg'));
        });

        $this->app->bind(ProductMapper::class, function ($app) {
            $class = $app['config']->get('apptagg.product_mapper');
            if (!$class) {
                throw new ApptaggException('Set apptagg.product_mapper to your ProductMapper class.');
            }

            return $app->make($class);
        });

        $this->app->bind(OrderHandler::class, function ($app) {
            $class = $app['config']->get('apptagg.order_handler');
            if (!$class) {
                throw new ApptaggException('Set apptagg.order_handler to your OrderHandler class.');
            }

            return $app->make($class);
        });

        $this->app->bind(ProductSync::class, function ($app) {
            return new ProductSync(
                $app->make(ApptaggClient::class),
                $app->make(ProductMapper::class),
                (int) $app['config']->get('apptagg.bulk_size', 100)
            );
        });

        $this->app->bind(OrderSync::class, function ($app) {
            return new OrderSync($app->make(ApptaggClient::class), $app->make(OrderHandler::class));
        });
    }

    public function boot()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([__DIR__ . '/../config/apptagg.php' => config_path('apptagg.php')], 'apptagg-config');

        $this->commands([
            Console\StatusCommand::class,
            Console\SyncCommand::class,
            Console\PullOrdersCommand::class,
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $config = $this->app['config'];
            $ready = function () use ($config) {
                return (bool) ($config->get('apptagg.shop_key') && $config->get('apptagg.api_url'));
            };

            $sync = $config->get('apptagg.schedule.sync');
            if ($sync && $config->get('apptagg.product_mapper')) {
                $schedule->command('apptagg:sync')->{$sync}()
                    ->name('apptagg-sync')->withoutOverlapping()->when($ready);
            }

            $pull = $config->get('apptagg.schedule.pull_orders');
            if ($pull && $config->get('apptagg.order_handler')) {
                $schedule->command('apptagg:pull-orders')->{$pull}()
                    ->name('apptagg-pull-orders')->withoutOverlapping()->when($ready);
            }
        });
    }
}
