<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

use WP_Error;

final class WooCommerce
{
    public function __construct(
        private readonly RuleMatcher $matcher,
        private readonly Notifications $notifications
    ) {
    }

    public function register(): void
    {
        add_action('woocommerce_after_checkout_validation', [$this, 'validateClassicCheckout'], 10, 2);
        add_action('woocommerce_checkout_validate_order_before_payment', [$this, 'validateBlocksCheckout'], 10, 2);
        add_action('template_redirect', [$this, 'maybeForceLogout'], 1);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validateClassicCheckout(array $data, WP_Error $errors): void
    {
        $context = $this->contextFromArray($data);
        $match = $this->matcher->match($context);
        if ($match === null) {
            return;
        }

        $errors->add('talistech_checkout_blocked', $this->notice());
        $this->notifications->maybeSend($match, $context);
    }

    public function validateBlocksCheckout(object $order, WP_Error $errors): void
    {
        if (! is_a($order, 'WC_Order')) {
            return;
        }

        $context = $this->contextFromOrder($order);
        $match = $this->matcher->match($context);
        if ($match === null) {
            return;
        }

        $errors->add('talistech_checkout_blocked', $this->notice());
        $this->notifications->maybeSend($match, $context);
    }

    public function maybeForceLogout(): void
    {
        if (empty(Settings::get()['force_logout']) || ! is_user_logged_in() || is_admin() || wp_doing_ajax()) {
            return;
        }
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        $user = wp_get_current_user();
        $email = $user instanceof \WP_User ? (string) $user->user_email : '';
        if ($email === '' || $this->matcher->match(['email' => $email]) === null) {
            return;
        }

        /** @var \WP_User $user */
        do_action('talistech_checkout_blocker_user_blocked', $user);
        wp_logout();
        wp_safe_redirect(esc_url_raw((string) (Settings::get()['logout_redirect'] ?? home_url('/'))));
        exit;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function contextFromArray(array $data): array
    {
        $context = [
            'email' => $this->text($data['billing_email'] ?? ''),
            'first_name' => $this->text($data['billing_first_name'] ?? ''),
            'last_name' => $this->text($data['billing_last_name'] ?? ''),
            'address_1' => $this->text($data['billing_address_1'] ?? ''),
            'postcode' => $this->text($data['billing_postcode'] ?? ''),
            'country' => $this->text($data['billing_country'] ?? ''),
        ];

        return $this->completeContext($context);
    }

    /**
     * @return array<string, string>
     */
    private function contextFromOrder(object $order): array
    {
        $context = [
            'email' => $this->text($order->get_billing_email()),
            'first_name' => $this->text($order->get_billing_first_name()),
            'last_name' => $this->text($order->get_billing_last_name()),
            'address_1' => $this->text($order->get_billing_address_1()),
            'postcode' => $this->text($order->get_billing_postcode()),
            'country' => $this->text($order->get_billing_country()),
        ];

        return $this->completeContext($context);
    }

    /**
     * @param array<string, string> $context
     * @return array<string, string>
     */
    private function completeContext(array $context): array
    {
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            if ($user instanceof \WP_User) {
                $context['email'] = $context['email'] !== '' ? $context['email'] : (string) $user->user_email;
                $context['first_name'] = $context['first_name'] !== '' ? $context['first_name'] : (string) $user->first_name;
                $context['last_name'] = $context['last_name'] !== '' ? $context['last_name'] : (string) $user->last_name;
                $context['address_1'] = $context['address_1'] !== '' ? $context['address_1'] : (string) get_user_meta($user->ID, 'billing_address_1', true);
                $context['postcode'] = $context['postcode'] !== '' ? $context['postcode'] : (string) get_user_meta($user->ID, 'billing_postcode', true);
                $context['country'] = $context['country'] !== '' ? $context['country'] : (string) get_user_meta($user->ID, 'billing_country', true);
            }
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : '';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash((string) $_SERVER['HTTP_USER_AGENT'])), 0, 512)
            : '';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash((string) $_SERVER['REQUEST_URI'])) : '/';

        return array_merge($context, [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'url' => home_url($requestUri),
        ]);
    }

    private function text(mixed $value): string
    {
        return sanitize_text_field(wp_unslash((string) $value));
    }

    private function notice(): string
    {
        $notice = (string) (Settings::get()['notice_text'] ?? '');
        return $notice !== '' ? $notice : __('Checkout is not available for this account. Please contact support.', 'talistech-checkout-blocker');
    }
}
