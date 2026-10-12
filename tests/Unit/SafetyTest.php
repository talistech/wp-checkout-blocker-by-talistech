<?php
declare(strict_types=1);

namespace Talistech\CheckoutBlocker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Talistech\CheckoutBlocker\{Notifications, RuleMatcher, Sessions, Settings, WooCommerce};

final class SafetyTest extends TestCase
{
    protected function setUp(): void { \TcbTestState::reset(); }

    public function testAccountEmailBlocksAnAlternativeBillingEmail(): void
    {
        $matcher = new RuleMatcher(['email_rules' => 'blocked@example.com', 'name_rules' => 'Jan Jansen']);
        $match = $matcher->match(['email' => 'allowed@example.com', 'account_email' => 'BLOCKED@example.com', 'first_name' => 'Jan', 'last_name' => 'Jansen']);
        self::assertSame(['type' => 'email', 'rule' => 'blocked@example.com', 'value' => 'blocked@example.com'], $match);
    }

    public function testAccountDomainAndWildcardRules(): void
    {
        foreach (['@example.com', 'blocked*@example.com'] as $rule) {
            self::assertNotNull((new RuleMatcher(['email_rules' => $rule]))->match(['email' => 'allowed@other.test', 'account_email' => 'blocked@example.com']));
        }
    }

    public function testMalformedContextAndSettingsCannotBecomeAnArrayRule(): void
    {
        $matcher = new RuleMatcher(['email_rules' => [], 'name_rules' => 'array', 'address_rules' => []]);
        self::assertNull($matcher->match(['email' => [], 'account_email' => new \stdClass(), 'first_name' => []]));
    }

    public function testInvalidSettingTypesAreSafeAndNotificationsStayOff(): void
    {
        $settings = Settings::sanitize(['email_rules' => [], 'name_rules' => [], 'address_rules' => [], 'notice_text' => new \stdClass(), 'notify_admins' => ['1'], 'force_logout' => 'yes', 'logout_redirect' => []]);
        self::assertSame('', $settings['email_rules']);
        self::assertSame(0, $settings['notify_admins']);
        self::assertSame(0, $settings['force_logout']);
        self::assertSame('https://shop.example.com/', $settings['logout_redirect']);
        self::assertNotSame('', $settings['notice_text']);
    }

    public function testSettingsNormalizeDeduplicateAndReportInvalidRules(): void
    {
        $settings = Settings::sanitize(['email_rules' => "INVALID\nBLOCKED@EXAMPLE.COM\nblocked@example.com", 'name_rules' => " José   De Smet \nJOSE DE SMET", 'address_rules' => "invalid\nStreet 1 | 100 0 | BE"]);
        self::assertSame('blocked@example.com', $settings['email_rules']);
        self::assertSame('jose de smet', $settings['name_rules']);
        self::assertSame('street 1|1000|be', $settings['address_rules']);
        self::assertCount(2, \TcbTestState::$errors);
    }

    public function testUnsafeAndMalformedRedirectsFallBackToTheShop(): void
    {
        foreach (['https://evil.test/', '//evil.test/', '/relative', 'javascript:alert(1)', 'https://shop.example.com.evil.test/', 'https://user@shop.example.com/', []] as $url) {
            self::assertSame('https://shop.example.com/', Settings::localRedirect($url));
        }
        self::assertSame('https://shop.example.com/help', Settings::localRedirect('https://shop.example.com/help'));
    }

    public function testActivationAndReadsPreserveStoredConfiguration(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['email_rules' => 'old@example.com', 'notify_admins' => 1];
        Settings::activate();
        self::assertSame('old@example.com', Settings::get()['email_rules']);
        self::assertSame(1, Settings::get()['notify_admins']);
        \TcbTestState::$options[Settings::OPTION_KEY]['email_rules'] = [];
        self::assertSame('', Settings::get()['email_rules']);
    }

    public function testSessionDestructionSkipsManagementAndWildcardAccounts(): void
    {
        \TcbTestState::$users = [new \WP_User(1, 'admin@example.com', ['manage_options']), new \WP_User(2, 'manager@example.com', ['manage_woocommerce']), new \WP_User(3, 'customer@example.com'), new \WP_User(4, 'star*@example.com')];
        \TcbTestState::$options[Settings::OPTION_KEY] = ['email_rules' => "admin@example.com\nmanager@example.com\ncustomer@example.com\nstar*@example.com\n@example.com"];
        self::assertSame(1, (new Sessions())->destroyExactEmailSessions());
        self::assertSame([3], \TcbTestState::$destroyed);
    }

