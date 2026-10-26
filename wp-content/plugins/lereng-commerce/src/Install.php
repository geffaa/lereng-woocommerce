<?php

namespace Lereng;

/** Activation: database tables and defaults. Safe to run again (dbDelta + add_option). */
final class Install
{
    public const DB_VERSION = '3';

    public static function activate(): void
    {
        self::tables();
        update_option('lereng_version', LERENG_VERSION);
    }

    public static function maybeUpgrade(): void
    {
        if (get_option('lereng_db_version') !== self::DB_VERSION) {
            self::tables();
        }
    }

    public static function tables(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $c = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$wpdb->prefix}lereng_gift_cards (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(20) NOT NULL,
            initial_amount bigint(20) NOT NULL,
            balance bigint(20) NOT NULL,
            order_id bigint(20) unsigned NULL,
            order_item_id bigint(20) unsigned NULL,
            purchaser_email varchar(200) NULL,
            recipient_name varchar(200) NULL,
            recipient_email varchar(200) NULL,
            message text NULL,
            deliver_at datetime NULL,
            delivered_at datetime NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY recipient_email (recipient_email),
            KEY order_id (order_id)
        ) $c;");
        dbDelta("CREATE TABLE {$wpdb->prefix}lereng_gift_card_ledger (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            card_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NULL,
            amount bigint(20) NOT NULL,
            note varchar(200) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY card_id (card_id)
        ) $c;");
        dbDelta("CREATE TABLE {$wpdb->prefix}lereng_subscriptions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            quantity int(11) NOT NULL DEFAULT 1,
            interval_weeks int(11) NOT NULL DEFAULT 2,
            discount_pct decimal(5,2) NOT NULL DEFAULT 10.00,
            price bigint(20) NOT NULL,
            shipping_method varchar(100) NULL,
            shipping_cost bigint(20) NOT NULL DEFAULT 0,
            payment_method varchar(50) NULL,
            address longtext NULL,
            next_date date NULL,
            paused_until date NULL,
            parent_order_id bigint(20) unsigned NULL,
            last_order_id bigint(20) unsigned NULL,
            renewals int(11) NOT NULL DEFAULT 0,
            skipped int(11) NOT NULL DEFAULT 0,
            cancel_reason varchar(200) NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY status_next (status, next_date)
        ) $c;");
        dbDelta("CREATE TABLE {$wpdb->prefix}lereng_subscription_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            subscription_id bigint(20) unsigned NOT NULL,
            type varchar(40) NOT NULL,
            detail varchar(255) NULL,
            order_id bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY subscription_id (subscription_id)
        ) $c;");
        update_option('lereng_db_version', self::DB_VERSION);
    }
}
