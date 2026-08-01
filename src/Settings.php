<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

final class Settings
{
    public const OPTION_KEY = 'talistech_checkout_blocker_settings';
    public const OPTION_GROUP = 'talistech_checkout_blocker';
    public const PAGE_SLUG = 'talistech-checkout-blocker';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'email_rules' => '',
            'name_rules' => '',
            'address_rules' => '',
            'notice_text' => __('Checkout is not available for this account. Please contact support.', 'talistech-checkout-blocker'),
            'notify_admins' => 0,
            'force_logout' => 0,
            'logout_redirect' => home_url('/'),
        ];
    }

    public static function activate(): void
    {
        if (get_option(self::OPTION_KEY, null) === null) {
            add_option(self::OPTION_KEY, self::defaults());
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $settings = get_option(self::OPTION_KEY, []);
        if (! is_array($settings)) {
            $settings = [];
        }

        return array_merge(self::defaults(), $settings);
    }

    public static function register(): void
    {
        register_setting(self::OPTION_GROUP, self::OPTION_KEY, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
            'show_in_rest' => false,
        ]);
    }

    /**
     * @param mixed $input
     * @return array<string, mixed>
     */
    public static function sanitize(mixed $input): array
    {
        $input = is_array($input) ? $input : [];
        $output = self::defaults();

        foreach (['email_rules', 'name_rules', 'address_rules'] as $key) {
            [$clean, $invalid] = self::sanitizeLines((string) ($input[$key] ?? ''), $key);
            $output[$key] = $clean;

            if ($invalid !== []) {
                $label = match ($key) {
                    'email_rules' => __('email', 'talistech-checkout-blocker'),
                    'name_rules' => __('name', 'talistech-checkout-blocker'),
                    default => __('address', 'talistech-checkout-blocker'),
                };
                add_settings_error(
                    self::OPTION_KEY,
                    'invalid_' . $key,
                    sprintf(
                        /* translators: 1: rule type, 2: invalid values */
                        __('Some invalid %1$s rules were skipped: %2$s', 'talistech-checkout-blocker'),
                        $label,
                        implode(', ', array_slice($invalid, 0, 5))
                    ),
                    'warning'
                );
            }
        }

        $notice = sanitize_text_field((string) ($input['notice_text'] ?? ''));
        $output['notice_text'] = $notice !== '' ? $notice : self::defaults()['notice_text'];
        $output['notify_admins'] = empty($input['notify_admins']) ? 0 : 1;
        $output['force_logout'] = empty($input['force_logout']) ? 0 : 1;
        $redirect = esc_url_raw((string) ($input['logout_redirect'] ?? ''));
        $output['logout_redirect'] = $redirect !== '' ? $redirect : home_url('/');

        return $output;
    }

    public static function normalizeRule(string $value, string $type): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($type === 'email_rules') {
            $value = strtolower($value);
            return RuleMatcher::isValidEmailRule($value) ? $value : null;
        }

        if ($type === 'name_rules') {
            $value = RuleMatcher::normalizeText($value);
            return $value !== '' ? $value : null;
        }

        if ($type === 'address_rules') {
            return RuleMatcher::normalizeAddressRule($value);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function sanitizeLines(string $raw, string $type): array
    {
        $lines = preg_split('/\R+/', $raw) ?: [];
        $valid = [];
        $invalid = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $normalized = self::normalizeRule($line, $type);
            if ($normalized === null) {
                if ($type !== 'name_rules') {
                    $invalid[] = $type === 'email_rules' ? strtolower($line) : $line;
                }
                continue;
            }

            $valid[] = $normalized;
        }

        return [implode("\n", array_values(array_unique($valid))), array_values(array_unique($invalid))];
    }
}
