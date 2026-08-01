<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

final class RuleMatcher
{
    public const EMAIL = 'email';
    public const NAME = 'name';
    public const ADDRESS = 'address';

    /**
     * @param array<string, mixed> $settings
     */
    public function __construct(private readonly array $settings)
    {
    }

    /**
     * @param array<string, mixed> $context
     * @return array{type: string, rule: string, value: string}|null
     */
    public function match(array $context): ?array
    {
        $email = self::normalizeEmail((string) ($context['email'] ?? ''));
        foreach ($this->emailRules() as $rule) {
            if ($this->matchesEmail($rule, $email)) {
                return ['type' => self::EMAIL, 'rule' => $rule, 'value' => $email];
            }
        }

        $name = self::normalizeName(
            (string) ($context['first_name'] ?? ''),
            (string) ($context['last_name'] ?? '')
        );
        if ($name !== '') {
            foreach ($this->lines('name_rules') as $rule) {
                $normalizedRule = self::normalizeText($rule);
                if ($this->matchesPattern($normalizedRule, $name)) {
                    return ['type' => self::NAME, 'rule' => $normalizedRule, 'value' => $name];
                }
            }
        }

        $address = self::normalizeAddress(
            (string) ($context['address_1'] ?? ''),
            (string) ($context['postcode'] ?? ''),
            (string) ($context['country'] ?? '')
        );
        if ($address !== '') {
            foreach ($this->lines('address_rules') as $rule) {
                $normalizedRule = self::normalizeAddressRule($rule);
                if ($normalizedRule !== null && $this->matchesPattern($normalizedRule, $address)) {
                    return ['type' => self::ADDRESS, 'rule' => $normalizedRule, 'value' => $address];
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function emailRules(): array
    {
        return array_values(array_filter(
            $this->lines('email_rules'),
            static fn(string $rule): bool => self::isValidEmailRule($rule)
        ));
    }

    /**
     * @return list<string>
     */
    private function lines(string $key): array
    {
        $raw = (string) ($this->settings[$key] ?? '');
        $lines = preg_split('/\R+/', $raw) ?: [];

        return array_values(array_filter(array_map(
            static fn(string $line): string => trim($line),
            $lines
        ), static fn(string $line): bool => $line !== ''));
    }

    private function matchesEmail(string $rule, string $email): bool
    {
        if ($email === '') {
            return false;
        }

        $rule = strtolower(trim($rule));
        if (str_starts_with($rule, '@')) {
            return $email !== '' && str_ends_with($email, $rule) && strrpos($email, '@') === strlen($email) - strlen($rule);
        }

        return $this->matchesPattern($rule, $email);
    }

    private function matchesPattern(string $rule, string $value): bool
    {
        if ($rule === '' || $value === '') {
            return false;
        }

        if (! str_contains($rule, '*')) {
            return $rule === $value;
        }

        $pattern = '/^' . str_replace('\\*', '.*', preg_quote($rule, '/')) . '$/iu';
        return preg_match($pattern, $value) === 1;
    }

    public static function normalizeEmail(string $value): string
    {
        return strtolower(trim($value));
    }

    public static function normalizeName(string $firstName, string $lastName): string
    {
        $firstName = self::normalizeText($firstName);
        $lastName = self::normalizeText($lastName);

        return trim($firstName . ' ' . $lastName);
    }

    public static function normalizeAddress(string $address, string $postcode, string $country): string
    {
        $address = self::normalizeText($address);
        $postcode = preg_replace('/\s+/', '', self::normalizeText($postcode)) ?? '';
        $country = self::normalizeText($country);

        if ($address === '' || $postcode === '' || $country === '') {
            return '';
        }

        return $address . '|' . $postcode . '|' . $country;
    }

    public static function normalizeAddressRule(string $rule): ?string
    {
        $parts = array_map('trim', explode('|', $rule));
        if (count($parts) !== 3) {
            return null;
        }

        $normalized = self::normalizeText($parts[0]) . '|' .
            (preg_replace('/\s+/', '', self::normalizeText($parts[1])) ?? '') . '|' .
            self::normalizeText($parts[2]);

        return in_array('', explode('|', $normalized), true) ? null : $normalized;
    }

    public static function normalizeText(string $value): string
    {
        $value = trim($value);
        if (function_exists('remove_accents')) {
            $value = remove_accents($value);
        } elseif (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        return preg_replace('/\s+/', ' ', $value) ?? '';
    }

    public static function isValidEmailRule(string $rule): bool
    {
        $rule = strtolower(trim($rule));
        if ($rule === '' || substr_count($rule, '@') !== 1) {
            return false;
        }

        [$local, $domain] = explode('@', $rule, 2);
        $local = str_replace('*', 'placeholder', $local);
        $domain = str_replace('*', 'example', $domain);
        if ($local === '') {
            $local = 'placeholder';
        }

        return filter_var($local . '@' . $domain, FILTER_VALIDATE_EMAIL) !== false;
    }
}
