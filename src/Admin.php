<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

final class Admin
{
    public function __construct(private readonly Sessions $sessions)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [Settings::class, 'register']);
        add_action('admin_post_talistech_checkout_blocker_destroy_sessions', [$this, 'destroySessions']);
        add_filter('plugin_action_links_' . plugin_basename(TCB_PLUGIN_FILE), [$this, 'pluginLinks']);
    }

    public function addMenu(): void
    {
        add_options_page(
            __('Checkout Blocker', 'talistech-checkout-blocker'),
            __('Checkout Blocker', 'talistech-checkout-blocker'),
            'manage_options',
            Settings::PAGE_SLUG,
            [$this, 'renderPage']
        );
    }

    /**
     * @param list<string> $links
     * @return list<string>
     */
    public function pluginLinks(array $links): array
    {
        array_unshift(
            $links,
            '<a href="' . esc_url(admin_url('options-general.php?page=' . Settings::PAGE_SLUG)) . '">' .
            esc_html__('Settings', 'talistech-checkout-blocker') . '</a>'
        );
        return $links;
    }

    public function destroySessions(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to do this.', 'talistech-checkout-blocker'));
        }
        check_admin_referer('talistech_checkout_blocker_destroy_sessions');

        $count = $this->sessions->destroyExactEmailSessions();
        $url = add_query_arg([
            'page' => Settings::PAGE_SLUG,
            'sessions_destroyed' => $count,
        ], admin_url('options-general.php'));
        wp_safe_redirect($url);
        exit;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = Settings::get();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Checkout Blocker', 'talistech-checkout-blocker'); ?></h1>
            <p class="description">
                <?php esc_html_e('Block WooCommerce order placement for matching email addresses, names or billing addresses.', 'talistech-checkout-blocker'); ?>
            </p>

            <?php settings_errors(); ?>
            <?php if (isset($_GET['sessions_destroyed'])): ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php
                    printf(
                        esc_html__('Sessions destroyed for %d user(s).', 'talistech-checkout-blocker'),
                        absint($_GET['sessions_destroyed'])
                    );
                    ?>
                </p></div>
            <?php endif; ?>

            <form action="options.php" method="post">
                <?php
                settings_fields(Settings::OPTION_GROUP);
                do_settings_sections(Settings::PAGE_SLUG);
                submit_button(__('Save settings', 'talistech-checkout-blocker'));
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Destroy sessions', 'talistech-checkout-blocker'); ?></h2>
            <p><?php esc_html_e('Invalidate all active sessions for users listed with an exact email rule. Domain and wildcard rules are intentionally not expanded.', 'talistech-checkout-blocker'); ?></p>
            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="talistech_checkout_blocker_destroy_sessions">
                <?php wp_nonce_field('talistech_checkout_blocker_destroy_sessions'); ?>
                <?php submit_button(__('Destroy exact-email sessions', 'talistech-checkout-blocker'), 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    public static function registerFields(): void
    {
        add_settings_section(
            'talistech_checkout_blocker_rules',
            __('Block rules', 'talistech-checkout-blocker'),
            static function (): void {
                echo '<p>' . esc_html__('Use one rule per line. A match in any category blocks checkout.', 'talistech-checkout-blocker') . '</p>';
            },
            Settings::PAGE_SLUG
        );

        self::addTextAreaField(
            'email_rules',
            __('Blocked email rules', 'talistech-checkout-blocker'),
            __('Supports exact emails, @domain rules and * wildcards. Example: *@example.com', 'talistech-checkout-blocker')
        );
        self::addTextAreaField(
            'name_rules',
            __('Blocked full names', 'talistech-checkout-blocker'),
            __('Use the full billing name, for example: Jan Jansen. Accents and repeated spaces are normalized.', 'talistech-checkout-blocker')
        );
        self::addTextAreaField(
            'address_rules',
            __('Blocked billing addresses', 'talistech-checkout-blocker'),
            __('Format: address line 1|postcode|country. Example: Keringstraat 7|9506|BE. Wildcards are supported.', 'talistech-checkout-blocker')
        );

        add_settings_section(
            'talistech_checkout_blocker_behavior',
            __('Behavior', 'talistech-checkout-blocker'),
            '__return_false',
            Settings::PAGE_SLUG
        );
        add_settings_field(
            'notice_text',
            __('Customer notice', 'talistech-checkout-blocker'),
            [self::class, 'renderTextField'],
            Settings::PAGE_SLUG,
            'talistech_checkout_blocker_behavior',
            ['key' => 'notice_text', 'description' => __('This message is shown when checkout is blocked.', 'talistech-checkout-blocker')]
        );
        add_settings_field(
            'notify_admins',
            __('Admin notifications', 'talistech-checkout-blocker'),
            [self::class, 'renderCheckbox'],
            Settings::PAGE_SLUG,
            'talistech_checkout_blocker_behavior',
            ['key' => 'notify_admins', 'label' => __('Email administrators with full checkout context when a block occurs.', 'talistech-checkout-blocker')]
        );
        add_settings_field(
            'force_logout',
            __('Force logout', 'talistech-checkout-blocker'),
            [self::class, 'renderCheckbox'],
            Settings::PAGE_SLUG,
            'talistech_checkout_blocker_behavior',
            ['key' => 'force_logout', 'label' => __('Log out logged-in users whose email matches an email rule.', 'talistech-checkout-blocker')]
        );
        add_settings_field(
            'logout_redirect',
            __('Logout redirect', 'talistech-checkout-blocker'),
            [self::class, 'renderTextField'],
            Settings::PAGE_SLUG,
            'talistech_checkout_blocker_behavior',
            ['key' => 'logout_redirect', 'type' => 'url', 'description' => __('Where a forced logout redirects the user.', 'talistech-checkout-blocker')]
        );
    }

    /**
     * @param array<string, string> $args
     */
    public static function renderTextArea(array $args): void
    {
        $settings = Settings::get();
        $key = $args['key'];
        printf(
            '<textarea class="large-text code" rows="6" name="%1$s[%2$s]">%3$s</textarea><p class="description">%4$s</p>',
            esc_attr(Settings::OPTION_KEY),
            esc_attr($key),
            esc_textarea((string) ($settings[$key] ?? '')),
            esc_html($args['description'])
        );
    }

    /**
     * @param array<string, string> $args
     */
    public static function renderTextField(array $args): void
    {
        $settings = Settings::get();
        $key = $args['key'];
        $type = $args['type'] ?? 'text';
        printf(
            '<input class="regular-text" type="%1$s" name="%2$s[%3$s]" value="%4$s">',
            esc_attr($type),
            esc_attr(Settings::OPTION_KEY),
            esc_attr($key),
            esc_attr((string) ($settings[$key] ?? ''))
        );
        if (! empty($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }

    /**
     * @param array<string, string> $args
     */
    public static function renderCheckbox(array $args): void
    {
        $settings = Settings::get();
        $key = $args['key'];
        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
            esc_attr(Settings::OPTION_KEY),
            esc_attr($key),
            checked(! empty($settings[$key]), true, false),
            esc_html($args['label'])
        );
    }

    private static function addTextAreaField(string $key, string $label, string $description): void
    {
        add_settings_field(
            $key,
            $label,
            [self::class, 'renderTextArea'],
            Settings::PAGE_SLUG,
            'talistech_checkout_blocker_rules',
            ['key' => $key, 'description' => $description]
        );
    }
}
