<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Talistech\CheckoutBlocker\RuleMatcher;
use Talistech\CheckoutBlocker\Settings;

final class RuleMatcherTest extends TestCase
{
    public function testExactEmailRulesAreCaseInsensitive(): void
    {
        $matcher = new RuleMatcher(['email_rules' => 'customer@example.com']);

        $match = $matcher->match(['email' => 'CUSTOMER@EXAMPLE.COM']);

        self::assertSame(RuleMatcher::EMAIL, $match['type']);
    }

    public function testDomainRulesMatchOnlyTheConfiguredDomain(): void
    {
        $matcher = new RuleMatcher(['email_rules' => "@example.com"]);

        self::assertNotNull($matcher->match(['email' => 'customer@example.com']));
        self::assertNull($matcher->match(['email' => 'customer@shop.example.com']));
    }

    public function testDomainRulesAreAcceptedByTheRuleValidator(): void
    {
        self::assertTrue(RuleMatcher::isValidEmailRule('@example.com'));
        self::assertTrue(RuleMatcher::isValidEmailRule('*@example.com'));
        self::assertFalse(RuleMatcher::isValidEmailRule('not-an-email'));
    }

    public function testEmailWildcardsAreSupported(): void
    {
        $matcher = new RuleMatcher(['email_rules' => '*@example.com']);

        self::assertNotNull($matcher->match(['email' => 'customer@example.com']));
        self::assertNull($matcher->match(['email' => 'customer@example.org']));
    }

    public function testNamesAreNormalizedBeforeMatching(): void
    {
        $matcher = new RuleMatcher(['name_rules' => 'José  De  Smet']);

        self::assertNotNull($matcher->match([
            'first_name' => 'JOSE',
            'last_name' => 'de smet',
        ]));
    }

    public function testAddressesUseNormalizedThreePartKeys(): void
    {
        $matcher = new RuleMatcher(['address_rules' => 'Keringstraat 7|9506|BE']);

        self::assertNotNull($matcher->match([
            'address_1' => 'Keringstraat   7',
            'postcode' => '950 6',
            'country' => 'be',
        ]));
    }

    public function testNameAndAddressWildcardsAreSupported(): void
    {
        $matcher = new RuleMatcher([
            'name_rules' => '* de smet',
            'address_rules' => '*laan 10|2000|BE',
        ]);

        self::assertNotNull($matcher->match([
            'first_name' => 'Jan',
            'last_name' => 'De Smet',
        ]));
        self::assertNotNull($matcher->match([
            'address_1' => 'Kerklaan 10',
            'postcode' => '2000',
            'country' => 'BE',
        ]));
    }

    public function testANameOrAddressMatchIsEnoughAndEmailHasPriority(): void
    {
        $matcher = new RuleMatcher([
            'email_rules' => 'customer@example.com',
            'name_rules' => 'Jan Jansen',
            'address_rules' => 'Main Street 1|1000|BE',
        ]);

        $match = $matcher->match([
            'email' => 'customer@example.com',
            'first_name' => 'Jan',
            'last_name' => 'Jansen',
            'address_1' => 'Main Street 1',
            'postcode' => '1000',
            'country' => 'BE',
        ]);

        self::assertSame(RuleMatcher::EMAIL, $match['type']);
    }

    public function testIncompleteAddressDoesNotCreateAFalsePositive(): void
    {
        $matcher = new RuleMatcher(['address_rules' => 'Main Street 1|1000|BE']);

        self::assertNull($matcher->match([
            'address_1' => 'Main Street 1',
            'postcode' => '',
            'country' => 'BE',
        ]));
    }

    public function testInvalidEmailRulesAreIgnored(): void
    {
        $matcher = new RuleMatcher(['email_rules' => "not-an-email\nvalid@example.com"]);

        self::assertNull($matcher->match(['email' => 'not-an-email']));
        self::assertNotNull($matcher->match(['email' => 'valid@example.com']));
    }

    public function testDocumentedWildcardExamplesMatch(): void
    {
        $emailMatcher = new RuleMatcher(['email_rules' => "test-*@example.net\n*@*.example.com"]);
        $addressMatcher = new RuleMatcher(['address_rules' => 'Keringstraat 7|*|BE']);

        self::assertNotNull($emailMatcher->match(['email' => 'test-123@example.net']));
        self::assertNotNull($emailMatcher->match(['email' => 'user@shop.example.com']));
        self::assertNotNull($addressMatcher->match([
            'address_1' => 'Keringstraat 7',
            'postcode' => '9506',
            'country' => 'BE',
        ]));
    }

