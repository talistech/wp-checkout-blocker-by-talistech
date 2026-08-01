<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

final class Admin
{
    private const RULES_SECTION = 'talistech_checkout_blocker_rules';
    private const BEHAVIOR_SECTION = 'talistech_checkout_blocker_behavior';

    public function __construct(private readonly Sessions $sessions)
    {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [Settings::class, 'register']);
        add_action('admin_init', [self::class, 'registerFields']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
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

    public function enqueueAssets(string $hookSuffix): void
    {
        if ($hookSuffix !== 'settings_page_' . Settings::PAGE_SLUG) {
            return;
        }

        wp_enqueue_style(
            'talistech-checkout-blocker-admin',
            plugins_url('assets/admin.css', TCB_PLUGIN_FILE),
            [],
            TCB_VERSION
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

        $sessionsDestroyed = isset($_GET['sessions_destroyed'])
            ? absint(wp_unslash((string) $_GET['sessions_destroyed']))
            : null;
        ?>
        <div class="wrap tcb-admin">
            <header class="tcb-hero">
                <div class="tcb-hero__content">
                    <span class="tcb-eyebrow"><?php esc_html_e('WooCommerce protection by Talistech BV', 'talistech-checkout-blocker'); ?></span>
                    <h1><?php esc_html_e('Checkout Blocker', 'talistech-checkout-blocker'); ?></h1>
                    <p><?php esc_html_e('Stop selected customers from placing an order using server-side email, name or billing address rules.', 'talistech-checkout-blocker'); ?></p>
                </div>
                <div class="tcb-hero__meta" aria-label="<?php esc_attr_e('Compatibility', 'talistech-checkout-blocker'); ?>">
                    <span><?php esc_html_e('Server-side validation', 'talistech-checkout-blocker'); ?></span>
                    <span><?php esc_html_e('Classic + Checkout Blocks', 'talistech-checkout-blocker'); ?></span>
                </div>
            </header>

            <?php settings_errors(); ?>
            <?php if ($sessionsDestroyed !== null): ?>
                <div class="notice notice-success is-dismissible"><p>
                    <?php
                    printf(
                        esc_html__('Sessions destroyed for %d user(s).', 'talistech-checkout-blocker'),
                        $sessionsDestroyed
                    );
                    ?>
                </p></div>
            <?php endif; ?>

            <div class="tcb-layout">
                <main class="tcb-main">
                    <form action="options.php" method="post" class="tcb-settings-form">
                        <?php settings_fields(Settings::OPTION_GROUP); ?>

                        <section class="tcb-card" aria-labelledby="tcb-rules-title">
                            <div class="tcb-card__header">
                                <span class="tcb-step" aria-hidden="true">01</span>
                                <div>
                                    <h2 id="tcb-rules-title"><?php esc_html_e('Block rules', 'talistech-checkout-blocker'); ?></h2>
                                    <p><?php esc_html_e('Add one rule per line. A match in any one of the three lists blocks checkout.', 'talistech-checkout-blocker'); ?></p>
                                </div>
                            </div>
                            <table class="form-table tcb-form-table" role="presentation">
                                <?php do_settings_fields(Settings::PAGE_SLUG, self::RULES_SECTION); ?>
                            </table>
                        </section>

                        <section class="tcb-card" aria-labelledby="tcb-behavior-title">
                            <div class="tcb-card__header">
                                <span class="tcb-step" aria-hidden="true">02</span>
                                <div>
                                    <h2 id="tcb-behavior-title"><?php esc_html_e('Customer experience', 'talistech-checkout-blocker'); ?></h2>
                                    <p><?php esc_html_e('Choose what customers see and what should happen after a match.', 'talistech-checkout-blocker'); ?></p>
                                </div>
                            </div>
                            <table class="form-table tcb-form-table" role="presentation">
                                <?php do_settings_fields(Settings::PAGE_SLUG, self::BEHAVIOR_SECTION); ?>
                            </table>
                        </section>

                        <div class="tcb-save-bar">
                            <p><?php esc_html_e('Changes apply to the next checkout attempt.', 'talistech-checkout-blocker'); ?></p>
                            <?php submit_button(__('Save settings', 'talistech-checkout-blocker'), 'primary', 'submit', false); ?>
                        </div>
                    </form>
                </main>

                <aside class="tcb-sidebar" aria-label="<?php esc_attr_e('Checkout Blocker help', 'talistech-checkout-blocker'); ?>">
                    <section class="tcb-card tcb-guide">
                        <h2><?php esc_html_e('Quick start', 'talistech-checkout-blocker'); ?></h2>
                        <ol>
                            <li><?php esc_html_e('Paste one or more rules into the matching list.', 'talistech-checkout-blocker'); ?></li>
                            <li><?php esc_html_e('Set the notice shown to blocked customers.', 'talistech-checkout-blocker'); ?></li>
                            <li><?php esc_html_e('Save, then test with a private browser window.', 'talistech-checkout-blocker'); ?></li>
                        </ol>
                        <p class="tcb-callout"><?php esc_html_e('Rules are normalized when saved, so casing, accents and repeated spaces do not cause accidental misses.', 'talistech-checkout-blocker'); ?></p>
                    </section>

                    <section class="tcb-card tcb-session-card">
                        <span class="tcb-eyebrow"><?php esc_html_e('Account access', 'talistech-checkout-blocker'); ?></span>
                        <h2><?php esc_html_e('Destroy sessions', 'talistech-checkout-blocker'); ?></h2>
                        <p><?php esc_html_e('Immediately invalidate active sessions for WordPress users listed with an exact email rule. Domain and wildcard rules are never expanded.', 'talistech-checkout-blocker'); ?></p>
                        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                            <input type="hidden" name="action" value="talistech_checkout_blocker_destroy_sessions">
                            <?php wp_nonce_field('talistech_checkout_blocker_destroy_sessions'); ?>
                            <?php submit_button(__('Destroy exact-email sessions', 'talistech-checkout-blocker'), 'secondary', 'submit', false); ?>
                        </form>
                    </section>
                </aside>
            </div>
        </div>
        <?php
    }

    public static function registerFields(): void
    {
        add_settings_section(self::RULES_SECTION, '', '__return_false', Settings::PAGE_SLUG);

        self::addTextAreaField(
            'email_rules',
            __('Blocked email addresses', 'talistech-checkout-blocker'),
            __('Block one address, an entire domain, or an email pattern.', 'talistech-checkout-blocker'),
            [
                [__('Exact email', 'talistech-checkout-blocker'), 'blocked@example.com'],
                [__('Entire domain', 'talistech-checkout-blocker'), '@example.com'],
                [__('Wildcard', 'talistech-checkout-blocker'), 'test-*@example.net'],
            ]
        );
        self::addTextAreaField(
            'name_rules',
            __('Blocked full names', 'talistech-checkout-blocker'),
            __('Match the complete billing first and last name. Accents, casing and repeated spaces are normalized.', 'talistech-checkout-blocker'),
            [
                [__('Exact name', 'talistech-checkout-blocker'), 'Jan Jansen'],
                [__('Any first name', 'talistech-checkout-blocker'), '* De Smet'],
            ]
        );
        self::addTextAreaField(
            'address_rules',
            __('Blocked billing addresses', 'talistech-checkout-blocker'),
            __('Use street and number, postcode, and two-letter country code separated by | characters.', 'talistech-checkout-blocker'),
            [
                [__('Exact address', 'talistech-checkout-blocker'), 'Keringstraat 7|9506|BE'],
                [__('Wildcard street', 'talistech-checkout-blocker'), '*laan 10|2000|BE'],
            ]
        );

        add_settings_section(self::BEHAVIOR_SECTION, '', '__return_false', Settings::PAGE_SLUG);
        add_settings_field(
            'notice_text',
            __('Customer notice', 'talistech-checkout-blocker'),
            [self::class, 'renderTextField'],
            Settings::PAGE_SLUG,
            self::BEHAVIOR_SECTION,
            [
                'key' => 'notice_text',
                'label_for' => 'tcb-notice-text',
                'description' => __('Shown at checkout when an order is blocked.', 'talistech-checkout-blocker'),
            ]
        );
        add_settings_field(
            'notify_admins',
            __('Admin notifications', 'talistech-checkout-blocker'),
            [self::class, 'renderCheckbox'],
            Settings::PAGE_SLUG,
            self::BEHAVIOR_SECTION,
            [
                'key' => 'notify_admins',
                'label_for' => 'tcb-notify-admins',
                'label' => __('Email administrators with checkout details when a block occurs.', 'talistech-checkout-blocker'),
            ]
        );
        add_settings_field(
            'force_logout',
            __('Force logout', 'talistech-checkout-blocker'),
            [self::class, 'renderCheckbox'],
            Settings::PAGE_SLUG,
            self::BEHAVIOR_SECTION,
            [
                'key' => 'force_logout',
                'label_for' => 'tcb-force-logout',
                'label' => __('Log out signed-in users whose email matches an email rule.', 'talistech-checkout-blocker'),
            ]
        );
        add_settings_field(
            'logout_redirect',
            __('Logout redirect', 'talistech-checkout-blocker'),
            [self::class, 'renderTextField'],
            Settings::PAGE_SLUG,
            self::BEHAVIOR_SECTION,
            [
                'key' => 'logout_redirect',
                'label_for' => 'tcb-logout-redirect',
                'type' => 'url',
                'description' => __('Local URL used after a forced logout.', 'talistech-checkout-blocker'),
            ]
        );
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function renderTextArea(array $args): void
    {
        $settings = Settings::get();
        $key = (string) $args['key'];
        $id = 'tcb-' . str_replace('_', '-', $key);
        $descriptionId = $id . '-description';
        $examples = is_array($args['examples'] ?? null) ? $args['examples'] : [];
        $placeholder = implode("\n", array_map(
            static fn(array $example): string => (string) ($example[1] ?? ''),
            $examples
        ));
        ?>
        <textarea
            class="large-text code tcb-rule-input"
            rows="6"
            id="<?php echo esc_attr($id); ?>"
            name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
            aria-describedby="<?php echo esc_attr($descriptionId); ?>"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            spellcheck="false"
            autocapitalize="off"
        ><?php echo esc_textarea((string) ($settings[$key] ?? '')); ?></textarea>
        <div class="tcb-field-help" id="<?php echo esc_attr($descriptionId); ?>">
            <p><?php echo esc_html((string) $args['description']); ?></p>
            <?php self::renderExamples($examples); ?>
        </div>
        <?php
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function renderTextField(array $args): void
    {
        $settings = Settings::get();
        $key = (string) $args['key'];
        $id = 'tcb-' . str_replace('_', '-', $key);
        $type = (string) ($args['type'] ?? 'text');
        ?>
        <input
            class="regular-text"
            id="<?php echo esc_attr($id); ?>"
            type="<?php echo esc_attr($type); ?>"
            name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
            value="<?php echo esc_attr((string) ($settings[$key] ?? '')); ?>"
        >
        <?php if (! empty($args['description'])): ?>
            <p class="description"><?php echo esc_html((string) $args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    /**
     * @param array<string, mixed> $args
     */
    public static function renderCheckbox(array $args): void
    {
        $settings = Settings::get();
        $key = (string) $args['key'];
        $id = 'tcb-' . str_replace('_', '-', $key);
        ?>
        <label class="tcb-checkbox" for="<?php echo esc_attr($id); ?>">
            <input
                id="<?php echo esc_attr($id); ?>"
                type="checkbox"
                name="<?php echo esc_attr(Settings::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
                value="1"
                <?php checked(! empty($settings[$key])); ?>
            >
            <span><?php echo esc_html((string) $args['label']); ?></span>
        </label>
        <?php
    }

    /**
     * @param list<array{0: string, 1: string}> $examples
     */
    private static function renderExamples(array $examples): void
    {
        if ($examples === []) {
            return;
        }
        ?>
        <div class="tcb-examples">
            <span class="tcb-examples__label"><?php esc_html_e('Examples', 'talistech-checkout-blocker'); ?></span>
            <?php foreach ($examples as $example): ?>
                <span class="tcb-example">
                    <span><?php echo esc_html($example[0]); ?></span>
                    <code><?php echo esc_html($example[1]); ?></code>
                </span>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * @param list<array{0: string, 1: string}> $examples
     */
    private static function addTextAreaField(string $key, string $label, string $description, array $examples): void
    {
        add_settings_field(
            $key,
            $label,
            [self::class, 'renderTextArea'],
            Settings::PAGE_SLUG,
            self::RULES_SECTION,
            [
                'key' => $key,
                'label_for' => 'tcb-' . str_replace('_', '-', $key),
                'description' => $description,
                'examples' => $examples,
            ]
        );
    }
}
