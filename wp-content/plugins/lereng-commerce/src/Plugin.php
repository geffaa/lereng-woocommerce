<?php

namespace Lereng;

/**
 * Wires the modules together. Each module registers its own hooks in register().
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    /** @var array<class-string, object> */
    private array $modules = [];

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        foreach ($this->moduleClasses() as $class) {
            $module = new $class();
            $module->register();
            $this->modules[$class] = $module;
        }
    }

    /** @return list<class-string> */
    private function moduleClasses(): array
    {
        return apply_filters('lereng_modules', [
            Catalog\CoffeeData::class,
            Blocks\Blocks::class,
            Shipping\Shipping::class,
            Payments\Payments::class,
            Mail\Outbox::class,
            GiftCards\GiftCards::class,
            Bundles\Bundles::class,
            Promotions\Coupons::class,
            Checkout\CheckoutFields::class,
            Subscriptions\Subscriptions::class,
            Fulfilment\Fulfilment::class,
            Fulfilment\Invoice::class,
            Account\Restock::class,
            Account\Wishlist::class,
            Wholesale\Wholesale::class,
        ]);
    }

    /** @template T @param class-string<T> $class @return T */
    public function get(string $class): object
    {
        return $this->modules[$class];
    }
}
