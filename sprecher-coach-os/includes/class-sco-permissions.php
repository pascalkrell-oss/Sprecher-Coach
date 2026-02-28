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

    public static function checkout_url() {
        $settings = SCO_Utils::get_settings();
        return esc_url_raw((string) ($settings['checkout_url'] ?? ''));
    }

    private static function has_active_yith_membership($user_id) {
        if (!class_exists('YITH_WCMBS_Members') || !is_callable(['YITH_WCMBS_Members', 'get_member'])) {
            return false;
        }

        $member = YITH_WCMBS_Members::get_member($user_id);
        if (!$member || !method_exists($member, 'has_active_plan')) {
            return false;
        }

        return (bool) $member->has_active_plan();
    }

    private static function has_active_woo_subscription($user_id) {
        if (!function_exists('wcs_user_has_subscription')) {
            return false;
        }

        return (bool) wcs_user_has_subscription($user_id, '', 'active');
    }
}

function sco_is_premium_user($user_id) {
    return SCO_Permissions::is_premium_user($user_id);
}

function sco_checkout_url() {
    return SCO_Permissions::checkout_url();
}
