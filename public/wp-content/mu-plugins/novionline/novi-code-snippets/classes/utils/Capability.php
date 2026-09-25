<?php

namespace NoviOnline\CodeSnippets;

/**
 * Class Capability
 * @package NoviOnline\CodeSnippets
 */
class Capability
{
    /**
     * Whether current user can manage code snippets (administrator + novionline login or @novionline.nl email)
     * @return bool
     */
    public static function userCanManageSnippets(): bool
    {
        if (!current_user_can('manage_options')) {
            return false;
        }
        $user = wp_get_current_user();
        if (!$user->exists()) {
            return false;
        }
        if ($user->user_login === 'novionline') {
            return true;
        }
        $email = isset($user->user_email) ? $user->user_email : '';
        $suffix = '@novionline.nl';
        return $suffix === substr(strtolower($email), -strlen($suffix));
    }
}
