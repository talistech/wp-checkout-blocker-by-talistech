<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Talistech\CheckoutBlocker\RuleMatcher;

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
}
