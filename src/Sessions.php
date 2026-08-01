<?php

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

final class Sessions
{
    public function destroyExactEmailSessions(): int
    {
        $settings = Settings::get();
        $matcher = new RuleMatcher($settings);
        $count = 0;

        if (! class_exists('WP_Session_Tokens')) {
            return 0;
        }

        foreach ($matcher->emailRules() as $rule) {
            if (! filter_var($rule, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $user = get_user_by('email', $rule);
            if (! $user) {
                continue;
            }

            $tokens = \WP_Session_Tokens::get_instance((int) $user->ID);
            if ($tokens) {
                $tokens->destroy_all();
                $count++;
            }
        }

        return $count;
    }
}
