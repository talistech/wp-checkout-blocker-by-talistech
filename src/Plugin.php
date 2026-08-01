<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

final class Plugin
{
    public static function boot(): void
    {
        if (! defined('WC_VERSION') || version_compare((string) WC_VERSION, '10.9', '<')) {
            add_action('admin_notices', [self::class, 'dependencyNotice']);
            return;
        }

        if (class_exists('Talistech_Checkout_Blocker')) {
            add_action('admin_notices', [self::class, 'legacyConflictNotice']);
            return;
        }

        $settings = Settings::get();
        $matcher = new RuleMatcher($settings);
        $sessions = new Sessions();
        $admin = new Admin($sessions);

        $admin->register();
        (new WooCommerce($matcher, new Notifications()))->register();
        CLI::register();
    }

    public static function dependencyNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>' . esc_html__(
            'Checkout Blocker by Talistech BV requires WooCommerce 10.9 or newer and is currently inactive.',
            'talistech-checkout-blocker'
        ) . '</p></div>';
    }

    public static function legacyConflictNotice(): void
    {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-warning"><p>' . esc_html__(
            'The legacy Talistech BV Checkout Blocker is active. Deactivate it before using this plugin to avoid duplicate checkout validation.',
            'talistech-checkout-blocker'
        ) . '</p></div>';
    }
}
