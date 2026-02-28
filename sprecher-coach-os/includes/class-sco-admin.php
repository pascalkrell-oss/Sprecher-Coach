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
        add_submenu_page('sprecher-coach-os', 'Tools', 'Tools', 'manage_options', 'sprecher-coach-os-tools', [$this, 'render_tools']);
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

        $message = '';
        $error = '';
        if (!empty($_POST['sco_action']) && $_POST['sco_action'] === 'save_drill') {
            check_admin_referer('sco_save_drill');
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Keine Berechtigung.', 'sprecher-coach-os'));
            }

            $json_raw = wp_unslash((string) ($_POST['self_check_questions'] ?? '[]'));
            $decoded = json_decode($json_raw, true);
            if (!is_array($decoded)) {
                $error = 'Self-Check JSON ist ungültig.';
            } else {
                $data = [
                    'title' => sanitize_text_field($_POST['title'] ?? ''),
                    'description' => sanitize_textarea_field($_POST['description'] ?? ''),
                    'skill_key' => sanitize_key($_POST['skill_key'] ?? ''),
                    'category_key' => sanitize_key($_POST['category_key'] ?? ''),
                    'duration_min' => max(1, (int) ($_POST['duration_min'] ?? 1)),
                    'difficulty' => max(1, min(3, (int) ($_POST['difficulty'] ?? 1))),
                    'xp_reward' => max(1, (int) ($_POST['xp_reward'] ?? 1)),
                    'self_check_questions' => wp_json_encode($decoded),
                    'is_premium' => !empty($_POST['is_premium']) ? 1 : 0,
                ];

                $drill_id = (int) ($_POST['drill_id'] ?? 0);
                if ($drill_id > 0) {
                    $wpdb->update(SCO_DB::table('drills'), $data, ['id' => $drill_id]);
                    $message = 'Drill aktualisiert.';
                } else {
                    $wpdb->insert(SCO_DB::table('drills'), $data);
                    $message = 'Drill gespeichert.';
                }
            }
        }

        $edit_id = (int) ($_GET['edit'] ?? 0);
        $edit_drill = $edit_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . ' WHERE id=%d', $edit_id), ARRAY_A) : null;
        $drills = $wpdb->get_results('SELECT id,title,skill_key,category_key,duration_min,difficulty,xp_reward FROM ' . SCO_DB::table('drills') . ' ORDER BY id DESC LIMIT 100', ARRAY_A);

        include SCO_PLUGIN_PATH . 'templates/admin-drills.php';
        $this->admin_footer();
    }

    public function render_library() {
        global $wpdb;
        $this->admin_header('Bibliothek verwalten');

        $message = '';
        if (!empty($_POST['sco_action']) && $_POST['sco_action'] === 'save_library_item') {
            check_admin_referer('sco_save_library_item');
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Keine Berechtigung.', 'sprecher-coach-os'));
            }

            $data = [
                'category_key' => sanitize_key($_POST['category_key'] ?? ''),
                'skill_key' => sanitize_key($_POST['skill_key'] ?? ''),
                'difficulty' => max(1, min(3, (int) ($_POST['difficulty'] ?? 1))),
                'duration_min' => max(1, (int) ($_POST['duration_min'] ?? 1)),
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'content' => wp_kses_post(wp_unslash($_POST['content'] ?? '')),
                'is_premium' => !empty($_POST['is_premium']) ? 1 : 0,
            ];

            $item_id = (int) ($_POST['item_id'] ?? 0);
            if ($item_id > 0) {
                $wpdb->update(SCO_DB::table('library'), $data, ['id' => $item_id]);
                $message = 'Library-Eintrag aktualisiert.';
            } else {
                $wpdb->insert(SCO_DB::table('library'), $data);
                $message = 'Library-Eintrag gespeichert.';
            }
        }

        $filter_type = sanitize_key($_GET['filter_type'] ?? '');
        $filter_skill = sanitize_key($_GET['filter_skill'] ?? '');
        $where = ['1=1'];
        $args = [];
        if ($filter_type) {
            $where[] = 'category_key=%s';
            $args[] = $filter_type;
        }
        if ($filter_skill) {
            $where[] = 'skill_key=%s';
            $args[] = $filter_skill;
        }

        $sql = 'SELECT * FROM ' . SCO_DB::table('library') . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 100';
        $items = $wpdb->get_results($args ? $wpdb->prepare($sql, ...$args) : $sql, ARRAY_A);
        $edit_id = (int) ($_GET['edit'] ?? 0);
        $edit_item = $edit_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('library') . ' WHERE id=%d', $edit_id), ARRAY_A) : null;

        include SCO_PLUGIN_PATH . 'templates/admin-library.php';
        $this->admin_footer();
    }

    public function render_missions() {
        global $wpdb;
        $this->admin_header('Missions verwalten');

        $message = '';
        $error = '';
        if (!empty($_POST['sco_action']) && $_POST['sco_action'] === 'save_mission') {
            check_admin_referer('sco_save_mission');
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Keine Berechtigung.', 'sprecher-coach-os'));
            }

            $mission_data = [
                'title' => sanitize_text_field($_POST['title'] ?? ''),
                'description' => sanitize_text_field($_POST['short_description'] ?? ''),
                'duration_days' => max(1, (int) ($_POST['duration_days'] ?? 1)),
                'is_bonus' => 0,
                'is_premium' => !empty($_POST['is_premium']) ? 1 : 0,
            ];

            $mission_id = (int) ($_POST['mission_id'] ?? 0);
            if ($mission_id > 0) {
                $wpdb->update(SCO_DB::table('missions'), $mission_data, ['id' => $mission_id]);
            } else {
                $wpdb->insert(SCO_DB::table('missions'), $mission_data);
                $mission_id = (int) $wpdb->insert_id;
            }

            $steps = json_decode(wp_unslash((string) ($_POST['steps_json'] ?? '[]')), true);
            if (!is_array($steps)) {
                $error = 'Steps JSON ist ungültig.';
            } else {
                foreach ($steps as $step) {
                    $step_order = (int) ($step['day'] ?? 0);
                    if ($step_order <= 0) {
                        continue;
                    }

                    $data = [
                        'mission_id' => $mission_id,
                        'step_order' => $step_order,
                        'title' => sanitize_text_field($step['title'] ?? 'Step'),
                        'description' => sanitize_text_field($step['title'] ?? 'Step'),
                        'checklist' => wp_json_encode(array_values(array_map('sanitize_text_field', (array) ($step['checklist'] ?? [])))),
                    ];

                    $existing = (int) $wpdb->get_var($wpdb->prepare('SELECT id FROM ' . SCO_DB::table('mission_steps') . ' WHERE mission_id=%d AND step_order=%d', $mission_id, $step_order));
                    if ($existing > 0) {
                        $wpdb->update(SCO_DB::table('mission_steps'), $data, ['id' => $existing]);
                    } else {
                        $wpdb->insert(SCO_DB::table('mission_steps'), $data);
                    }
                }

                $message = 'Mission gespeichert.';
            }
        }

        $missions = $wpdb->get_results('SELECT * FROM ' . SCO_DB::table('missions') . ' ORDER BY id DESC', ARRAY_A);
        $edit_id = (int) ($_GET['edit'] ?? 0);
        $edit_mission = $edit_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('missions') . ' WHERE id=%d', $edit_id), ARRAY_A) : null;
        $edit_steps = $edit_id ? $wpdb->get_results($wpdb->prepare('SELECT step_order,title,checklist FROM ' . SCO_DB::table('mission_steps') . ' WHERE mission_id=%d ORDER BY step_order ASC', $edit_id), ARRAY_A) : [];

        include SCO_PLUGIN_PATH . 'templates/admin-missions.php';
        $this->admin_footer();
    }

    public function render_tools() {
        $this->admin_header('Tools');
        $message = '';

        if (!empty($_POST['sco_action']) && $_POST['sco_action'] === 'seed_import') {
            check_admin_referer('sco_seed_import');
            if (!current_user_can('manage_options')) {
                wp_die(esc_html__('Keine Berechtigung.', 'sprecher-coach-os'));
            }

            $overwrite = !empty($_POST['overwrite_seed']);
            $report = SCO_Seeder::import_seed($overwrite);
            $message = sprintf(
                'Import fertig: Drills %d/%d, Library %d/%d, Missions %d/%d, Steps %d/%d (inserted/updated).',
                $report['drills']['inserted'],
                $report['drills']['updated'],
                $report['library']['inserted'],
                $report['library']['updated'],
                $report['missions']['inserted'],
                $report['missions']['updated'],
                $report['mission_steps']['inserted'],
                $report['mission_steps']['updated']
            );
        }

        include SCO_PLUGIN_PATH . 'templates/admin-tools.php';
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
