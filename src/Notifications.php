<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

final class Notifications
{
    private bool $sent = false;

    /**
     * @param array{type: string, rule: string, value: string} $match
     * @param array<string, string> $context
     */
    public function maybeSend(array $match, array $context): void
    {
        if ($this->sent || empty(Settings::get()['notify_admins'])) {
            return;
        }

        $email = $context['email'] ?? '';
        $ip = $context['ip'] ?? '';
        $transientKey = 'tcb_notice_' . md5(($email !== '' ? $email : 'anon') . '|' . ($ip !== '' ? $ip : 'noip'));
        if (get_transient($transientKey)) {
            return;
        }
        set_transient($transientKey, 1, 10 * MINUTE_IN_SECONDS);

        $recipients = $this->recipients($context);
        if ($recipients === []) {
            return;
        }

        $label = match ($match['type']) {
            RuleMatcher::EMAIL => __('email', 'talistech-checkout-blocker'),
            RuleMatcher::NAME => __('name', 'talistech-checkout-blocker'),
            default => __('address', 'talistech-checkout-blocker'),
        };
        $who = $email !== '' ? $email : trim(($context['first_name'] ?? '') . ' ' . ($context['last_name'] ?? ''));
        $who = $who !== '' ? $who : __('unknown user', 'talistech-checkout-blocker');
        $subject = sprintf(
            __('Blocked checkout (%1$s): %2$s', 'talistech-checkout-blocker'),
            $label,
            $who
        );

        $lines = [
            __('A checkout was blocked for a user matching the blocklist.', 'talistech-checkout-blocker'),
            '',
            'Reason: ' . $match['type'] . ' match (' . $match['rule'] . ')',
            'Name: ' . trim(($context['first_name'] ?? '') . ' ' . ($context['last_name'] ?? '')),
            'Email: ' . ($context['email'] ?? ''),
            'Address: ' . ($context['address_1'] ?? ''),
            'Postcode: ' . ($context['postcode'] ?? ''),
            'Country: ' . ($context['country'] ?? ''),
            'IP: ' . ($context['ip'] ?? ''),
            'User-Agent: ' . ($context['user_agent'] ?? ''),
            'When: ' . current_time('mysql'),
            'URL: ' . ($context['url'] ?? home_url('/')),
            'Site: ' . home_url('/'),
        ];

        wp_mail($recipients, $subject, implode("\n", $lines), ['Content-Type: text/plain; charset=UTF-8']);
        $this->sent = true;
    }

    /**
     * @param array<string, string> $context
     * @return list<string>
     */
    private function recipients(array $context): array
    {
        $recipients = [];
        $admins = get_users(['role' => 'administrator', 'fields' => ['user_email']]);
        foreach ((array) $admins as $admin) {
            if (is_object($admin) && ! empty($admin->user_email) && is_email($admin->user_email)) {
                $recipients[] = sanitize_email($admin->user_email);
            }
        }

        $siteAdmin = get_option('admin_email');
        if ($recipients === [] && is_email($siteAdmin)) {
            $recipients[] = sanitize_email($siteAdmin);
        }

        /**
         * @param list<string> $recipients
         * @param array<string, string> $context
         */
        $filtered = apply_filters('talistech_checkout_blocker_notification_recipients', $recipients, $context);
        return array_values(array_unique(array_filter(array_map('sanitize_email', (array) $filtered))));
    }
}