    public function testProtectedManagementAccountsAreNotForciblyLoggedOut(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['force_logout' => 1];
        foreach (['manage_options', 'manage_woocommerce'] as $cap) {
            \TcbTestState::$current = new \WP_User(1, 'admin@example.com', [$cap]);
            (new WooCommerce(new RuleMatcher(['email_rules' => '@example.com']), new Notifications()))->maybeForceLogout();
            self::assertNotNull(\TcbTestState::$current);
        }
    }

    public function testDuplicateLegacyEmailRulesCountEachCustomerOnlyOnce(): void
    {
        \TcbTestState::$users = [new \WP_User(3, 'customer@example.com')];
        \TcbTestState::$options[Settings::OPTION_KEY] = ['email_rules' => "customer@example.com\nCUSTOMER@EXAMPLE.COM\ncustomer@example.com"];
        self::assertSame(1, (new Sessions())->destroyExactEmailSessions());
        self::assertSame([3], \TcbTestState::$destroyed);
    }

    public function testBothCheckoutAdaptersBlockTheAccountEmail(): void
    {
        \TcbTestState::$current = new \WP_User(3, 'blocked@example.com');
        $plugin = new WooCommerce(new RuleMatcher(['email_rules' => 'blocked@example.com']), new Notifications());
        $data = ['billing_email' => 'allowed@example.com'];
        $classic = new \WP_Error();
        $blocks = new \WP_Error();
        $plugin->validateClassicCheckout($data, $classic);
        $plugin->validateBlocksCheckout(new \WC_Order($data), $blocks);
        self::assertTrue($classic->has_errors());
        self::assertSame($classic->errors, $blocks->errors);
    }

    public function testAllowedCheckoutAndMalformedBillingValuesDoNotBlock(): void
    {
        $plugin = new WooCommerce(new RuleMatcher(['email_rules' => 'blocked@example.com']), new Notifications());
        $errors = new \WP_Error();
        $plugin->validateClassicCheckout(['billing_email' => [], 'billing_first_name' => []], $errors);
        self::assertFalse($errors->has_errors());
    }

    public function testExistingOrderPaymentChecksTheTrustedOwnerWhileLoggedOut(): void
    {
        \TcbTestState::$users = [new \WP_User(3, 'blocked@example.com')];
        $plugin = new WooCommerce(new RuleMatcher(['email_rules' => 'blocked@example.com']), new Notifications());
        $plugin->validatePayOrder(new \WC_Order(['customer_id' => 3, 'billing_email' => 'allowed@example.net']));
        self::assertSame(1, wc_notice_count('error'));
    }

    public function testAnotherSignedInAccountCannotBypassTheExistingOrderOwnerRule(): void
    {
        \TcbTestState::$users = [new \WP_User(3, 'blocked@example.com')];
        \TcbTestState::$current = new \WP_User(4, 'allowed@example.net');
        $plugin = new WooCommerce(new RuleMatcher(['email_rules' => 'blocked@example.com']), new Notifications());
        $plugin->validatePayOrder(new \WC_Order(['customer_id' => 3, 'billing_email' => 'allowed@example.net']));
        self::assertSame(1, wc_notice_count('error'));
    }

    public function testExistingOrderPaymentChecksBillingAndCurrentAccountRules(): void
    {
        $plugin = new WooCommerce(new RuleMatcher(['email_rules' => 'blocked@example.com']), new Notifications());
        $plugin->validatePayOrder(new \WC_Order(['billing_email' => 'blocked@example.com']));
        self::assertSame(1, wc_notice_count('error'));
        \TcbTestState::$current = new \WP_User(3, 'blocked@example.com');
        $plugin->validatePayOrder(new \WC_Order(['billing_email' => 'allowed@example.net']));
        self::assertSame(2, wc_notice_count('error'));
    }

    public function testAllowedOrdersAndNonOrderObjectsDoNotCreatePaymentErrors(): void
    {
        $plugin = new WooCommerce(new RuleMatcher(['email_rules' => 'blocked@example.com']), new Notifications());
        $plugin->validatePayOrder(new \WC_Order(['billing_email' => 'allowed@example.net']));
        $plugin->validatePayOrder(new \stdClass());
        self::assertSame(0, wc_notice_count('error'));
    }

    private function notify(string $email, string $ip): void
    {
        (new Notifications())->maybeSend(['type' => 'email', 'rule' => '@example.com', 'value' => $email], ['email' => $email, 'ip' => $ip]);
    }

