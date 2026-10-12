<?php
/** Minimal WordPress test doubles. Real WordPress behavior is covered by integration tests. */
declare(strict_types=1);

const DB_NAME = 'test';
const MINUTE_IN_SECONDS = 60;
const HOUR_IN_SECONDS = 3600;

final class TcbTestState
{
    public static array $options = [];
    public static array $users = [];
    public static array $mails = [];
    public static array $destroyed = [];
    public static array $errors = [];
    public static ?WP_User $current = null;
    public static bool $mailResult = true;
    public static bool $mailThrows = false;
    public static mixed $recipients = ['admin@example.com'];
    public static string $home = 'https://shop.example.com';
    public static ?WP_Screen $screen = null;
    public static array $actions = [];
    public static array $privacyContent = [];
    public static array $notices = [];
    public static bool $networkAdmin = false;

    public static function reset(): void
    {
        $GLOBALS['wpdb'] = new TcbTestDb();
        self::$options = self::$users = self::$mails = self::$destroyed = self::$errors = [];
        self::$current = null;
        self::$mailResult = true;
        self::$mailThrows = false;
        self::$recipients = ['admin@example.com'];
        self::$home = 'https://shop.example.com';
        self::$screen = null;
        self::$actions = self::$privacyContent = self::$notices = [];
        self::$networkAdmin = false;
    }
}

class WP_User
{
    public function __construct(public int $ID, public string $user_email, public array $capabilities = []) {}
}

class WP_Error
{
    public array $errors = [];
    public function add(string $code, string $message): void { $this->errors[$code][] = $message; }
    public function has_errors(): bool { return $this->errors !== []; }
}

class WC_Order
{
    public function __construct(private array $data) {}
    public function __call(string $method, array $args): string { return $this->data[substr($method, 4)] ?? ''; }
    public function get_customer_id(): int { return (int) ($this->data['customer_id'] ?? 0); }
}

class WP_Screen { public function __construct(public string $id) {} }

class WP_Session_Tokens
{
    public function __construct(private int $id) {}
    public static function get_instance(int $id): self { return new self($id); }
    public function destroy_all(): void { TcbTestState::$destroyed[] = $this->id; }
}

function __(string $value, string $domain = ''): string { return $value; }
function esc_html(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function esc_html__(string $value, string $domain = ''): string { return esc_html($value); }
function esc_url(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function admin_url(string $path = ''): string { return home_url('/wp-admin/' . $path); }
function plugin_basename(string $file): string { return basename(dirname($file)) . '/' . basename($file); }
function current_user_can(string $capability): bool { return TcbTestState::$current !== null && user_can(TcbTestState::$current, $capability); }
function get_current_screen(): ?WP_Screen { return TcbTestState::$screen; }
function is_network_admin(): bool { return TcbTestState::$networkAdmin; }
function add_action(string $hook, mixed $callback, int $priority = 10, int $acceptedArgs = 1): bool {
    TcbTestState::$actions[$hook][] = ['callback' => $callback, 'priority' => $priority]; return true;
}
function add_filter(string $hook, mixed $callback, int $priority = 10, int $acceptedArgs = 1): bool { return add_action($hook, $callback, $priority, $acceptedArgs); }
function has_action(string $hook, mixed $callback = false): int|bool {
    foreach (TcbTestState::$actions[$hook] ?? [] as $entry) { if ($callback === false || $entry['callback'] === $callback) { return $entry['priority']; } }
    return false;
}
function wp_kses_post(string $value): string { return strip_tags($value, '<p><a><strong><em>'); }
function wp_add_privacy_policy_content(string $name, string $value): void { TcbTestState::$privacyContent[$name] = $value; }
function wc_add_notice(string $message, string $type = 'success'): void { TcbTestState::$notices[$type][] = $message; }
function wc_notice_count(string $type = ''): int { return $type !== '' ? count(TcbTestState::$notices[$type] ?? []) : array_sum(array_map('count', TcbTestState::$notices)); }
// WordPress supplies accent normalization in production; use fixture mappings in isolated unit tests.
// The HTTP suite exercises WordPress's actual remove_accents() implementation on each PHP version.
function remove_accents(string $value): string { return str_replace(['é', 'É'], ['e', 'E'], $value); }
function home_url(string $path = ''): string { return TcbTestState::$home . $path; }
function is_multisite(): bool { return false; }
function get_option(string $key, mixed $default = false): mixed { return TcbTestState::$options[$key] ?? $default; }
function add_option(string $key, mixed $value, string $deprecated = '', mixed $autoload = null): bool {
    if (array_key_exists($key, TcbTestState::$options)) { return false; }
    TcbTestState::$options[$key] = $value;
    return true;
}
function update_option(string $key, mixed $value, mixed $autoload = null): bool {
    if (array_key_exists($key, TcbTestState::$options) && TcbTestState::$options[$key] === $value) { return false; }
    TcbTestState::$options[$key] = $value;
    return true;
}
function delete_option(string $key): bool { unset(TcbTestState::$options[$key]); return true; }
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function sanitize_email(string $value): string { return filter_var($value, FILTER_SANITIZE_EMAIL); }
function is_email(mixed $value): string|false { return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : false; }
function esc_url_raw(string $value): string { return filter_var($value, FILTER_SANITIZE_URL); }
function wp_parse_url(string $value, int $component = -1): mixed { return parse_url($value, $component); }
function wp_validate_redirect(string $url, string $fallback): string { return parse_url($url, PHP_URL_HOST) === 'shop.example.com' ? $url : $fallback; }
function wp_unslash(string $value): string { return stripslashes($value); }
function wp_salt(string $scheme): string { return 'test-only-secret'; }
function current_time(string $type): string { return '2026-10-06 12:00:00'; }
function wp_cache_delete(string $key, string $group): bool { return true; }
function add_settings_error(string $setting, string $code, string $message, string $type): void { TcbTestState::$errors[] = $message; }
function is_user_logged_in(): bool { return TcbTestState::$current !== null; }
function wp_get_current_user(): WP_User { return TcbTestState::$current ?? new WP_User(0, ''); }
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function user_can(WP_User $user, string $capability): bool { return in_array($capability, $user->capabilities, true); }
function get_user_by(string $field, string|int $value): WP_User|false {
    foreach (TcbTestState::$users as $user) { if (($field === 'id' && $user->ID === (int) $value) || ($field === 'email' && strtolower($user->user_email) === strtolower((string) $value))) { return $user; } }
    return false;
}
function get_users(array $args): array { return []; }
function apply_filters(string $hook, mixed $value, mixed ...$args): mixed { return TcbTestState::$recipients; }
function wp_mail(array $to, string $subject, string $body, array $headers): bool {
    TcbTestState::$mails[] = compact('to', 'subject', 'body', 'headers');
    if (TcbTestState::$mailThrows) { throw new RuntimeException('Mail transport failed'); }
    return TcbTestState::$mailResult;
}

class TcbTestDb
{
    public string $options = 'wp_options';
    public bool $locked = false;
    public function prepare(string $query, mixed ...$values): string { return $query; }
    public function get_var(string $query): int {
        if (str_contains($query, 'RELEASE_LOCK')) { $this->locked = false; return 1; }
        if ($this->locked) { return 0; }
        $this->locked = true;
        return 1;
    }
}
