<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Activator {
    public static function activate() {
        SCO_DB::create_tables();
        SCO_Seeder::seed_if_empty();

        add_option('sco_settings', [
            'accent_color' => '#1a93ee',
            'weekly_goal' => 5,
            'free_library_limit' => 5,
            'free_skills_enabled' => ['werbung', 'elearning'],
            'checkout_url' => '',
            'premium_user_ids' => [],
        ]);
    }
}