    public function testSettingsNormalizeRulesForAdminAndCliUsage(): void
    {
        self::assertSame('blocked@example.com', Settings::normalizeRule('BLOCKED@EXAMPLE.COM', 'email_rules'));
        self::assertSame('jose de smet', Settings::normalizeRule('José  De Smet', 'name_rules'));
        self::assertSame('keringstraat 7|9506|be', Settings::normalizeRule('Keringstraat 7 | 950 6 | BE', 'address_rules'));
        self::assertNull(Settings::normalizeRule('invalid', 'address_rules'));
    }

    public function testUnicodeCasingIsConsistentForExactAndWildcardRules(): void
    {
        foreach (['Иван Иванов', 'Иван*', '*Иванов'] as $rule) {
            $matcher = new RuleMatcher(['name_rules' => $rule]);
            self::assertNotNull($matcher->match(['first_name' => 'ИВАН', 'last_name' => 'ИВАНОВ']));
        }
        self::assertNotNull((new RuleMatcher(['name_rules' => 'Γιάννης Παπαδόπουλος']))->match([
            'first_name' => 'ΓΙΆΝΝΗΣ', 'last_name' => 'ΠΑΠΑΔΌΠΟΥΛΟΣ',
        ]));
        self::assertNotNull((new RuleMatcher(['address_rules' => 'Улица 1|1000|BE']))->match([
            'address_1' => 'УЛИЦА 1', 'postcode' => '1000', 'country' => 'be',
        ]));
        self::assertNotNull((new RuleMatcher(['name_rules' => 'Иван Иванов']))->match([
            'first_name' => "ИВАН\u{00a0}", 'last_name' => "\u{00a0}ИВАНОВ",
        ]));
        self::assertNotNull((new RuleMatcher(['address_rules' => 'Улица 1|1000|BE']))->match([
            'address_1' => 'УЛИЦА 1', 'postcode' => "100\u{00a0}0", 'country' => 'be',
        ]));
    }

    public function testComplexWildcardRulesCannotExhaustTheBacktrackLimit(): void
    {
        $matcher = new RuleMatcher(['name_rules' => str_repeat('*a', 20) . '*b*']);
        self::assertNotNull($matcher->match([
            'first_name' => str_repeat('a', 20) . 'b' . str_repeat('a', 20), 'last_name' => 'Smith',
        ]));
        self::assertNull($matcher->match(['first_name' => str_repeat('a', 70), 'last_name' => 'Smith']));
    }

    public function testMetacharactersStayLiteralAndAdjacentStarsAreCollapsed(): void
    {
        $literal = 'a.[b](c)+?^$|\\d/';
        $matcher = new RuleMatcher(['name_rules' => $literal]);
        self::assertNotNull($matcher->match(['first_name' => $literal]));
        self::assertNull($matcher->match(['first_name' => 'axbccc']));
        self::assertNotNull((new RuleMatcher(['name_rules' => '***a****b***']))->match(['first_name' => 'xxaybzz']));
        self::assertNotNull((new RuleMatcher(['name_rules' => '*']))->match(['first_name' => 'ИВАН']));
        self::assertNull((new RuleMatcher(['name_rules' => '*']))->match(['first_name' => '']));
        self::assertNull((new RuleMatcher(['name_rules' => 'a*b']))->match(['first_name' => 'xab']));
        self::assertNull((new RuleMatcher(['name_rules' => '*ab']))->match(['first_name' => 'abx']));
    }

    public function testVeryLongLegacyRulesUseTheUnicodeFallbackWithoutWarnings(): void
    {
        $matcher = new RuleMatcher(['name_rules' => str_repeat('*a', 10000) . '*b*']);
        self::assertNotNull($matcher->match([
            'first_name' => str_repeat('a', 10000) . 'b' . str_repeat('a', 10000), 'last_name' => 'Smith',
        ]));
        self::assertNull($matcher->match(['first_name' => str_repeat('a', 10000), 'last_name' => 'Smith']));
        $unicode = new RuleMatcher(['name_rules' => str_repeat('И', 10000)]);
        self::assertNotNull($unicode->match(['first_name' => str_repeat('и', 10000)]));
        self::assertNull($unicode->match(['first_name' => str_repeat('и', 9999) . 'а']));
    }

    public function testInvalidUtf8CannotProduceRegexWarnings(): void
    {
        self::assertNull((new RuleMatcher(['name_rules' => "bad\xff*"]))->match(['first_name' => 'bad value']));
        self::assertNull((new RuleMatcher(['name_rules' => '*']))->match(['first_name' => "bad\xff"]));
    }
}
