<?php
/** Suppress outbound mail from disposable fixtures; never included in the release. */
if (!defined('ABSPATH')) { exit; }
add_filter('pre_wp_mail', static function ($result) {
    return wp_get_environment_type() === 'local' ? true : $result;
});
