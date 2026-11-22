# freebirds/apptagg-shop

Sell a Laravel shop's products in the Apptagg app. The package handles the Apptagg Shop API:
product sync (on save and hourly), importing app orders (every minute) and sending order status back.
Your project only writes two small classes: how a product looks on Apptagg, and how an app order
becomes an order in your system.

Laravel 8 to 12, PHP 8.0+.

## 1. Get a shop key

Apptagg admin: **Admin > Shops > Add Shop**. Copy the API key shown once (`shk_...`).

## 2. Install

```bash
composer require freebirds/apptagg-shop
php artisan vendor:publish --tag=apptagg-config
```

Not on Packagist yet? Add the GitHub repository first:

```bash
composer config repositories.apptagg-shop vcs https://github.com/freebirds-com/apptagg-shop.git
composer require freebirds/apptagg-shop:^1.0
```

`.env`:

```
APPTAGG_API_URL=https://<apptagg-domain>/api/v1
APPTAGG_SHOP_KEY=shk_...
APPTAGG_IMAGE_BASE=https://<your-shop-domain>    # relative image paths become absolute on this
```

## 3. Product mapper (required)

```php
namespace App\Apptagg;

use App\Models\Product;
use Freebirds\ApptaggShop\ProductMapper;
use Illuminate\Database\Eloquent\Builder;

class ShopProductMapper extends ProductMapper
{
    // Every product, hidden and soft-deleted included (they are hidden on Apptagg). No orderBy.
    public function query(): Builder
    {
        return Product::withTrashed()->with('category');
    }

    public function map($p): array
    {
        return [
            'sku'         => 'SHOP-' . $p->id,            // stable and unique, never change it
            'name'        => $p->name,
            'price'       => (float) $p->price,           // BDT
            'old_price'   => $p->regular_price ?: null,   // shows "% OFF"
            'category'    => $this->limit(optional($p->category)->name, 60),
            'description' => $this->plainText($p->description),
            'stock'       => (int) $p->stock,
            'images'      => $this->images([$p->image, ...$p->gallery]),
            'features'    => $this->lines($p->highlights),
            'status'      => $p->is_active && !$p->trashed() ? 1 : 0,
        ];
    }
}
```

Helpers: `imageUrl()`, `images()` (max 10, absolute URLs), `plainText()` (HTML to text),
`lines()` (HTML list to bullet strings), `limit()`. Override `prepare($products)` to eager load per chunk.

## 4. Order handler (optional, to receive app orders)

```php
namespace App\Apptagg;

use Freebirds\ApptaggShop\Contracts\OrderHandler;

class ShopOrderHandler implements OrderHandler
{
    // Create the order and return your order id. Called again for the same order_no? Return the same id.
    public function import(array $o): string
    {
        // $o: order_no, customer_name, phone, address, delivery_zone_label, note,
        //     items[] {sku, name, price, qty, line_total}, bonus_used, coin_used,
        //     cod_amount (cash the courier collects), ...
    }

    // Delivery charges for the Apptagg checkout, or null to keep Apptagg's defaults.
    public function deliveryZones(): ?array
    {
        return [
            ['key' => 'inside_dhaka',  'label' => 'Inside Dhaka',  'charge' => 60],
            ['key' => 'sub_dhaka',     'label' => 'Sub Dhaka',     'charge' => 100],
            ['key' => 'outside_dhaka', 'label' => 'Outside Dhaka', 'charge' => 120],
        ];
    }
}
```

`config/apptagg.php`:

```php
'product_mapper' => App\Apptagg\ShopProductMapper::class,
'order_handler'  => App\Apptagg\ShopOrderHandler::class,   // or null
```

## 5. Push changes right away

Add the trait to the product model:

```php
use Freebirds\ApptaggShop\Concerns\SyncsToApptagg;

class Product extends Model
{
    use SyncsToApptagg;
}
```

For related rows, and for order status changes of app orders:

```php
use Freebirds\ApptaggShop\Apptagg;

Apptagg::productChanged($image->product_id);
Apptagg::orderStatusChanged($order->apptagg_order_no, 'shipped', 'Invoice ' . $order->id);
```

Statuses: `pending`, `on_hold`, `confirmed`, `processing`, `shipped`, `delivered`, `cancelled`.
Changes are sent once, after the response, so pages never wait on Apptagg. Failures are logged;
the hourly sync catches up.

## 6. Run

```bash
php artisan apptagg:status        # checks URL, key, shop status, mapper
php artisan apptagg:sync          # full product sync (also hourly)
php artisan apptagg:pull-orders   # import app orders (also every minute)
```

The scheduler needs the usual cron: `* * * * * php artisan schedule:run`.
Change or turn off the frequencies in `config/apptagg.php` > `schedule`.

Products appear in the app once the Apptagg admin has approved the shop.