    public function testNotificationsDefaultToOff(): void
    {
        $this->notify('blocked@example.com', '192.0.2.1');
        self::assertSame([], \TcbTestState::$mails);
        self::assertArrayNotHasKey(Notifications::STATE_KEY, \TcbTestState::$options);
    }

    public function testChangingEmailDoesNotResetIpNotificationLimit(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['notify_admins' => 1];
        $this->notify('one@example.com', '192.0.2.1');
        $this->notify('two@example.com', '192.0.2.1');
        self::assertCount(1, \TcbTestState::$mails);
        $state = \TcbTestState::$options[Notifications::STATE_KEY];
        self::assertSame(1, $state['count']);
        self::assertStringNotContainsString('192.0.2.1', json_encode($state));
    }

    public function testSiteLimitStopsTheSixtyFirstNotification(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['notify_admins' => 1];
        for ($i = 1; $i <= 61; $i++) { $this->notify('blocked@example.com', "192.0.2.$i"); }
        self::assertCount(60, \TcbTestState::$mails);
        self::assertArrayNotHasKey(Notifications::LOCK_KEY, \TcbTestState::$options);
    }

    public function testExpiredLimitsResetButRecentIpEntriesSurviveHourlyReset(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['notify_admins' => 1];
        $recent = hash_hmac('sha256', '192.0.2.1', wp_salt('auth'));
        \TcbTestState::$options[Notifications::STATE_KEY] = ['started' => time() - 3601, 'count' => 60, 'ips' => [$recent => time() + 500, 'expired' => time() - 1]];
        $this->notify('blocked@example.com', '192.0.2.2');
        self::assertCount(1, \TcbTestState::$mails);
        self::assertSame(1, \TcbTestState::$options[Notifications::STATE_KEY]['count']);
        self::assertArrayHasKey($recent, \TcbTestState::$options[Notifications::STATE_KEY]['ips']);
        self::assertArrayNotHasKey('expired', \TcbTestState::$options[Notifications::STATE_KEY]['ips']);
    }

    public function testAnotherRequestsLockIsNotRemoved(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['notify_admins' => 1];
        $GLOBALS['wpdb']->locked = true;
        $this->notify('blocked@example.com', '192.0.2.1');
        self::assertSame([], \TcbTestState::$mails);
        self::assertTrue($GLOBALS['wpdb']->locked);
    }

    public function testBadRecipientFilterDoesNotCrashCheckout(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['notify_admins' => 1];
        \TcbTestState::$recipients = [[], new \stdClass(), 'invalid', 'admin@example.com', 'admin@example.com'];
        $this->notify('blocked@example.com', '192.0.2.1');
        self::assertSame(['admin@example.com'], \TcbTestState::$mails[0]['to']);
    }

    public function testFailedAndThrowingMailCannotUnblockCheckout(): void
    {
        \TcbTestState::$options[Settings::OPTION_KEY] = ['notify_admins' => 1];
        foreach ([false, true] as $throws) {
            delete_option(Notifications::STATE_KEY);
            \TcbTestState::$mailResult = false;
            \TcbTestState::$mailThrows = $throws;
            $errors = new \WP_Error();
            (new WooCommerce(new RuleMatcher(['email_rules' => '@example.com']), new Notifications()))->validateClassicCheckout(['billing_email' => 'blocked@example.com'], $errors);
            self::assertTrue($errors->has_errors());
            self::assertSame(1, \TcbTestState::$options[Notifications::STATE_KEY]['count']);
            self::assertArrayNotHasKey(Notifications::LOCK_KEY, \TcbTestState::$options);
        }
    }

    public function testNotificationUrlsExcludeTokensAndDoNotDuplicateSubsitePaths(): void
    {
        \TcbTestState::$home = 'https://shop.example.com/subsite';
        \TcbTestState::$options[Settings::OPTION_KEY] = ['notify_admins' => 1];
        $previous = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/subsite/checkout/?token=private-value';
        try {
            $errors = new \WP_Error();
            (new WooCommerce(new RuleMatcher(['email_rules' => '@example.com']), new Notifications()))->validateClassicCheckout(['billing_email' => 'blocked@example.com'], $errors);
            $body = \TcbTestState::$mails[0]['body'];
            self::assertStringContainsString('URL: https://shop.example.com/subsite/checkout/', $body);
            self::assertStringNotContainsString('private-value', $body);
            self::assertStringNotContainsString('/subsite/subsite/', $body);
        } finally {
            if ($previous === null) { unset($_SERVER['REQUEST_URI']); } else { $_SERVER['REQUEST_URI'] = $previous; }
        }
    }
}
