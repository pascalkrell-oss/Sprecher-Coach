<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_DB {
    public static function table($key) {
        global $wpdb;

        $map = [
            'drills' => 'sco_drills',
            'library' => 'sco_library_items',
            'missions' => 'sco_missions',
            'mission_steps' => 'sco_mission_steps',
            'user_progress' => 'sco_user_progress',
            'user_skill_progress' => 'sco_user_skill_progress',
            'user_completions' => 'sco_user_completions',
            'user_mission_progress' => 'sco_user_mission_progress',
        ];

        return $wpdb->prefix . $map[$key];
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];
        $sql[] = "CREATE TABLE " . self::table('drills') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            skill_key VARCHAR(50) NOT NULL,
            category_key VARCHAR(80) NOT NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NOT NULL,
            duration_min TINYINT UNSIGNED NOT NULL DEFAULT 5,
            difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
            self_check_questions LONGTEXT NOT NULL,
            xp_reward SMALLINT UNSIGNED NOT NULL DEFAULT 10,
            is_premium TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY skill_key (skill_key),
            KEY category_key (category_key),
            KEY is_premium (is_premium)
        ) $charset_collate";

        $sql[] = "CREATE TABLE " . self::table('library') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_key VARCHAR(80) NOT NULL,
            skill_key VARCHAR(50) NOT NULL DEFAULT '',
            difficulty TINYINT UNSIGNED NOT NULL DEFAULT 1,
            duration_min TINYINT UNSIGNED NOT NULL DEFAULT 3,
            title VARCHAR(190) NOT NULL,
            content LONGTEXT NOT NULL,
            is_premium TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY category_key (category_key),
            KEY skill_key (skill_key),
            KEY is_premium (is_premium)
        ) $charset_collate";

        $sql[] = "CREATE TABLE " . self::table('missions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            description TEXT NOT NULL,
            duration_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
            is_bonus TINYINT(1) NOT NULL DEFAULT 0,
            is_premium TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY is_premium (is_premium)
        ) $charset_collate";

        $sql[] = "CREATE TABLE " . self::table('mission_steps') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            mission_id BIGINT UNSIGNED NOT NULL,
            step_order SMALLINT UNSIGNED NOT NULL,
            title VARCHAR(190) NOT NULL,
            description TEXT NOT NULL,
            checklist LONGTEXT NOT NULL,
            PRIMARY KEY (id),
            KEY mission_id (mission_id),
            KEY step_order (step_order)
        ) $charset_collate";

        $sql[] = "CREATE TABLE " . self::table('user_progress') . " (
            user_id BIGINT UNSIGNED NOT NULL,
            xp_total INT UNSIGNED NOT NULL DEFAULT 0,
            streak SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            last_completed_date DATE NULL,
            weekly_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            weekly_reset_date DATE NULL,
            preferred_skill VARCHAR(50) NOT NULL DEFAULT 'werbung',
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id),
            KEY last_completed_date (last_completed_date)
        ) $charset_collate";

        $sql[] = "CREATE TABLE " . self::table('user_skill_progress') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            skill_key VARCHAR(50) NOT NULL,
            level SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            xp INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY user_skill (user_id, skill_key)
        ) $charset_collate";

        $sql[] = "CREATE TABLE " . self::table('user_completions') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            completion_date DATE NOT NULL,
            drill_id BIGINT UNSIGNED NOT NULL,
            score SMALLINT UNSIGNED NOT NULL,
            xp SMALLINT UNSIGNED NOT NULL,
            answers_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_date (user_id, completion_date),
            KEY drill_id (drill_id)
        ) $charset_collate";

        $sql[] = "CREATE TABLE " . self::table('user_mission_progress') . " (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            mission_id BIGINT UNSIGNED NOT NULL,
            current_step SMALLINT UNSIGNED NOT NULL DEFAULT 1,
            completed_steps LONGTEXT NOT NULL,
            is_completed TINYINT(1) NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_mission (user_id, mission_id)
        ) $charset_collate";

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }
}
