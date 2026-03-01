<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Seeder {
    const TARGET_SEED_VERSION = 3;

    public static function seed_if_empty() {
        $stored_version = (int) get_option('sco_seed_version', 0);
        $should_seed = self::tables_are_empty() || $stored_version < self::TARGET_SEED_VERSION;

        if (!$should_seed) {
            return;
        }

        self::import_seed(false);
        update_option('sco_seed_version', self::TARGET_SEED_VERSION);
    }

    public static function import_seed($overwrite = false) {
        $report = [
            'drills' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'library' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'missions' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
            'mission_steps' => ['inserted' => 0, 'updated' => 0, 'skipped' => 0],
        ];

        self::import_drills($overwrite, $report);
        self::import_library($overwrite, $report);
        self::import_missions($overwrite, $report);
        self::ensure_drill_script_texts($report);
        self::ensure_library_script_items($report, $overwrite);

        update_option('sco_seed_version', self::TARGET_SEED_VERSION);

        return $report;
    }

    private static function tables_are_empty() {
        global $wpdb;

        $tables = [
            SCO_DB::table('drills'),
            SCO_DB::table('library'),
            SCO_DB::table('missions'),
        ];

        foreach ($tables as $table) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            if ($count > 0) {
                return false;
            }
        }

        return true;
    }

    private static function import_drills($overwrite, array &$report) {
        global $wpdb;

        foreach (self::drills_seed()['drills'] as $drill) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . SCO_DB::table('drills') . ' WHERE skill_key=%s AND category_key=%s AND title=%s LIMIT 1',
                $drill['skill_key'],
                $drill['category_key'],
                $drill['title']
            ));

            $data = [
                'skill_key' => sanitize_key($drill['skill_key']),
                'category_key' => sanitize_key($drill['category_key']),
                'difficulty' => max(1, min(3, (int) $drill['difficulty'])),
                'duration_min' => max(1, (int) $drill['duration_min']),
                'xp_reward' => max(1, (int) $drill['xp_reward']),
                'title' => sanitize_text_field($drill['title']),
                'description' => sanitize_textarea_field($drill['description']),
                'self_check_questions' => wp_json_encode($drill['self_check_questions']),
                'is_premium' => 0,
            ];

            if ($existing_id > 0) {
                if ($overwrite) {
                    $wpdb->update(SCO_DB::table('drills'), $data, ['id' => $existing_id]);
                    $report['drills']['updated']++;
                } else {
                    $report['drills']['skipped']++;
                }
                continue;
            }

            $wpdb->insert(SCO_DB::table('drills'), $data);
            $report['drills']['inserted']++;
        }
    }

    private static function import_library($overwrite, array &$report) {
        global $wpdb;

        foreach (self::library_seed()['library_items'] as $item) {
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . SCO_DB::table('library') . ' WHERE category_key=%s AND skill_key=%s AND title=%s LIMIT 1',
                sanitize_key($item['type']),
                sanitize_key($item['skill_key']),
                $item['title']
            ));

            $data = [
                'category_key' => sanitize_key($item['type']),
                'skill_key' => sanitize_key($item['skill_key']),
                'difficulty' => max(1, min(3, (int) $item['difficulty'])),
                'duration_min' => max(1, (int) $item['duration_min']),
                'title' => sanitize_text_field($item['title']),
                'content' => wp_kses_post($item['content']),
                'is_premium' => 0,
            ];

            if ($existing_id > 0) {
                if ($overwrite) {
                    $wpdb->update(SCO_DB::table('library'), $data, ['id' => $existing_id]);
                    $report['library']['updated']++;
                } else {
                    $report['library']['skipped']++;
                }
                continue;
            }

            $wpdb->insert(SCO_DB::table('library'), $data);
            $report['library']['inserted']++;
        }
    }

    private static function import_missions($overwrite, array &$report) {
        global $wpdb;

        foreach (self::missions_seed()['missions'] as $mission) {
            $skill_key = sanitize_key($mission['skill_key'] ?? '');
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . SCO_DB::table('missions') . ' WHERE title=%s LIMIT 1',
                $mission['title']
            ));

            if ($existing_id === 0 && $skill_key) {
                $candidate_ids = $wpdb->get_col($wpdb->prepare(
                    'SELECT m.id FROM ' . SCO_DB::table('missions') . ' m INNER JOIN ' . SCO_DB::table('mission_steps') . ' ms ON m.id = ms.mission_id WHERE m.title=%s',
                    $mission['title']
                ));

                foreach ((array) $candidate_ids as $candidate_id) {
                    $first_step = $wpdb->get_var($wpdb->prepare(
                        'SELECT checklist FROM ' . SCO_DB::table('mission_steps') . ' WHERE mission_id=%d ORDER BY step_order ASC LIMIT 1',
                        (int) $candidate_id
                    ));
                    $first_step_payload = json_decode((string) $first_step, true);
                    $candidate_skill = '';
                    if (is_array($first_step_payload) && array_keys($first_step_payload) !== range(0, count($first_step_payload) - 1)) {
                        $candidate_skill = sanitize_key($first_step_payload['drill_skill_key'] ?? '');
                    }

                    if ($candidate_skill === $skill_key) {
                        $existing_id = (int) $candidate_id;
                        break;
                    }
                }
            }

            $data = [
                'title' => sanitize_text_field($mission['title']),
                'description' => sanitize_text_field($mission['short_description']),
                'duration_days' => count($mission['steps']),
                'is_bonus' => 0,
                'is_premium' => !empty($mission['is_premium']) ? 1 : 0,
            ];

            $mission_id = $existing_id;
            if ($existing_id > 0) {
                if ($overwrite) {
                    $wpdb->update(SCO_DB::table('missions'), $data, ['id' => $existing_id]);
                    $report['missions']['updated']++;
                } else {
                    $report['missions']['skipped']++;
                }
            } else {
                $wpdb->insert(SCO_DB::table('missions'), $data);
                $mission_id = (int) $wpdb->insert_id;
                $report['missions']['inserted']++;
            }

            foreach ($mission['steps'] as $step) {
                self::upsert_mission_step($mission_id, $step, $overwrite, $report);
            }
        }
    }

    private static function upsert_mission_step($mission_id, array $step, $overwrite, array &$report) {
        global $wpdb;

        $existing_id = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT id FROM ' . SCO_DB::table('mission_steps') . ' WHERE mission_id=%d AND step_order=%d LIMIT 1',
            $mission_id,
            (int) $step['day']
        ));

        $tasks = !empty($step['tasks'])
            ? array_values(array_map('sanitize_text_field', (array) $step['tasks']))
            : array_values(array_map('sanitize_text_field', (array) ($step['checklist'] ?? [])));

        $data = [
            'mission_id' => $mission_id,
            'step_order' => (int) $step['day'],
            'title' => sanitize_text_field($step['title']),
            'description' => sanitize_text_field($step['title']),
            'checklist' => wp_json_encode([
                'day' => (int) $step['day'],
                'title' => sanitize_text_field($step['title']),
                'estimated_minutes' => isset($step['estimated_minutes']) ? max(0, (int) $step['estimated_minutes']) : 0,
                'tasks' => $tasks,
                'drill_skill_key' => sanitize_key($step['drill_skill_key'] ?? ''),
                'drill_category_key' => sanitize_key($step['drill_category_key'] ?? ''),
                'recommended_drill_id' => max(0, (int) ($step['recommended_drill_id'] ?? 0)),
                'library_item_id' => max(0, (int) ($step['library_item_id'] ?? 0)),
                'script_text' => sanitize_textarea_field((string) ($step['script_text'] ?? '')),
            ]),
        ];

        if ($existing_id > 0) {
            if ($overwrite) {
                $wpdb->update(SCO_DB::table('mission_steps'), $data, ['id' => $existing_id]);
                $report['mission_steps']['updated']++;
            } else {
                $report['mission_steps']['skipped']++;
            }
            return;
        }

        $wpdb->insert(SCO_DB::table('mission_steps'), $data);
        $report['mission_steps']['inserted']++;
    }

    private static function ensure_drill_script_texts(array &$report) {
        global $wpdb;

        $table = SCO_DB::table('drills');
        $has_script_text = (bool) $wpdb->get_var($wpdb->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE %s', 'script_text'));
        if (!$has_script_text) {
            return;
        }

        $pool_map = SCO_Utils::text_pools();
        $drills = $wpdb->get_results('SELECT id, skill_key, title FROM ' . $table . " WHERE script_text IS NULL OR TRIM(script_text)='' ORDER BY id ASC", ARRAY_A);

        foreach ($drills as $drill) {
            $skill = sanitize_key((string) ($drill['skill_key'] ?? 'werbung'));
            $pool = $pool_map[$skill] ?? $pool_map['werbung'];
            $seed = implode('|', [$drill['id'], $skill, $drill['title']]);
            $idx = abs(crc32($seed)) % count($pool);
            $wpdb->update($table, [
                'script_text' => sanitize_textarea_field($pool[$idx]),
                'script_source' => 'pool',
                'script_meta' => wp_json_encode(['seed' => $seed, 'skill' => $skill]),
            ], ['id' => (int) $drill['id']]);
            $report['drills']['updated']++;
        }
    }

    private static function ensure_library_script_items(array &$report, $overwrite) {
        global $wpdb;

        $skills = ['werbung', 'imagefilm', 'erklaervideo', 'elearning', 'telefon', 'hoerbuch', 'doku'];
        $pool_map = SCO_Utils::text_pools();

        foreach ($skills as $skill) {
            $existing = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . SCO_DB::table('library') . ' WHERE category_key=%s AND skill_key=%s',
                'script',
                $skill
            ));

            if ($existing >= 15 && !$overwrite) {
                continue;
            }

            $pool = $pool_map[$skill] ?? [];
            if (empty($pool)) {
                continue;
            }

            for ($i = $existing; $i < 15; $i++) {
                $text = $pool[$i % count($pool)];
                $wpdb->insert(SCO_DB::table('library'), [
                    'category_key' => 'script',
                    'skill_key' => $skill,
                    'difficulty' => ($i % 3) + 1,
                    'duration_min' => 3,
                    'title' => sprintf('%s Script %d', SCO_Utils::label_skill($skill), $i + 1),
                    'content' => wp_kses_post($text),
                    'is_premium' => 0,
                ]);
                $report['library']['inserted']++;
            }
        }
    }

    private static function drills_seed() {
        return json_decode('{
  "drills": [
    {"skill_key":"werbung","category_key":"energie","difficulty":1,"duration_min":6,"xp_reward":15,"title":"Energie auf Knopfdruck (3 Takes)","description":"Sprich denselben Satz drei Mal: 1) neutral, 2) +20% Energie, 3) +40% Energie. Ziel: Energie erhöhen, ohne schneller zu werden.","self_check_questions":[{"type":"scale_1_5","label":"Klang es in Take 2 klar energiereicher als in Take 1?"},{"type":"scale_1_5","label":"Blieb die Verständlichkeit trotz mehr Energie stabil?"},{"type":"checkbox_multi","label":"Was hat am meisten geholfen?","options":["Mehr Lächeln","Mehr Atemstütze","Mehr Konsonanten","Mehr Fokus auf Schlüsselwörter"]}]},
    {"skill_key":"werbung","category_key":"smile","difficulty":1,"duration_min":5,"xp_reward":12,"title":"Lächeln hörbar machen","description":"Sprich 2 kurze Sätze und halte dabei ein leichtes, echtes Lächeln. Wiederhole ohne Lächeln. Ziel: den Unterschied hören/fühlen.","self_check_questions":[{"type":"scale_1_5","label":"War der Take mit Lächeln hörbar freundlicher?"},{"type":"scale_1_5","label":"Klang es natürlich (nicht künstlich)?"},{"type":"checkbox_multi","label":"Woran hast du es gemerkt?","options":["Hellere Stimme","Mehr Leichtigkeit","Bessere Artikulation","Mehr Sympathie"]}]},
    {"skill_key":"werbung","category_key":"betonung","difficulty":2,"duration_min":8,"xp_reward":22,"title":"Keyword-Fokus (2 Schlüsselwörter)","description":"Markiere in einem Text zwei Schlüsselwörter. Sprich so, dass NUR diese zwei Wörter klar herausstechen – ohne alles andere zu überbetonen.","self_check_questions":[{"type":"scale_1_5","label":"Waren die beiden Keywords klar führend?"},{"type":"scale_1_5","label":"Blieb der Rest ruhig und stabil?"},{"type":"checkbox_multi","label":"Welche Technik hast du genutzt?","options":["Mini-Pause davor","Leichte Tonhöhenbewegung","Mehr Konsonanten","Mehr Lautstärke (sehr subtil)"]}]},
    {"skill_key":"imagefilm","category_key":"pausen","difficulty":1,"duration_min":7,"xp_reward":16,"title":"Pausen, die hochwertig klingen","description":"Sprich 6 Sätze. Setze nach jedem 2. Satz eine bewusste, kurze Pause (0,3–0,5s). Ziel: Ruhe + Wertigkeit.","self_check_questions":[{"type":"scale_1_5","label":"Klang es hochwertiger/ruhiger als ohne Pausen?"},{"type":"scale_1_5","label":"Waren die Pausen an sinnvollen Stellen?"},{"type":"checkbox_multi","label":"Wie wirkten die Pausen?","options":["Professionell","Zu lang","Zu kurz","Hat den Sinn besser transportiert"]}]},
    {"skill_key":"imagefilm","category_key":"textverstaendnis","difficulty":2,"duration_min":9,"xp_reward":24,"title":"Bild im Kopf (Story-Read)","description":"Lies den Text einmal still und stell dir 3 konkrete Bilder vor. Sprich dann – als würdest du genau diese Bilder beschreiben.","self_check_questions":[{"type":"scale_1_5","label":"War der Vortrag bildhafter/„filmischer“?"},{"type":"scale_1_5","label":"Hat deine Stimme mehr „Erzählfluss“ bekommen?"},{"type":"checkbox_multi","label":"Was hat sich verbessert?","options":["Tonhöhen-Melodie","Pausen","Tempo","Emotionale Wärme"]}]},
    {"skill_key":"erklaervideo","category_key":"artikulation","difficulty":1,"duration_min":6,"xp_reward":14,"title":"Klarheit ohne Härte (Konsonanten)","description":"Sprich 8 kurze Fachbegriffe deutlich, aber ohne zu pressen. Ziel: klare Konsonanten, weiche Gesamtwirkung.","self_check_questions":[{"type":"scale_1_5","label":"War die Artikulation deutlich, aber angenehm?"},{"type":"scale_1_5","label":"Hast du weniger „geklackert“/übertrieben?"},{"type":"checkbox_multi","label":"Worauf hast du geachtet?","options":["Leichte Lippenarbeit","Zunge präzise","Kiefer locker","Ruhiger Atem"]}]},
    {"skill_key":"erklaervideo","category_key":"pacing","difficulty":2,"duration_min":10,"xp_reward":26,"title":"Tempo stabil halten (Metronom-Gefühl)","description":"Sprich 60–90 Sekunden Text. Ziel: konstantes Tempo. Tipp: Stell dir ein inneres Metronom vor. Keine Beschleunigung am Satzende.","self_check_questions":[{"type":"scale_1_5","label":"Blieb das Tempo über den gesamten Text stabil?"},{"type":"scale_1_5","label":"Hast du am Ende weniger beschleunigt?"},{"type":"checkbox_multi","label":"Wo bist du aus dem Tempo gerutscht?","options":["Bei Aufzählungen","Bei langen Sätzen","Bei schwierigen Wörtern","Am Satzende"]}]},
    {"skill_key":"elearning","category_key":"pausen","difficulty":1,"duration_min":8,"xp_reward":18,"title":"Lern-Pausen (Verarbeiten lassen)","description":"Lies 10 Sätze. Nach jedem 3. Satz: kurze Verarbeitungs-Pause. Ziel: Zuhörer:innen Raum geben.","self_check_questions":[{"type":"scale_1_5","label":"Wirkten die Pausen didaktisch sinnvoll?"},{"type":"scale_1_5","label":"Klangst du ruhig und sicher?"},{"type":"checkbox_multi","label":"Was war schwierig?","options":["Pausen zu kurz","Pausen zu lang","Zu monoton","Zu viele Betonungen"]}]},
    {"skill_key":"elearning","category_key":"betonung","difficulty":2,"duration_min":9,"xp_reward":24,"title":"Didaktische Betonung (1 Satz, 3 Bedeutungen)","description":"Nimm einen Satz und betone je eine andere Information als Kern. Ziel: Zuhörer-Lenkung statt Schauspiel.","self_check_questions":[{"type":"scale_1_5","label":"Waren die Varianten klar unterscheidbar?"},{"type":"scale_1_5","label":"Klang es seriös (nicht gespielt)?"},{"type":"checkbox_multi","label":"Welche Variante war am stärksten?","options":["Variante 1","Variante 2","Variante 3"]}]},
    {"skill_key":"telefon","category_key":"smile","difficulty":1,"duration_min":5,"xp_reward":12,"title":"Telefonansage: freundlich & klar","description":"Sprich eine 20–30 Sekunden Ansage. Fokus: freundlich, ruhig, klar. Kein Verkaufsdruck.","self_check_questions":[{"type":"scale_1_5","label":"Klangst du freundlich ohne übertrieben zu wirken?"},{"type":"scale_1_5","label":"War alles gut verständlich?"},{"type":"checkbox_multi","label":"Was würdest du verbessern?","options":["Langsamer","Mehr Lächeln","Mehr Pausen","Klarere Zahlen/Begriffe"]}]},
    {"skill_key":"telefon","category_key":"artikulation","difficulty":2,"duration_min":7,"xp_reward":20,"title":"Zahlen & Namen (perfekt verständlich)","description":"Übe: Telefonnummern, Öffnungszeiten, E-Mail-Adressen. Ziel: 100% Klarheit, gute Rhythmik.","self_check_questions":[{"type":"scale_1_5","label":"Waren Zahlen/Namen sofort verständlich?"},{"type":"scale_1_5","label":"Klang der Rhythmus angenehm (nicht abgehackt)?"},{"type":"checkbox_multi","label":"Welche Stelle war am schwierigsten?","options":["Telefonnummer","E-Mail","Öffnungszeiten","Straßenname"]}]},
    {"skill_key":"hoerbuch","category_key":"textverstaendnis","difficulty":2,"duration_min":10,"xp_reward":26,"title":"Erzählhaltung (neutral, aber fesselnd)","description":"Lies 90 Sekunden Erzähltext. Ziel: ruhig, klar, mit feiner Spannung – ohne Theater.","self_check_questions":[{"type":"scale_1_5","label":"War die Haltung konstant und angenehm?"},{"type":"scale_1_5","label":"Hast du Spannung gehalten ohne zu spielen?"},{"type":"checkbox_multi","label":"Was hat geholfen?","options":["Pausen","Leichte Melodie","Bild im Kopf","Konsonanten klar"]}]},
    {"skill_key":"hoerbuch","category_key":"pacing","difficulty":3,"duration_min":12,"xp_reward":30,"title":"Langer Atem: 3 Minuten stabil","description":"Lies 3 Minuten am Stück. Ziel: Tempo, Lautstärke und Haltung stabil – keine Müdigkeit im Satzbau.","self_check_questions":[{"type":"scale_1_5","label":"Blieben Tempo und Lautstärke stabil?"},{"type":"scale_1_5","label":"Wurdest du zum Ende hin „flacher“?"},{"type":"checkbox_multi","label":"Wo kam die Instabilität?","options":["Atem","Tempo","Artikulation","Konzentration"]}]},
    {"skill_key":"doku","category_key":"pausen","difficulty":2,"duration_min":8,"xp_reward":22,"title":"Dokustimme: Autorität durch Ruhe","description":"Sprich 60–90 Sekunden Doku-Text. Ziel: ruhige Autorität – Pausen bewusst setzen.","self_check_questions":[{"type":"scale_1_5","label":"Klang es ruhig und souverän?"},{"type":"scale_1_5","label":"Waren die Pausen dramaturgisch sinnvoll?"},{"type":"checkbox_multi","label":"Welche Wirkung hattest du?","options":["Seriös","Zu monoton","Zu schnell","Sehr stimmig"]}]},
    {"skill_key":"werbung","category_key":"pacing","difficulty":2,"duration_min":7,"xp_reward":20,"title":"Werbung: Punchy ohne zu hetzen","description":"Sprich 30–45 Sekunden Werbetext: klar, zackig, aber nicht gehetzt. Fokus: kurze Mikro-Pausen statt Tempo.","self_check_questions":[{"type":"scale_1_5","label":"Klang es schnell im Gefühl, aber nicht hektisch?"},{"type":"scale_1_5","label":"Waren Mikro-Pausen sauber?"},{"type":"checkbox_multi","label":"Wodurch wurde es besser?","options":["Mikro-Pausen","Keywords","Lächeln","Konsonanten"]}]},
    {"skill_key":"imagefilm","category_key":"energie","difficulty":2,"duration_min":8,"xp_reward":22,"title":"Imagefilm: Wärme & Vertrauen","description":"Sprich 60 Sekunden Imagefilm: warm, einladend, vertrauensvoll. Keine Werbe-Hektik.","self_check_questions":[{"type":"scale_1_5","label":"Klang es warm/vertrauensvoll?"},{"type":"scale_1_5","label":"Hattest du genug Ruhe im Text?"},{"type":"checkbox_multi","label":"Was würdest du ändern?","options":["Mehr Wärme","Mehr Pausen","Klarere Keywords","Weniger Druck"]}]},
    {"skill_key":"erklaervideo","category_key":"textverstaendnis","difficulty":1,"duration_min":6,"xp_reward":14,"title":"Erklärvideo: Sinn vor Tempo","description":"Lies einen Absatz und formuliere in einem Satz, was die Kernbotschaft ist. Dann sprich – mit Fokus auf diese Kernbotschaft.","self_check_questions":[{"type":"scale_1_5","label":"War die Kernbotschaft klar?"},{"type":"scale_1_5","label":"Wurde es verständlicher?"},{"type":"checkbox_multi","label":"Was hat geholfen?","options":["Langsamer","Mehr Pausen","Klarere Betonung","Weniger Melodie"]}]},
    {"skill_key":"elearning","category_key":"pacing","difficulty":1,"duration_min":7,"xp_reward":16,"title":"E-Learning: konstante Lernstimme","description":"Sprich 90 Sekunden Lerntext: gleichmäßig, freundlich, klar. Ziel: kein „Abdriften“ in Monotonie.","self_check_questions":[{"type":"scale_1_5","label":"War es konstant und angenehm?"},{"type":"scale_1_5","label":"War es zu monoton?"},{"type":"checkbox_multi","label":"Was brauchst du für mehr Lebendigkeit?","options":["Keywords","Mini-Pausen","Leichte Tonhöhenbewegung","Mehr Lächeln"]}]},
    {"skill_key":"telefon","category_key":"pausen","difficulty":1,"duration_min":6,"xp_reward":12,"title":"Telefon: Pausen an logischen Stellen","description":"Sprich eine Ansage mit 3 Info-Blöcken. Nach jedem Block: kurze Pause. Ziel: bessere Verständlichkeit.","self_check_questions":[{"type":"scale_1_5","label":"War die Struktur klar?"},{"type":"scale_1_5","label":"Waren Pausen sinnvoll?"},{"type":"checkbox_multi","label":"Wo würdest du trennen?","options":["Nach Begrüßung","Nach Öffnungszeiten","Vor Rückrufhinweis","Vor Verabschiedung"]}]},
    {"skill_key":"hoerbuch","category_key":"artikulation","difficulty":2,"duration_min":8,"xp_reward":22,"title":"Hörbuch: weiche Konsonanten, klare Wörter","description":"Lies 60–90 Sekunden: Konsonanten klar, aber weich. Ziel: kein „Klick-Klack", trotzdem verständlich.","self_check_questions":[{"type":"scale_1_5","label":"War es klar, aber weich?"},{"type":"scale_1_5","label":"Klang es angenehm über Zeit?"},{"type":"checkbox_multi","label":"Woran arbeitest du als nächstes?","options":["Kiefer lockerer","Zunge präziser","Mehr Pausen","Weniger Druck"]}]},
    {"skill_key":"doku","category_key":"betonung","difficulty":2,"duration_min":9,"xp_reward":24,"title":"Doku: Betonung minimalistisch","description":"Markiere 3 Keywords in einem Doku-Text. Betone nur diese minimal. Ziel: Autorität durch Understatement.","self_check_questions":[{"type":"scale_1_5","label":"Waren Keywords klar, ohne overacting?"},{"type":"scale_1_5","label":"Blieb die Stimme souverän?"},{"type":"checkbox_multi","label":"Wie war die Wirkung?","options":["Sehr seriös","Zu flach","Zu viel Betonung","Sehr stimmig"]}]}
  ]
}', true);
    }

    private static function library_seed() {
        return json_decode('{
  "library_items": [
    {"type":"warmup","skill_key":"all","difficulty":1,"duration_min":3,"title":"Lippenflattern + Atem (60s + 60s)","content":"60 Sekunden Lippenflattern (locker), danach 60 Sekunden ruhig ein- und ausatmen. Fokus: Entspannung + Luftfluss."},
    {"type":"warmup","skill_key":"all","difficulty":1,"duration_min":4,"title":"Kiefer locker (Mini-Check)","content":"Kiefer locker lassen, Mund leicht geöffnet. 10x langsam ma-me-mi-mo-mu, ohne Druck, mit klaren Vokalen."},
    {"type":"warmup","skill_key":"all","difficulty":2,"duration_min":5,"title":"Konsonanten-Reset","content":"Sprich langsam: Klar. Deutlich. Ruhig. Dann 20 Sekunden schneller, ohne zu verkrampfen. Ziel: Präzision halten."},
    {"type":"tongue_twister","skill_key":"all","difficulty":2,"duration_min":3,"title":"Fischers Fische (klar & weich)","content":"Fischers Fritze fischt frische Fische. Frische Fische fischt Fischers Fritze."},
    {"type":"tongue_twister","skill_key":"all","difficulty":3,"duration_min":3,"title":"Blaukraut bleibt Blaukraut","content":"Blaukraut bleibt Blaukraut und Brautkleid bleibt Brautkleid."},
    {"type":"script","skill_key":"werbung","difficulty":1,"duration_min":1,"title":"Werbung – Kaffee (warm & modern)","content":"Guter Kaffee ist mehr als ein Getränk. Er ist ein Moment. Ein Atemzug. Ein kleines Stück Ruhe im Alltag. Jetzt entdecken – und den Tag bewusst starten."},
    {"type":"script","skill_key":"werbung","difficulty":2,"duration_min":1,"title":"Werbung – Fitness App (punchy)","content":"Mehr Energie. Mehr Fokus. Mehr du. Starte heute – mit kurzen Workouts, die wirklich in deinen Alltag passen. Jetzt App öffnen und loslegen."},
    {"type":"script","skill_key":"imagefilm","difficulty":2,"duration_min":2,"title":"Imagefilm – Handwerk (vertrauensvoll)","content":"Seit Generationen steht unser Handwerk für Qualität, die man spürt. Mit Erfahrung, Präzision und dem Anspruch, Dinge richtig zu machen – vom ersten Schritt bis zum letzten Detail."},
    {"type":"script","skill_key":"erklaervideo","difficulty":1,"duration_min":2,"title":"Erklärvideo – Online-Bestellung (klar)","content":"In drei Schritten zum Ziel: Erst wählst du das Produkt aus. Dann legst du es in den Warenkorb. Zum Schluss gibst du deine Daten ein – und bestätigst die Bestellung. Fertig."},
    {"type":"script","skill_key":"elearning","difficulty":2,"duration_min":3,"title":"E-Learning – Datenschutz (didaktisch)","content":"Datenschutz bedeutet: Du entscheidest, was mit deinen Daten passiert. Wichtig sind drei Punkte: Transparenz, Zweckbindung und Datensparsamkeit. Schauen wir uns das Schritt für Schritt an."},
    {"type":"script","skill_key":"telefon","difficulty":1,"duration_min":1,"title":"Telefonansage – Öffnungszeiten","content":"Willkommen. Sie erreichen uns montags bis freitags von 9 bis 18 Uhr. Hinterlassen Sie gern eine Nachricht – wir melden uns schnellstmöglich zurück. Vielen Dank."},
    {"type":"script","skill_key":"hoerbuch","difficulty":2,"duration_min":3,"title":"Hörbuch – Erzähler (ruhig)","content":"Am Morgen war die Luft kühl, fast still. Die Stadt wirkte wie angehalten – als würde sie noch überlegen, ob der Tag wirklich beginnen sollte. Dann, irgendwo in der Ferne, ein leises Geräusch."},
    {"type":"script","skill_key":"doku","difficulty":2,"duration_min":3,"title":"Doku – Natur (understated)","content":"Die Landschaft wirkt unberührt. Doch jeder Abschnitt erzählt eine Geschichte – von Anpassung, von Geduld und von Kräften, die über Jahre wirken, statt in Minuten."},
    {"type":"business","skill_key":"all","difficulty":1,"duration_min":4,"title":"Angebotsmail – freundlich & klar","content":"Betreff: Sprachaufnahme – Angebot\n\nHallo [Name],\n\nvielen Dank für deine Anfrage. Gern unterstütze ich euch mit professionellen Sprachaufnahmen. Damit ich dir ein passendes Angebot erstellen kann, brauche ich kurz:\n- Einsatzbereich (z.B. Werbung, Imagefilm, E-Learning)\n- Nutzungsdauer und Verbreitungsgebiet\n- Textumfang / gewünschte Länge\n- Timing/Deadline\n\nSobald ich das habe, sende ich dir ein konkretes Angebot.\n\nBeste Grüße\nPascal"},
    {"type":"business","skill_key":"all","difficulty":2,"duration_min":5,"title":"Nachfassmail – professionell","content":"Betreff: Kurze Rückfrage zu meiner Sprecher-Anfrage\n\nHallo [Name],\n\nich wollte kurz nachfragen, ob noch Fragen offen sind oder ob ich etwas ergänzen kann. Falls der Zeitplan eng ist: Ich kann meist kurzfristig liefern.\n\nBeste Grüße\nPascal"}
  ]
}', true);
    }

    private static function missions_seed() {
        return json_decode(<<<'JSON'
{
  "missions": [
    {
      "skill_key": "werbung",
      "title": "7 Tage Werbung-Basics",
      "short_description": "Punchy, freundlich, keyword-fokussiert – ohne Hektik.",
      "is_premium": false,
      "steps": [
        {"day": 1, "title": "Energie & Präsenz (Baseline)", "estimated_minutes": 6, "tasks": ["Sprich den Text 3x: (1) neutral, (2) +20% Energie, (3) +40% Energie – aber ohne schneller zu werden.", "Markiere danach 1 Satz, der am besten „frisch und klar“ klingt.", "Notiere 1 Mini-Regel für morgen (z.B. „Mikro-Pause vor Keyword“)."], "drill_skill_key": "werbung", "drill_category_key": "energie", "script_text": "Guter Geschmack muss nicht kompliziert sein. Ein Klick – und dein Lieblingsmoment ist bereit. Probier’s aus und starte entspannt in den Tag."},
        {"day": 2, "title": "Keyword-Fokus (2 Wörter führen)", "estimated_minutes": 7, "tasks": ["Wähle im Text genau 2 Keywords (z.B. „schnell“ und „kostenlos“).", "Sprich so, dass nur diese 2 Wörter klar herausstechen – der Rest bleibt ruhig.", "Tipp: nutze eine Mikro-Pause vor dem Keyword statt Lautstärke."], "drill_skill_key": "werbung", "drill_category_key": "betonung", "script_text": "Heute wird’s einfacher: Jetzt bestellen – und du bekommst die Lieferung schnell und kostenlos. Schnell. Kostenlos. Ohne Umwege."},
        {"day": 3, "title": "Pacing: Punchy ohne zu hetzen", "estimated_minutes": 7, "tasks": ["Sprich den Text in einem flotten Gefühl, aber setze bewusst Mikro-Pausen statt Tempo.", "Achte darauf, am Satzende NICHT zu beschleunigen.", "Wähle am Ende deinen besten Take und begründe kurz warum."], "drill_skill_key": "werbung", "drill_category_key": "pacing", "script_text": "Mehr Energie. Mehr Fokus. Mehr du. Starte heute – mit kurzen Workouts, die wirklich in deinen Alltag passen. Jetzt öffnen und loslegen."},
        {"day": 4, "title": "Smile: Freundlichkeit hörbar machen", "estimated_minutes": 6, "tasks": ["Sprich den Text 2x: einmal mit echtem Lächeln, einmal komplett neutral.", "Vergleiche: Welche Version wirkt sympathischer – und welche klingt natürlicher?", "Mini-Ziel: Lächeln fühlen, aber nicht „überdrehen“."], "drill_skill_key": "werbung", "drill_category_key": "smile", "script_text": "Du hast dir was Gutes verdient. Gönn dir eine Pause – und mach’s dir leicht. Heute ist dein Tag."},
        {"day": 5, "title": "Call-to-Action sauber & klar", "estimated_minutes": 7, "tasks": ["Sprich den CTA-Satz 3 Varianten: (1) freundlich, (2) entschlossen, (3) ruhig-premium.", "Achte: klare Endungen, kein Wegnuscheln.", "Wähle die beste CTA-Variante und notiere 1 Lernpunkt."], "drill_skill_key": "werbung", "drill_category_key": "artikulation", "script_text": "Jetzt testen. Einfach starten. Und sofort merken, wie leicht es sein kann. Jetzt ausprobieren."},
        {"day": 6, "title": "Mini-Demo: 3 Takes, 1 Gewinner", "estimated_minutes": 10, "tasks": ["Sprich den Text 3 Takes: (A) neutral, (B) energiegeladen, (C) premium-ruhig.", "Wähle 1 Gewinner-Take und begründe: Was macht ihn „werblich“?", "Selbstcheck: Keyword klar? Tempo stabil? Endungen sauber?"], "drill_skill_key": "werbung", "drill_category_key": "textverstaendnis", "script_text": "Das ist nicht nur praktisch – das ist smart. Du sparst Zeit, behältst den Überblick und hast mehr Raum für das, was wirklich zählt."},
        {"day": 7, "title": "Checkpoint: Pattern festigen", "estimated_minutes": 8, "tasks": ["Wähle den Drill, der dir diese Woche am meisten gebracht hat (Energie / Keywords / Pacing).", "Mach 1 Take „Best-of“ – ohne nachzudenken, nur dein neues Pattern.", "Fazit: 3 Dinge besser, 1 Fokus für nächste Woche."], "drill_skill_key": "werbung", "drill_category_key": "betonung", "script_text": "Kurz. Klar. Auf den Punkt. Genau so soll es klingen. Du bist dran – mach’s jetzt."}
      ]
    },
    {
      "skill_key": "imagefilm",
      "title": "7 Tage Imagefilm & Produktvideo",
      "short_description": "Wertigkeit, Ruhe, Emotion – ohne Kitsch.",
      "is_premium": true,
      "steps": [
        {"day": 1, "title": "Haltung: warm & vertrauensvoll", "estimated_minutes": 7, "tasks": ["Lies den Text einmal still und markiere 3 Worte, die nach „Vertrauen“ klingen.", "Sprich dann langsam, mit Ruhe – ohne werbliche Hektik.", "Mini-Ziel: Sätze ausklingen lassen (nicht abreißen)."], "drill_skill_key": "imagefilm", "drill_category_key": "energie", "script_text": "Qualität entsteht nicht zufällig. Sie ist das Ergebnis aus Erfahrung, Präzision und dem Anspruch, Dinge richtig zu machen – vom ersten Schritt bis zum letzten Detail."},
        {"day": 2, "title": "Pausen: Wertigkeit durch Luft", "estimated_minutes": 7, "tasks": ["Setze nach jedem zweiten Satz eine kurze, bewusste Pause (0,3–0,5s).", "Achte: Pausen sind Teil der Aussage, nicht „Stille aus Versehen“.", "Wähle am Ende die ruhigste, wertigste Version."], "drill_skill_key": "imagefilm", "drill_category_key": "pausen", "script_text": "Manchmal sind es die kleinen Dinge, die den Unterschied machen. Ein Material. Eine Linie. Ein Gefühl. Und plötzlich wird aus gut… besonders."},
        {"day": 3, "title": "Bild im Kopf: filmisch erzählen", "estimated_minutes": 9, "tasks": ["Stell dir 3 konkrete Bilder vor (Ort, Licht, Bewegung).", "Sprich so, als würdest du genau diese Bilder beschreiben.", "Mini-Ziel: Erzählfluss – keine „Vorlese“-Kante."], "drill_skill_key": "imagefilm", "drill_category_key": "textverstaendnis", "script_text": "Der Morgen ist ruhig. Die Stadt wird langsam wach. Und mittendrin beginnt etwas, das bleibt: ein Moment, der sich richtig anfühlt – weil alles stimmt."},
        {"day": 4, "title": "Understatement: weniger ist mehr", "estimated_minutes": 8, "tasks": ["Markiere 2 Keywords (z.B. „Verlässlichkeit“, „Leidenschaft“).", "Betone diese minimal – keine großen Tonhöhen-Sprünge.", "Ziel: Autorität durch Ruhe, nicht durch Lautstärke."], "drill_skill_key": "imagefilm", "drill_category_key": "betonung", "script_text": "Was uns ausmacht? Verlässlichkeit. Und die Leidenschaft, jedes Projekt so zu behandeln, als wäre es unser eigenes."},
        {"day": 5, "title": "Rhythmus: lange Sätze elegant führen", "estimated_minutes": 10, "tasks": ["Sprich den Text und setze Mini-Pausen nach Sinn-Einheiten (nicht nach Zeilen).", "Achte darauf, dass die Melodie natürlich bleibt.", "Selbstcheck: Kein Durchrushen, keine Monotonie."], "drill_skill_key": "imagefilm", "drill_category_key": "pacing", "script_text": "Wir verbinden moderne Technologie mit echter Handarbeit – und schaffen Lösungen, die nicht nur funktionieren, sondern sich auch richtig anfühlen."},
        {"day": 6, "title": "Produktvideo: klar, aber emotional", "estimated_minutes": 9, "tasks": ["Sprich den Text 2 Varianten: (1) nüchtern-klar, (2) warm-emotional.", "Finde die perfekte Mitte: klar + menschlich.", "Notiere: Welche Worte tragen die Emotion, ohne kitschig zu werden?"], "drill_skill_key": "imagefilm", "drill_category_key": "artikulation", "script_text": "Ein Produkt, das dir Zeit spart. Das dich entlastet. Und das im Alltag einfach funktioniert – genau dann, wenn du es brauchst."},
        {"day": 7, "title": "Checkpoint: Imagefilm Take in 60 Sekunden", "estimated_minutes": 8, "tasks": ["Mach 1 Take, der komplett „wie aus einem Guss“ klingt.", "Fokus: Ruhe, Wertigkeit, natürliche Melodie.", "Fazit: 3 Dinge besser, 1 Sache als nächster Fokus."], "drill_skill_key": "imagefilm", "drill_category_key": "pausen", "script_text": "Am Ende zählt, was bleibt: Vertrauen. Qualität. Und das Gefühl, die richtige Entscheidung getroffen zu haben."}
      ]
    },
    {
      "skill_key": "elearning",
      "title": "7 Tage E-Learning Klarheit",
      "short_description": "Didaktisch, ruhig, verständlich – ohne Monotonie.",
      "is_premium": false,
      "steps": [
        {"day": 1, "title": "Grundhaltung: ruhig & sicher", "estimated_minutes": 7, "tasks": ["Sprich den Text bewusst ruhig – als würdest du 1 Person direkt helfen.", "Achte darauf, freundlich zu klingen, ohne „zu werben“.", "Notiere: 1 Stelle, an der du noch klarer werden willst."], "drill_skill_key": "elearning", "drill_category_key": "pacing", "script_text": "In dieser Einheit lernst du die Grundlagen. Wir gehen Schritt für Schritt vor, damit du das Thema sicher anwenden kannst – ohne Stress."},
        {"day": 2, "title": "Pausen: Verarbeiten lassen", "estimated_minutes": 8, "tasks": ["Setze nach jedem dritten Satz eine kurze Pause.", "Ziel: Zuhörer:innen Zeit geben, das Gesagte zu verarbeiten.", "Achte: Pausen klingen bewusst, nicht zufällig."], "drill_skill_key": "elearning", "drill_category_key": "pausen", "script_text": "Wichtig sind drei Punkte: Erstens die Struktur. Zweitens die Umsetzung. Und drittens die Kontrolle. Schauen wir uns das jetzt nacheinander an."},
        {"day": 3, "title": "Didaktische Betonung: Sinn lenken", "estimated_minutes": 9, "tasks": ["Betone pro Satz nur 1 Kernwort.", "Sprich den Text zweimal: einmal Kernwort A, einmal Kernwort B.", "Ziel: Zuhörer-Lenkung statt Schauspiel."], "drill_skill_key": "elearning", "drill_category_key": "betonung", "script_text": "Datenschutz bedeutet: Du entscheidest, was mit deinen Daten passiert. Entscheidend ist, dass du den Zweck kennst – und bewusst zustimmst."},
        {"day": 4, "title": "Artikulation: Fachbegriffe klar, aber weich", "estimated_minutes": 8, "tasks": ["Sprich die Schlüsselbegriffe extra sauber, ohne zu pressen.", "Ziel: 100% Verständlichkeit bei angenehmer Stimme.", "Tipp: Kiefer locker, Endungen sauber."], "drill_skill_key": "elearning", "drill_category_key": "artikulation", "script_text": "Wir unterscheiden zwischen Authentifizierung, Autorisierung und Verschlüsselung. Diese drei Begriffe werden häufig verwechselt – hier klären wir den Unterschied."},
        {"day": 5, "title": "Tempo stabil (keine Satz-Ende-Beschleunigung)", "estimated_minutes": 10, "tasks": ["Sprich 60–90 Sekunden und halte das Tempo konstant.", "Achte besonders auf das Satzende: nicht schneller werden.", "Notiere: Wo rutschst du aus dem Tempo?"], "drill_skill_key": "elearning", "drill_category_key": "pacing", "script_text": "Zuerst wählst du das passende Modul aus. Dann bearbeitest du die Aufgabe. Zum Schluss überprüfst du das Ergebnis – und speicherst deine Änderungen."},
        {"day": 6, "title": "Lebendigkeit ohne Show: Mini-Melodie", "estimated_minutes": 8, "tasks": ["Arbeite mit minimaler Tonhöhenbewegung auf Schlüsselstellen.", "Ziel: lebendig, aber seriös – keine übertriebene Performance.", "Selbstcheck: Klingt es natürlich und hilfreich?"], "drill_skill_key": "elearning", "drill_category_key": "textverstaendnis", "script_text": "Wenn du den Ablauf einmal verstanden hast, wird es leicht. Du musst nicht alles auswendig können – du brauchst nur das Prinzip. Und genau das üben wir jetzt."},
        {"day": 7, "title": "Checkpoint: 90 Sekunden „wie aus einem Guss“", "estimated_minutes": 9, "tasks": ["Mach 1 Take, der ruhig, klar und konstant klingt.", "Fokus: Pausen + ein Kernwort pro Satz.", "Fazit: 3 Dinge besser, 1 Fokus für nächste Woche."], "drill_skill_key": "elearning", "drill_category_key": "pausen", "script_text": "Zusammengefasst: Du kennst jetzt die Schritte, du kennst die Stolperfallen – und du weißt, worauf du achten musst. Im nächsten Kapitel vertiefen wir das Thema."}
      ]
    }
  ]
}
JSON
        , true);
    }
}
