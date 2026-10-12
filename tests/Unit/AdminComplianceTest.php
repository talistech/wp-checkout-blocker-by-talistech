<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker\Tests\Unit;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Talistech\CheckoutBlocker\{Admin, Plugin, Sessions, Settings};

final class LegacyConflictFixture {}

final class AdminComplianceTest extends TestCase
{
    protected function setUp(): void
    {
        \TcbTestState::reset();
    }

    private function administrator(string $screen = 'plugins'): void
    {
        \TcbTestState::$current = new \WP_User(1, 'admin@example.com', ['manage_options', 'activate_plugins']);
        \TcbTestState::$screen = new \WP_Screen($screen);
    }

    private function notice(string $method): string
    {
        ob_start();
        Plugin::$method();
        return (string) ob_get_clean();
    }

    public function testInvalidRulesCannotInjectHtmlIntoSettingsErrors(): void
    {
        $settings = Settings::sanitize([
            'email_rules' => "<script>alert(1)</script>\nvalid@example.com",
            'address_rules' => '<img src=x onerror=alert(2)>',
        ]);

        self::assertSame('valid@example.com', $settings['email_rules']);
        self::assertSame('', $settings['address_rules']);
        self::assertCount(2, \TcbTestState::$errors);
        self::assertStringContainsString('&lt;script&gt;', \TcbTestState::$errors[0]);
        self::assertStringContainsString('&lt;img', \TcbTestState::$errors[1]);
        foreach (\TcbTestState::$errors as $message) {
            self::assertStringNotContainsString('<', $message);
        }
    }

    public function testSettingsLinksAreRestrictedToSettingsAdministrators(): void
    {
        $controller = new Admin(new Sessions());
        $links = ['Existing link'];
        \TcbTestState::$current = new \WP_User(3, 'customer@example.com');
        self::assertSame($links, $controller->pluginLinks($links));

        $this->administrator();
        $result = $controller->pluginLinks($links);
        self::assertCount(2, $result);
        self::assertStringContainsString('page=' . Settings::PAGE_SLUG, $result[0]);
        self::assertSame('Existing link', $result[1]);
    }

    public function testDependencyNoticesAreLimitedToRelevantScreens(): void
    {
        foreach (['plugins', 'plugins-network', 'settings_page_' . Settings::PAGE_SLUG] as $screen) {
            $this->administrator($screen);
            $notice = $this->notice('dependencyNotice');
            self::assertStringContainsString('notice-error is-dismissible', $notice);
            self::assertStringContainsString('Activate WooCommerce', $notice);
        }

        foreach (['dashboard', 'edit-post', 'upload', 'woocommerce_page_wc-orders'] as $screen) {
            $this->administrator($screen);
            self::assertSame('', $this->notice('dependencyNotice'));
        }

        \TcbTestState::$screen = null;
        self::assertSame('', $this->notice('dependencyNotice'));
    }

    public function testOperationalNoticesRequirePluginActivationCapability(): void
    {
        \TcbTestState::$screen = new \WP_Screen('plugins');
        \TcbTestState::$current = new \WP_User(3, 'customer@example.com');
        self::assertSame('', $this->notice('dependencyNotice'));

        \TcbTestState::$current = new \WP_User(2, 'manager@example.com', ['manage_options']);
        self::assertSame('', $this->notice('dependencyNotice'));
    }

    public function testDependencyHooksCoverSiteAndNetworkAdministration(): void
    {
        Plugin::boot();
        $callback = [Plugin::class, 'dependencyNotice'];
        self::assertNotFalse(has_action('admin_notices', $callback));
        self::assertNotFalse(has_action('network_admin_notices', $callback));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDependencyNoticeDisappearsWhenSupportedWooCommerceIsPresent(): void
    {
        define('WC_VERSION', '11.1.2');
        $this->administrator();
        self::assertSame('', $this->notice('dependencyNotice'));
    }

    public function testLegacyNoticeDisappearsWhenTheConflictingClassIsAbsent(): void
    {
        $this->administrator();
        self::assertSame('', $this->notice('legacyConflictNotice'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLegacyNoticeHasTheSameScopeAndCapabilityLimits(): void
    {
        class_alias(LegacyConflictFixture::class, 'Talistech_Checkout_Blocker');
        $this->administrator();
        self::assertStringContainsString('notice-warning is-dismissible', $this->notice('legacyConflictNotice'));

        $this->administrator('dashboard');
        self::assertSame('', $this->notice('legacyConflictNotice'));

        $this->administrator('plugins-network');
        self::assertStringContainsString('Deactivate it', $this->notice('legacyConflictNotice'));
        \TcbTestState::$current = new \WP_User(3, 'customer@example.com');
        self::assertSame('', $this->notice('legacyConflictNotice'));
    }
}
