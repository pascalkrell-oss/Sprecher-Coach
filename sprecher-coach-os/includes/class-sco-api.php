<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_API {
    public function register_hooks() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        $auth = ['permission_callback' => [$this, 'must_be_logged_in']];

        register_rest_route('sco/v1', '/dashboard', array_merge(['methods' => 'GET', 'callback' => [$this, 'dashboard']], $auth));
        register_rest_route('sco/v1', '/complete-drill', array_merge(['methods' => 'POST', 'callback' => [$this, 'complete_drill']], $auth));
        register_rest_route('sco/v1', '/skilltree', array_merge(['methods' => 'GET', 'callback' => [$this, 'skilltree']], $auth));
        register_rest_route('sco/v1', '/missions', array_merge(['methods' => 'GET', 'callback' => [$this, 'missions']], $auth));
        register_rest_route('sco/v1', '/missions/step-complete', array_merge(['methods' => 'POST', 'callback' => [$this, 'mission_step_complete']], $auth));
        register_rest_route('sco/v1', '/drills/recommend', array_merge(['methods' => 'GET', 'callback' => [$this, 'recommend_drill']], $auth));
        register_rest_route('sco/v1', '/drills/alt-text', array_merge(['methods' => 'GET', 'callback' => [$this, 'alt_text']], $auth));
        register_rest_route('sco/v1', '/library', array_merge(['methods' => 'GET', 'callback' => [$this, 'library']], $auth));
        register_rest_route('sco/v1', '/library/open', array_merge(['methods' => 'POST', 'callback' => [$this, 'library_open']], $auth));
    }

    public function must_be_logged_in() {
        if (!is_user_logged_in()) {
            return new WP_Error('sco_auth_required', __('Bitte einloggen.', 'sprecher-coach-os'), ['status' => 401]);
        }

        return true;
    }

    public function dashboard() {
        global $wpdb;

        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $progress = $this->ensure_user_progress($user_id);
        $drill = $this->get_daily_drill($user_id, $premium);

        return rest_ensure_response([
            'drill' => $drill,
            'progress' => [
                'xp_total' => (int) $progress['xp_total'],
                'streak' => (int) $progress['streak'],
                'weekly_count' => (int) $progress['weekly_count'],
                'weekly_goal' => (int) SCO_Utils::get_settings()['weekly_goal'],
                'level' => SCO_Utils::level_from_xp((int) $progress['xp_total']),
                'last_completed_date' => $progress['last_completed_date'],
            ],
            'premium' => $premium,
            'teaser' => SCO_Utils::copy('premium_tooltips', 'locked_skill'),
            'cta' => SCO_Utils::copy('cta'),
            'premium_tooltips' => SCO_Utils::copy('premium_tooltips'),
        ]);
    }

    private function ensure_user_progress($user_id) {
        global $wpdb;

        $today = SCO_Utils::today();
        $week_monday = SCO_Utils::week_monday($today);
        $table = SCO_DB::table('user_progress');

        $progress = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE user_id=%d", $user_id), ARRAY_A);
        if (!$progress) {
            $progress = [
                'user_id' => $user_id,
                'xp_total' => 0,
                'streak' => 0,
                'last_completed_date' => null,
                'weekly_count' => 0,
                'weekly_reset_date' => $week_monday,
                'preferred_skill' => 'werbung',
            ];
            $wpdb->insert($table, [
                'user_id' => $user_id,
                'xp_total' => 0,
                'streak' => 0,
                'last_completed_date' => null,
                'weekly_count' => 0,
                'weekly_reset_date' => $week_monday,
                'preferred_skill' => 'werbung',
                'updated_at' => current_time('mysql'),
            ]);
            return $progress;
        }

        if ($progress['weekly_reset_date'] !== $week_monday) {
            $progress['weekly_count'] = 0;
            $progress['weekly_reset_date'] = $week_monday;
            $wpdb->update($table, [
                'weekly_count' => 0,
                'weekly_reset_date' => $week_monday,
                'updated_at' => current_time('mysql'),
            ], ['user_id' => $user_id]);
        }

        return $progress;
    }

    private function get_daily_drill($user_id, $premium) {
        global $wpdb;

        $today = SCO_Utils::today();
        $settings = SCO_Utils::get_settings();
        $free_skills = (array) $settings['free_skills_enabled'];

        $skill = 'werbung';
        $row = $wpdb->get_row($wpdb->prepare('SELECT preferred_skill FROM ' . SCO_DB::table('user_progress') . ' WHERE user_id=%d', $user_id), ARRAY_A);
        if ($row && !empty($row['preferred_skill'])) {
            $skill = sanitize_key($row['preferred_skill']);
        }

        $last7 = $wpdb->get_col($wpdb->prepare('SELECT drill_id FROM ' . SCO_DB::table('user_completions') . ' WHERE user_id=%d AND completion_date >= DATE_SUB(%s, INTERVAL 7 DAY)', $user_id, $today));
        $last7 = array_map('intval', $last7);

        $where = 'WHERE skill_key=%s';
        $args = [$skill];
        if (!$premium) {
            $where .= ' AND is_premium=0';
            if (!in_array($skill, $free_skills, true)) {
                $args[0] = $free_skills[0] ?? 'werbung';
            }
        }

        $drills = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . " {$where}", ...$args), ARRAY_A);
        if (empty($drills)) {
            $fallback_where = $premium ? '' : 'WHERE is_premium=0';
            $drills = $wpdb->get_results('SELECT * FROM ' . SCO_DB::table('drills') . " {$fallback_where} LIMIT 1", ARRAY_A);
        }

        if (empty($drills)) {
            return [];
        }

        $filtered = array_values(array_filter($drills, function ($d) use ($last7) {
            return !in_array((int) $d['id'], $last7, true);
        }));
        $pool = !empty($filtered) ? $filtered : $drills;
        $pick = $pool[array_rand($pool)];
        $pick['self_check_questions'] = json_decode($pick['self_check_questions'], true);

        return $this->decorate_drill_with_script($user_id, $pick);
    }

    public function complete_drill(WP_REST_Request $request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $drill_id = (int) $request->get_param('drill_id');
        $answers = (array) $request->get_param('answers');

        if (!$drill_id || count($answers) < 2) {
            return new WP_REST_Response(['message' => __('Bitte beantworte mindestens 2 Checks.', 'sprecher-coach-os')], 400);
        }

        $drill = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . ' WHERE id=%d', $drill_id), ARRAY_A);
        if (!$drill) {
            return new WP_REST_Response(['message' => __('Drill nicht gefunden.', 'sprecher-coach-os')], 404);
        }

        $score = SCO_Utils::score_from_answers($answers);
        $xp = SCO_Utils::xp_for_completion((int) $drill['xp_reward'], $score);
        $today = SCO_Utils::today();

        $wpdb->insert(SCO_DB::table('user_completions'), [
            'user_id' => $user_id,
            'completion_date' => $today,
            'drill_id' => $drill_id,
            'score' => $score,
            'xp' => $xp,
            'answers_json' => wp_json_encode($answers),
        ]);

        $progress = $this->ensure_user_progress($user_id);
        $new_streak = 1;
        if (!empty($progress['last_completed_date'])) {
            $days_diff = floor((strtotime($today) - strtotime($progress['last_completed_date'])) / DAY_IN_SECONDS);
            if ($days_diff === 0) {
                $new_streak = (int) $progress['streak'];
            } elseif ($days_diff === 1) {
                $new_streak = (int) $progress['streak'] + 1;
            }
        }

        $weekly_count = (int) $progress['weekly_count'];
        $already_done_today = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . SCO_DB::table('user_completions') . ' WHERE user_id=%d AND completion_date=%s', $user_id, $today));
        if ($already_done_today === 1) {
            $weekly_count++;
        }

        $xp_total = (int) $progress['xp_total'] + $xp;
        $wpdb->update(SCO_DB::table('user_progress'), [
            'xp_total' => $xp_total,
            'streak' => $new_streak,
            'last_completed_date' => $today,
            'weekly_count' => $weekly_count,
            'weekly_reset_date' => SCO_Utils::week_monday($today),
            'updated_at' => current_time('mysql'),
        ], ['user_id' => $user_id]);

        $skill_progress = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_skill_progress') . ' WHERE user_id=%d AND skill_key=%s', $user_id, $drill['skill_key']), ARRAY_A);
        if (!$skill_progress) {
            $wpdb->insert(SCO_DB::table('user_skill_progress'), [
                'user_id' => $user_id,
                'skill_key' => sanitize_key($drill['skill_key']),
                'xp' => $xp,
                'level' => SCO_Utils::level_from_xp($xp),
            ]);
        } else {
            $skill_xp = (int) $skill_progress['xp'] + $xp;
            $wpdb->update(SCO_DB::table('user_skill_progress'), [
                'xp' => $skill_xp,
                'level' => SCO_Utils::level_from_xp($skill_xp),
            ], ['id' => (int) $skill_progress['id']]);
        }

        return rest_ensure_response([
            'score' => $score,
            'xp' => $xp,
            'feedback' => SCO_Utils::feedback_from_score($score),
            'streak' => $new_streak,
            'xp_total' => $xp_total,
            'level' => SCO_Utils::level_from_xp($xp_total),
        ]);
    }

    public function skilltree() {
        global $wpdb;

        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $skills = ['werbung', 'imagefilm', 'erklaervideo', 'elearning', 'telefon', 'hoerbuch', 'doku'];
        $free_skills = (array) SCO_Utils::get_settings()['free_skills_enabled'];

        $rows = [];
        foreach ($skills as $skill) {
            $progress = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_skill_progress') . ' WHERE user_id=%d AND skill_key=%s', $user_id, $skill), ARRAY_A);
            $rows[] = [
                'skill' => $skill,
                'level' => $progress ? (int) $progress['level'] : 1,
                'xp' => $progress ? (int) $progress['xp'] : 0,
                'locked' => !$premium && !in_array($skill, $free_skills, true),
            ];
        }

        return rest_ensure_response($rows);
    }

    public function missions() {
        global $wpdb;

        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $missions = $wpdb->get_results('SELECT * FROM ' . SCO_DB::table('missions') . ' ORDER BY id ASC', ARRAY_A);

        foreach ($missions as &$mission) {
            $mission['locked'] = !$premium && (int) $mission['is_premium'] === 1;
            $steps = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('mission_steps') . ' WHERE mission_id=%d ORDER BY step_order ASC', $mission['id']), ARRAY_A);
            $mission['steps'] = array_map([$this, 'normalize_mission_step'], $steps);
            $completed_days = $wpdb->get_col($wpdb->prepare('SELECT step_day FROM ' . SCO_DB::table('user_mission_steps') . ' WHERE user_id=%d AND mission_id=%d', $user_id, $mission['id']));
            $mission['completed_days'] = array_map('intval', $completed_days);
        }

        return rest_ensure_response([
            'items' => $missions,
            'premium_tooltip' => SCO_Utils::copy('premium_tooltips', 'locked_missions'),
        ]);
    }

    public function recommend_drill(WP_REST_Request $request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $skill = sanitize_key((string) $request->get_param('skill'));
        $category = sanitize_key((string) $request->get_param('category'));
        $recommended_drill_id = (int) $request->get_param('recommended_drill_id');
        $today = SCO_Utils::today();
        $settings = SCO_Utils::get_settings();
        $free_skills = (array) $settings['free_skills_enabled'];

        if (!$skill) {
            $skill = 'werbung';
        }
        if (!$premium && !in_array($skill, $free_skills, true)) {
            $skill = $free_skills[0] ?? 'werbung';
        }

        if ($recommended_drill_id > 0) {
            $by_id = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . ' WHERE id=%d LIMIT 1', $recommended_drill_id), ARRAY_A);
            if ($by_id) {
                if ($premium || (int) $by_id['is_premium'] === 0) {
                    $by_id['self_check_questions'] = json_decode($by_id['self_check_questions'], true);
                    return rest_ensure_response($this->decorate_drill_with_script($user_id, $by_id));
                }
            }
        }

        $last7 = $wpdb->get_col($wpdb->prepare('SELECT drill_id FROM ' . SCO_DB::table('user_completions') . ' WHERE user_id=%d AND completion_date >= DATE_SUB(%s, INTERVAL 7 DAY)', $user_id, $today));
        $last7 = array_map('intval', $last7);

        $where = ' WHERE skill_key=%s';
        $args = [$skill];
        if ($category) {
            $where .= ' AND category_key=%s';
            $args[] = $category;
        }
        if (!$premium) {
            $where .= ' AND is_premium=0';
        }

        $drills = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . $where, ...$args), ARRAY_A);
        if (empty($drills) && $category) {
            $args = [$skill];
            if (!$premium) {
                $drills = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . ' WHERE skill_key=%s AND is_premium=0', ...$args), ARRAY_A);
            } else {
                $drills = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . ' WHERE skill_key=%s', ...$args), ARRAY_A);
            }
        }

        if (empty($drills)) {
            return new WP_REST_Response(['message' => __('Kein passender Drill gefunden.', 'sprecher-coach-os')], 404);
        }

        $filtered = array_values(array_filter($drills, function ($drill) use ($last7) {
            return !in_array((int) $drill['id'], $last7, true);
        }));
        $pool = !empty($filtered) ? $filtered : $drills;
        $pick = $pool[array_rand($pool)];
        $pick['self_check_questions'] = json_decode($pick['self_check_questions'], true);

        return rest_ensure_response($this->decorate_drill_with_script($user_id, $pick));
    }

    private function decorate_drill_with_script($user_id, array $drill) {
        $resolved = sco_resolve_drill_text($user_id, $drill);
        $drill['script_text_resolved'] = $resolved['text'];
        $drill['script_source'] = $resolved['source'];
        $drill['script_title'] = $resolved['title'];

        return $drill;
    }

    public function alt_text(WP_REST_Request $request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $skill = sanitize_key((string) $request->get_param('skill'));
        if (!$skill) {
            $skill = 'werbung';
        }

        $variant = max(1, (int) $request->get_param('variant'));

        $drill = [
            'id' => 0,
            'skill_key' => $skill,
            'difficulty' => 1 + (($variant - 1) % 3),
            'title' => SCO_Utils::label_skill($skill) . ' Drill',
            'script_text' => '',
            'script_source' => 'pool',
        ];

        $items = $wpdb->get_results($wpdb->prepare(
            'SELECT id, title, content FROM ' . SCO_DB::table('library') . ' WHERE category_key=%s AND skill_key=%s ORDER BY id ASC',
            'script',
            $skill
        ), ARRAY_A);

        if (!empty($items)) {
            $index = abs(crc32($user_id . '|' . SCO_Utils::today() . '|' . $skill . '|alt|' . $variant)) % count($items);
            $selected = $items[$index];
            $text = trim(wp_strip_all_tags((string) ($selected['content'] ?? '')));
            if ($text !== '') {
                return rest_ensure_response([
                    'text' => sanitize_textarea_field($text),
                    'source' => 'library',
                    'title' => sanitize_text_field((string) ($selected['title'] ?? 'Bibliothekstext')),
                ]);
            }
        }

        $resolved = sco_resolve_drill_text($user_id, $drill);
        return rest_ensure_response($resolved);
    }

    private function normalize_mission_step(array $step) {
        $payload = json_decode((string) $step['checklist'], true);
        $tasks = [];

        if (is_array($payload) && array_keys($payload) === range(0, count($payload) - 1)) {
            $tasks = array_values(array_filter(array_map('sanitize_text_field', $payload)));
            $payload = ['tasks' => $tasks];
        }

        if (!is_array($payload)) {
            $payload = [];
        }

        $tasks = array_values(array_filter(array_map('sanitize_text_field', (array) ($payload['tasks'] ?? []))));

        return [
            'id' => (int) $step['id'],
            'mission_id' => (int) $step['mission_id'],
            'step_order' => (int) $step['step_order'],
            'day' => (int) $step['step_order'],
            'title' => sanitize_text_field($step['title']),
            'estimated_minutes' => max(0, (int) ($payload['estimated_minutes'] ?? 0)),
            'tasks' => $tasks,
            'drill_skill_key' => sanitize_key($payload['drill_skill_key'] ?? ''),
            'drill_category_key' => sanitize_key($payload['drill_category_key'] ?? ''),
            'recommended_drill_id' => max(0, (int) ($payload['recommended_drill_id'] ?? 0)),
            'library_item_id' => max(0, (int) ($payload['library_item_id'] ?? 0)),
            'script_text' => sanitize_textarea_field((string) ($payload['script_text'] ?? '')),
        ];
    }

    public function mission_step_complete(WP_REST_Request $request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $mission_id = (int) $request->get_param('mission_id');
        $step_day = (int) $request->get_param('step_day');

        if (!$mission_id || !$step_day) {
            return new WP_REST_Response(['message' => __('Ungültige Daten.', 'sprecher-coach-os')], 400);
        }

        $wpdb->query($wpdb->prepare(
            'INSERT INTO ' . SCO_DB::table('user_mission_steps') . ' (user_id, mission_id, step_day, completed_at) VALUES (%d, %d, %d, %s) ON DUPLICATE KEY UPDATE completed_at = VALUES(completed_at)',
            $user_id,
            $mission_id,
            $step_day,
            current_time('mysql')
        ));

        $progress = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_mission_progress') . ' WHERE user_id=%d AND mission_id=%d', $user_id, $mission_id), ARRAY_A);
        $completed_days = $wpdb->get_col($wpdb->prepare('SELECT step_day FROM ' . SCO_DB::table('user_mission_steps') . ' WHERE user_id=%d AND mission_id=%d ORDER BY step_day ASC', $user_id, $mission_id));
        $next_step = !empty($completed_days) ? max(array_map('intval', $completed_days)) + 1 : 1;

        if (!$progress) {
            $wpdb->insert(SCO_DB::table('user_mission_progress'), [
                'user_id' => $user_id,
                'mission_id' => $mission_id,
                'current_step' => $next_step,
                'completed_steps' => wp_json_encode(array_map('intval', $completed_days)),
                'updated_at' => current_time('mysql'),
            ]);
        } else {
            $wpdb->update(SCO_DB::table('user_mission_progress'), [
                'current_step' => $next_step,
                'completed_steps' => wp_json_encode(array_map('intval', $completed_days)),
                'updated_at' => current_time('mysql'),
            ], ['id' => (int) $progress['id']]);
        }

        return rest_ensure_response(['success' => true, 'completed_days' => array_map('intval', $completed_days)]);
    }

    public function library(WP_REST_Request $request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $settings = SCO_Utils::get_settings();
        $daily_limit = $premium ? 999 : (int) $settings['free_library_limit'];

        $category = sanitize_key((string) $request->get_param('category'));
        $skill = sanitize_key((string) $request->get_param('skill'));
        $difficulty = (int) $request->get_param('difficulty');

        $where = ['1=1'];
        $args = [];

        if ($category) {
            $where[] = 'category_key=%s';
            $args[] = $category;
        }
        if ($skill) {
            $where[] = 'skill_key=%s';
            $args[] = $skill;
        }
        if ($difficulty > 0) {
            $where[] = 'difficulty=%d';
            $args[] = $difficulty;
        }
        if (!$premium) {
            $where[] = 'is_premium=0';
        }

        $sql = 'SELECT * FROM ' . SCO_DB::table('library') . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 50';
        $query = empty($args) ? $sql : $wpdb->prepare($sql, ...$args);
        $items = $wpdb->get_results($query, ARRAY_A);

        $today = SCO_Utils::today();
        $opens_count = (int) $wpdb->get_var($wpdb->prepare('SELECT open_count FROM ' . SCO_DB::table('user_library_opens') . ' WHERE user_id=%d AND open_date=%s', $user_id, $today));

        return rest_ensure_response([
            'items' => $items,
            'premium' => $premium,
            'daily_limit' => $daily_limit,
            'opens_count' => $opens_count,
            'limit_reached' => !$premium && $opens_count >= $daily_limit,
            'checkout_url' => sco_checkout_url(),
            'copy' => [
                'locked_library' => SCO_Utils::copy('premium_tooltips', 'locked_library'),
                'free_limit_reached' => SCO_Utils::copy('premium_tooltips', 'free_limit_reached'),
                'upgrade_primary' => SCO_Utils::copy('cta', 'upgrade_primary'),
            ],
        ]);
    }

    public function library_open(WP_REST_Request $request) {
        global $wpdb;

        $user_id = get_current_user_id();
        $item_id = (int) $request->get_param('item_id');
        if (!$item_id) {
            return new WP_REST_Response(['message' => __('Inhalt nicht gefunden.', 'sprecher-coach-os')], 400);
        }

        $premium = SCO_Permissions::is_premium_user($user_id);
        $daily_limit = $premium ? 999 : (int) SCO_Utils::get_settings()['free_library_limit'];
        $today = SCO_Utils::today();

        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_library_opens') . ' WHERE user_id=%d AND open_date=%s', $user_id, $today), ARRAY_A);
        $count = $row ? (int) $row['open_count'] : 0;

        if (!$premium && $count >= $daily_limit) {
            return new WP_REST_Response([
                'message' => SCO_Utils::copy('premium_tooltips', 'free_limit_reached'),
                'limit_reached' => true,
            ], 403);
        }

        if (!$row) {
            $wpdb->insert(SCO_DB::table('user_library_opens'), [
                'user_id' => $user_id,
                'open_date' => $today,
                'open_count' => 1,
                'updated_at' => current_time('mysql'),
            ]);
            $count = 1;
        } else {
            $count++;
            $wpdb->update(SCO_DB::table('user_library_opens'), [
                'open_count' => $count,
                'updated_at' => current_time('mysql'),
            ], ['id' => (int) $row['id']]);
        }

        return rest_ensure_response([
            'success' => true,
            'opens_count' => $count,
            'daily_limit' => $daily_limit,
            'limit_reached' => !$premium && $count >= $daily_limit,
        ]);
    }
}
