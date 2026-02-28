<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_API {
    public function register_hooks() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route('sco/v1', '/dashboard', ['methods' => 'GET', 'callback' => [$this, 'dashboard'], 'permission_callback' => '__return_true']);
        register_rest_route('sco/v1', '/complete-drill', ['methods' => 'POST', 'callback' => [$this, 'complete_drill'], 'permission_callback' => [$this, 'must_be_logged_in']]);
        register_rest_route('sco/v1', '/skilltree', ['methods' => 'GET', 'callback' => [$this, 'skilltree'], 'permission_callback' => '__return_true']);
        register_rest_route('sco/v1', '/missions', ['methods' => 'GET', 'callback' => [$this, 'missions'], 'permission_callback' => '__return_true']);
        register_rest_route('sco/v1', '/missions/step-complete', ['methods' => 'POST', 'callback' => [$this, 'mission_step_complete'], 'permission_callback' => [$this, 'must_be_logged_in']]);
        register_rest_route('sco/v1', '/library', ['methods' => 'GET', 'callback' => [$this, 'library'], 'permission_callback' => '__return_true']);
    }

    public function must_be_logged_in() {
        return is_user_logged_in();
    }

    public function dashboard() {
        global $wpdb;
        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $drill = $this->get_daily_drill($user_id, $premium);

        $progress = [
            'xp_total' => 0,
            'streak' => 0,
            'weekly_count' => 0,
            'weekly_goal' => (int) SCO_Utils::get_settings()['weekly_goal'],
            'level' => 1,
        ];

        if ($user_id) {
            $progressRow = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_progress') . ' WHERE user_id=%d', $user_id), ARRAY_A);
            if ($progressRow) {
                $progress['xp_total'] = (int) $progressRow['xp_total'];
                $progress['streak'] = (int) $progressRow['streak'];
                $progress['weekly_count'] = (int) $progressRow['weekly_count'];
                $progress['level'] = SCO_Utils::level_from_xp($progress['xp_total']);
            }
        }

        return rest_ensure_response([
            'drill' => $drill,
            'progress' => $progress,
            'premium' => $premium,
            'teaser' => 'Mit Premium erhältst du alle Skill-Pfade, Missionen ohne Limit und volle Bibliothek.',
        ]);
    }

    private function get_daily_drill($user_id, $premium) {
        global $wpdb;
        $today = SCO_Utils::today();
        $settings = SCO_Utils::get_settings();
        $freeSkills = (array) $settings['free_skills_enabled'];

        $skill = 'werbung';
        if ($user_id) {
            $row = $wpdb->get_row($wpdb->prepare('SELECT preferred_skill FROM ' . SCO_DB::table('user_progress') . ' WHERE user_id=%d', $user_id), ARRAY_A);
            if ($row && !empty($row['preferred_skill'])) {
                $skill = sanitize_key($row['preferred_skill']);
            }
        }

        $last7 = [];
        if ($user_id) {
            $last7 = $wpdb->get_col($wpdb->prepare('SELECT drill_id FROM ' . SCO_DB::table('user_completions') . ' WHERE user_id=%d AND completion_date >= DATE_SUB(%s, INTERVAL 7 DAY)', $user_id, $today));
            $last7 = array_map('intval', $last7);
        }

        $seed = crc32($user_id . '|' . $today . '|' . $skill);
        mt_srand($seed);

        $where = 'WHERE skill_key=%s';
        $args = [$skill];
        if (!$premium) {
            $where .= ' AND is_premium=0';
            if (!in_array($skill, $freeSkills, true)) {
                $args[0] = $freeSkills[0] ?? 'werbung';
            }
        }

        $drills = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . " $where", ...$args), ARRAY_A);
        if (empty($drills)) {
            $drills = $wpdb->get_results('SELECT * FROM ' . SCO_DB::table('drills') . ' LIMIT 1', ARRAY_A);
        }

        $filtered = array_values(array_filter($drills, function ($d) use ($last7) {
            return !in_array((int) $d['id'], $last7, true);
        }));
        $pool = !empty($filtered) ? $filtered : $drills;

        $pick = $pool[array_rand($pool)];
        $pick['self_check_questions'] = json_decode($pick['self_check_questions'], true);

        return $pick;
    }

    public function complete_drill(WP_REST_Request $request) {
        global $wpdb;
        $user_id = get_current_user_id();

        $drill_id = (int) $request->get_param('drill_id');
        $answers = (array) $request->get_param('answers');
        $drill = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('drills') . ' WHERE id=%d', $drill_id), ARRAY_A);

        if (!$drill) {
            return new WP_REST_Response(['message' => 'Drill nicht gefunden.'], 404);
        }

        $score = SCO_Utils::score_from_answers($answers);
        $xp = SCO_Utils::xp_for_completion((int) $drill['xp_reward'], $score);
        $today = SCO_Utils::today();
        $isPremium = SCO_Permissions::is_premium_user($user_id);

        $wpdb->insert(SCO_DB::table('user_completions'), [
            'user_id' => $user_id,
            'completion_date' => $today,
            'drill_id' => $drill_id,
            'score' => $score,
            'xp' => $xp,
            'answers_json' => wp_json_encode($answers),
        ]);

        $progress = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_progress') . ' WHERE user_id=%d', $user_id), ARRAY_A);
        if (!$progress) {
            $progress = ['xp_total' => 0, 'streak' => 0, 'last_completed_date' => null, 'weekly_count' => 0, 'weekly_reset_date' => null, 'preferred_skill' => 'werbung'];
            $wpdb->insert(SCO_DB::table('user_progress'), ['user_id' => $user_id, 'preferred_skill' => 'werbung']);
        }

        $newStreak = 1;
        if (!empty($progress['last_completed_date'])) {
            $daysDiff = (strtotime($today) - strtotime($progress['last_completed_date'])) / DAY_IN_SECONDS;
            if ((int) $daysDiff === 0) {
                $newStreak = (int) $progress['streak'];
                if ($isPremium) {
                    $xp += 3;
                }
            } elseif ((int) $daysDiff === 1) {
                $newStreak = (int) $progress['streak'] + 1;
            }
        }

        $weekMonday = SCO_Utils::week_monday($today);
        $weeklyCount = (int) $progress['weekly_count'];
        if ($progress['weekly_reset_date'] !== $weekMonday) {
            $weeklyCount = 0;
        }

        $alreadyDoneToday = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . SCO_DB::table('user_completions') . ' WHERE user_id=%d AND completion_date=%s', $user_id, $today));
        if ($alreadyDoneToday === 1) {
            $weeklyCount++;
        }

        $xpTotal = (int) $progress['xp_total'] + $xp;
        $wpdb->update(SCO_DB::table('user_progress'), [
            'xp_total' => $xpTotal,
            'streak' => $newStreak,
            'last_completed_date' => $today,
            'weekly_count' => $weeklyCount,
            'weekly_reset_date' => $weekMonday,
            'updated_at' => current_time('mysql'),
        ], ['user_id' => $user_id]);

        $skillProgress = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_skill_progress') . ' WHERE user_id=%d AND skill_key=%s', $user_id, $drill['skill_key']), ARRAY_A);
        if (!$skillProgress) {
            $wpdb->insert(SCO_DB::table('user_skill_progress'), [
                'user_id' => $user_id,
                'skill_key' => $drill['skill_key'],
                'xp' => $xp,
                'level' => SCO_Utils::level_from_xp($xp),
            ]);
        } else {
            $skillXp = (int) $skillProgress['xp'] + $xp;
            $wpdb->update(SCO_DB::table('user_skill_progress'), ['xp' => $skillXp, 'level' => SCO_Utils::level_from_xp($skillXp)], ['id' => $skillProgress['id']]);
        }

        return rest_ensure_response([
            'score' => $score,
            'xp' => $xp,
            'feedback' => SCO_Utils::feedback_from_score($score),
            'streak' => $newStreak,
            'xp_total' => $xpTotal,
            'level' => SCO_Utils::level_from_xp($xpTotal),
        ]);
    }

    public function skilltree() {
        global $wpdb;
        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $skills = ['werbung', 'imagefilm', 'erklaervideo', 'elearning', 'telefon', 'hoerbuch', 'doku'];
        $freeSkills = (array) SCO_Utils::get_settings()['free_skills_enabled'];

        $rows = [];
        foreach ($skills as $skill) {
            $progress = $user_id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_skill_progress') . ' WHERE user_id=%d AND skill_key=%s', $user_id, $skill), ARRAY_A) : null;
            $rows[] = [
                'skill' => $skill,
                'level' => $progress ? (int) $progress['level'] : 1,
                'xp' => $progress ? (int) $progress['xp'] : 0,
                'locked' => !$premium && !in_array($skill, $freeSkills, true),
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
            $mission['steps'] = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('mission_steps') . ' WHERE mission_id=%d ORDER BY step_order ASC', $mission['id']), ARRAY_A);
            if ($user_id) {
                $mission['progress'] = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_mission_progress') . ' WHERE user_id=%d AND mission_id=%d', $user_id, $mission['id']), ARRAY_A);
            }
        }

        return rest_ensure_response($missions);
    }

    public function mission_step_complete(WP_REST_Request $request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $mission_id = (int) $request->get_param('mission_id');
        $step_order = (int) $request->get_param('step_order');

        if (!$mission_id || !$step_order) {
            return new WP_REST_Response(['message' => 'Ungültige Daten.'], 400);
        }

        $progress = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . SCO_DB::table('user_mission_progress') . ' WHERE user_id=%d AND mission_id=%d', $user_id, $mission_id), ARRAY_A);
        if (!$progress) {
            $completed = [$step_order];
            $wpdb->insert(SCO_DB::table('user_mission_progress'), [
                'user_id' => $user_id,
                'mission_id' => $mission_id,
                'current_step' => $step_order + 1,
                'completed_steps' => wp_json_encode($completed),
            ]);
        } else {
            $completed = json_decode($progress['completed_steps'], true);
            $completed = is_array($completed) ? $completed : [];
            if (!in_array($step_order, $completed, true)) {
                $completed[] = $step_order;
            }
            $wpdb->update(SCO_DB::table('user_mission_progress'), [
                'current_step' => max((int) $progress['current_step'], $step_order + 1),
                'completed_steps' => wp_json_encode($completed),
                'updated_at' => current_time('mysql'),
            ], ['id' => $progress['id']]);
        }

        return rest_ensure_response(['success' => true]);
    }

    public function library(WP_REST_Request $request) {
        global $wpdb;
        $user_id = get_current_user_id();
        $premium = SCO_Permissions::is_premium_user($user_id);
        $settings = SCO_Utils::get_settings();
        $limit = $premium ? 50 : (int) $settings['free_library_limit'];

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

        $sql = 'SELECT * FROM ' . SCO_DB::table('library') . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY RAND() LIMIT %d';
        $args[] = $limit;

        $query = $wpdb->prepare($sql, ...$args);
        $items = $wpdb->get_results($query, ARRAY_A);

        return rest_ensure_response(['items' => $items, 'premium' => $premium, 'limit' => $limit]);
    }
}
