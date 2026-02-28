<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Seeder {
    public static function seed_if_empty() {
        global $wpdb;

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM " . SCO_DB::table('drills')) === 0) {
            self::seed_drills();
        }

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM " . SCO_DB::table('library')) === 0) {
            self::seed_library();
        }

        if ((int) $wpdb->get_var("SELECT COUNT(*) FROM " . SCO_DB::table('missions')) === 0) {
            self::seed_missions();
        }
    }

    private static function seed_drills() {
        global $wpdb;
        $skills = ['werbung', 'imagefilm', 'erklaervideo', 'elearning', 'telefon', 'hoerbuch', 'doku'];
        $kategorien = ['artikulation', 'pacing', 'pausen', 'betonung', 'smile', 'energie', 'textverstaendnis', 'mikrofontechnik_ohne_audio', 'business_basics'];

        foreach ($skills as $sIndex => $skill) {
            for ($i = 1; $i <= 10; $i++) {
                $cat = $kategorien[($i + $sIndex) % count($kategorien)];
                $difficulty = ($i % 3) + 1;
                $duration = 5 + ($i % 8);
                $xp = 10 + ($difficulty * 5);

                $questions = [
                    ['id' => 'klarheit', 'label' => 'War deine Aussprache klar und sauber?', 'type' => 'scale'],
                    ['id' => 'fluss', 'label' => 'War dein Sprechfluss stabil ohne Hast?', 'type' => 'scale'],
                    ['id' => 'pause', 'label' => 'Hast du bewusst Pausen gesetzt?', 'type' => 'checkbox'],
                    ['id' => 'energie', 'label' => 'Hast du dein Energielevel aktiv gesteuert?', 'type' => 'checkbox'],
                ];

                $wpdb->insert(SCO_DB::table('drills'), [
                    'skill_key' => $skill,
                    'category_key' => $cat,
                    'title' => sprintf('%s Drill %d: Fokus %s', ucfirst($skill), $i, str_replace('_', ' ', $cat)),
                    'description' => 'Lies den Übungstext in drei Durchläufen: neutral, fokussiert auf Pausen, dann mit klarer Intention. Achte auf Tempo, Präsenz und präzise Endungen.',
                    'duration_min' => $duration,
                    'difficulty' => $difficulty,
                    'self_check_questions' => wp_json_encode($questions),
                    'xp_reward' => $xp,
                    'is_premium' => in_array($skill, ['werbung', 'elearning'], true) ? 0 : 1,
                ]);
            }
        }
    }

    private static function seed_library() {
        global $wpdb;

        $batches = [
            'warmups' => 30,
            'zungenbrecher' => 30,
            'business_vorlagen' => 10,
        ];

        foreach ($batches as $cat => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $wpdb->insert(SCO_DB::table('library'), [
                    'category_key' => $cat,
                    'skill_key' => '',
                    'difficulty' => ($i % 3) + 1,
                    'duration_min' => 2 + ($i % 6),
                    'title' => ucfirst(str_replace('_', ' ', $cat)) . " Vorlage $i",
                    'content' => 'Praxisnahe Übung mit klarer Anleitung: Atmung setzen, Kernbotschaft markieren, langsam einsprechen, dann im Zieltempo wiederholen.',
                    'is_premium' => ($cat === 'business_vorlagen' && $i > 5) ? 1 : 0,
                ]);
            }
        }

        $genreCounts = ['werbung' => 25, 'imagefilm' => 20, 'erklaervideo' => 20, 'elearning' => 20, 'telefon' => 15, 'hoerbuch' => 20, 'doku' => 15];
        foreach ($genreCounts as $skill => $count) {
            for ($i = 1; $i <= $count; $i++) {
                $wpdb->insert(SCO_DB::table('library'), [
                    'category_key' => 'genre_skripte',
                    'skill_key' => $skill,
                    'difficulty' => ($i % 3) + 1,
                    'duration_min' => 3 + ($i % 7),
                    'title' => sprintf('Genre-Skript %s %d', ucfirst($skill), $i),
                    'content' => 'Textbaustein mit Hook, Kernnutzen und Abschluss-CTA. Nutze Pausen an Satzenden und setze Fokuswörter bewusst.',
                    'is_premium' => in_array($skill, ['werbung', 'elearning'], true) ? 0 : 1,
                ]);
            }
        }
    }

    private static function seed_missions() {
        global $wpdb;

        $missions = [
            ['7 Tage Werbung-Basics', 'In 7 Tagen zur klaren Werbestimme mit Fokus auf Intention, Pace und CTA.', 7, 0],
            ['5 Tage E-Learning Klarheit', 'Verständlich und ruhig erklären: Struktur, Pausen und Lernfreundlichkeit.', 5, 0],
            ['Demo-Struktur in 7 Tagen', 'Baue ein überzeugendes Demo-Setup mit passender Reihenfolge.', 7, 0],
            ['Preis & Nutzungsrechte Crashkurs', 'Business-Basics für Einsteiger: Angebot, Buyout, Revisionen.', 5, 1],
            ['Telefonansagen sicher sprechen', 'Klar, freundlich, professionell am Telefon klingen.', 7, 1],
            ['Hörbuch-Flow Woche', 'Figurenführung und Erzählerstimme sauber trennen.', 7, 1],
            ['Imagefilm Präsenz-Boost', 'Markenstimme mit Ruhe und Autorität trainieren.', 7, 1],
            ['Doku-Authentizität', 'Natürlicher Doku-Ton mit Faktendichte und Haltung.', 7, 1],
        ];

        foreach ($missions as $missionIndex => $mission) {
            $wpdb->insert(SCO_DB::table('missions'), [
                'title' => $mission[0],
                'description' => $mission[1],
                'duration_days' => $mission[2],
                'is_bonus' => $mission[3],
                'is_premium' => $missionIndex > 2 ? 1 : 0,
            ]);
            $mission_id = (int) $wpdb->insert_id;

            for ($s = 1; $s <= max(5, $mission[2]); $s++) {
                $wpdb->insert(SCO_DB::table('mission_steps'), [
                    'mission_id' => $mission_id,
                    'step_order' => $s,
                    'title' => "Tag $s: Fokus-Session",
                    'description' => 'Führe den Tagesdrill aus, prüfe dich mit Self-Check und notiere eine konkrete Verbesserung für morgen.',
                    'checklist' => wp_json_encode([
                        'Warmup 3 Minuten durchgeführt',
                        'Drill vollständig absolviert',
                        'Eine Lernnotiz dokumentiert',
                    ]),
                ]);
            }
        }
    }
}
