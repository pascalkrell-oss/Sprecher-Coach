<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Admin {
    public function register_assets() {
        wp_register_style('sco-admin', SCO_PLUGIN_URL . 'assets/css/admin.css', [], SCO_VERSION);
        wp_register_script('sco-admin', SCO_PLUGIN_URL . 'assets/js/admin.js', [], SCO_VERSION, true);
    }

    public function register_menu() {
        add_menu_page('Sprecher Coach', 'Sprecher Coach', 'manage_options', 'sprecher-coach-os', [$this, 'render_settings'], 'dashicons-microphone', 56);
        add_submenu_page('sprecher-coach-os', 'Drills', 'Drills', 'manage_options', 'sprecher-coach-os-drills', [$this, 'render_drills']);
        add_submenu_page('sprecher-coach-os', 'Bibliothek', 'Bibliothek', 'manage_options', 'sprecher-coach-os-library', [$this, 'render_library']);
        add_submenu_page('sprecher-coach-os', 'Missions', 'Missions', 'manage_options', 'sprecher-coach-os-missions', [$this, 'render_missions']);
        add_submenu_page('sprecher-coach-os', 'Einstellungen', 'Einstellungen', 'manage_options', 'sprecher-coach-os-settings', [$this, 'render_settings']);
    }

    private function admin_header($title) {
        wp_enqueue_style('sco-admin');
        wp_enqueue_script('sco-admin');
        echo '<div class="wrap sco-admin"><h1>' . esc_html($title) . '</h1>';
    }

    private function admin_footer() {
        echo '</div>';
    }

    public function render_drills() {
        global $wpdb;
        $this->admin_header('Drills verwalten');
        if (!empty($_POST['sco_action']) && $_POST['sco_action'] === 'add_drill' && check_admin_referer('sco_add_drill')) {
            $wpdb->insert(SCO_DB::table('drills'), [
                'skill_key' => sanitize_key($_POST['skill_key']),
                'category_key' => sanitize_key($_POST['category_key']),
                'title' => sanitize_text_field($_POST['title']),
                'description' => sanitize_textarea_field($_POST['description']),
                'duration_min' => (int) $_POST['duration_min'],
                'difficulty' => (int) $_POST['difficulty'],
                'self_check_questions' => wp_json_encode([
                    ['id' => 'q1', 'label' => sanitize_text_field($_POST['question_1']), 'type' => 'scale'],
                    ['id' => 'q2', 'label' => sanitize_text_field($_POST['question_2']), 'type' => 'checkbox'],
                ]),
                'xp_reward' => (int) $_POST['xp_reward'],
                'is_premium' => !empty($_POST['is_premium']) ? 1 : 0,
            ]);
            echo '<div class="updated"><p>Drill gespeichert.</p></div>';
        }

        $drills = $wpdb->get_results('SELECT * FROM ' . SCO_DB::table('drills') . ' ORDER BY id DESC LIMIT 50', ARRAY_A);
        include SCO_PLUGIN_PATH . 'templates/admin-drills.php';
        $this->admin_footer();
    }

    public function render_library() {
        global $wpdb;
        $this->admin_header('Bibliothek verwalten');
        $items = $wpdb->get_results('SELECT * FROM ' . SCO_DB::table('library') . ' ORDER BY id DESC LIMIT 50', ARRAY_A);
        include SCO_PLUGIN_PATH . 'templates/admin-library.php';
        $this->admin_footer();
    }

    public function render_missions() {
        global $wpdb;
        $this->admin_header('Missions verwalten');
        $missions = $wpdb->get_results('SELECT * FROM ' . SCO_DB::table('missions') . ' ORDER BY id DESC', ARRAY_A);
        include SCO_PLUGIN_PATH . 'templates/admin-missions.php';
        $this->admin_footer();
    }

    public function render_settings() {
        $this->admin_header('Einstellungen');
        $settings = SCO_Utils::get_settings();

        if (!empty($_POST['sco_action']) && $_POST['sco_action'] === 'save_settings' && check_admin_referer('sco_save_settings')) {
            $premiumIds = array_filter(array_map('intval', explode(',', (string) $_POST['premium_user_ids'])));
            $settings = [
                'accent_color' => sanitize_hex_color($_POST['accent_color']) ?: '#1a93ee',
                'weekly_goal' => max(1, (int) $_POST['weekly_goal']),
                'free_library_limit' => max(1, (int) $_POST['free_library_limit']),
                'free_skills_enabled' => array_map('sanitize_key', (array) ($_POST['free_skills_enabled'] ?? ['werbung', 'elearning'])),
                'checkout_url' => esc_url_raw($_POST['checkout_url']),
                'premium_user_ids' => $premiumIds,
            ];
            update_option('sco_settings', $settings);
            echo '<div class="updated"><p>Einstellungen gespeichert.</p></div>';
        }

        include SCO_PLUGIN_PATH . 'templates/admin-settings.php';
        $this->admin_footer();
    }
}
