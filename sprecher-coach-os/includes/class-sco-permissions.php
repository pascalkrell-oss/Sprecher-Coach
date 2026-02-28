<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Permissions {
    public static function is_premium_user($user_id) {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }

        if (self::has_active_yith_membership($user_id)) {
            return true;
        }

        if (self::has_active_woo_subscription($user_id)) {
            return true;
        }

        $settings = SCO_Utils::get_settings();
        $ids = array_map('intval', (array) ($settings['premium_user_ids'] ?? []));

        return in_array($user_id, $ids, true);
    }

    private static function has_active_yith_membership($user_id) {
        if (!class_exists('YITH_WCMBS_Members')) {
            return false;
        }

        try {
            $member = YITH_WCMBS_Members::get_member($user_id);
            if (!$member || !method_exists($member, 'has_active_plan')) {
                return false;
            }
            return (bool) $member->has_active_plan();
        } catch (Exception $e) {
            return false;
        }
    }

    private static function has_active_woo_subscription($user_id) {
        if (!function_exists('wcs_user_has_subscription')) {
            return false;
        }

        try {
            return (bool) wcs_user_has_subscription($user_id, '', 'active');
        } catch (Exception $e) {
            return false;
        }
    }
}
