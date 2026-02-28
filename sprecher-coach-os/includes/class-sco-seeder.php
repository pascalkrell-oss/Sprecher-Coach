<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Seeder {
    const TARGET_SEED_VERSION = 1;

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
            $existing_id = (int) $wpdb->get_var($wpdb->prepare(
                'SELECT id FROM ' . SCO_DB::table('missions') . ' WHERE title=%s LIMIT 1',
                $mission['title']
            ));

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

        $data = [
            'mission_id' => $mission_id,
            'step_order' => (int) $step['day'],
            'title' => sanitize_text_field($step['title']),
            'description' => sanitize_text_field($step['title']),
            'checklist' => wp_json_encode(array_values(array_map('sanitize_text_field', (array) $step['checklist']))),
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
        return json_decode('{
  "missions": [
    {"skill_key":"werbung","title":"7 Tage Werbung-Basics","short_description":"Punchy, freundlich, keyword-fokussiert – ohne Hektik.","is_premium":false,"steps":[{"day":1,"title":"Werbung-Haltung finden","checklist":["1x Warmup","1x Drill: Energie","Notiz: Was klingt für dich werblich?"]},{"day":2,"title":"Keyword-Fokus","checklist":["2 Keywords markieren","1x Drill: Keyword-Fokus","Reflexion: Welche Worte tragen die Message?"]},{"day":3,"title":"Tempo & Mikro-Pausen","checklist":["1x Drill: Punchy ohne zu hetzen","Notiz: Wo neigst du zu hetzen?"]},{"day":4,"title":"Smile/Leichtigkeit","checklist":["1x Drill: Lächeln hörbar","Vergleich: mit/ohne Lächeln"]},{"day":5,"title":"Call-to-Action sauber","checklist":["1 CTA-Satz 3 Varianten","Wähle die beste + Begründung"]},{"day":6,"title":"Mini-Demo Take","checklist":["1 Script aus Library","3 Takes aufnehmen (optional lokal)","Self-Check ausfüllen"]},{"day":7,"title":"Checkpoint","checklist":["1 Drill deiner Wahl","Kurzfazit: 3 Dinge besser, 1 Sache als nächster Fokus"]}]},
    {"skill_key":"elearning","title":"5 Tage Lernstimme & Klarheit","short_description":"Didaktisch, ruhig, verständlich – ohne Monotonie.","is_premium":false,"steps":[{"day":1,"title":"Grundhaltung","checklist":["Warmup","1 E-Learning Script sprechen","Self-Check: Verständlichkeit"]},{"day":2,"title":"Pausen zum Verarbeiten","checklist":["Drill: Lern-Pausen","Notiz: Wo brauchen Zuhörer Raum?"]},{"day":3,"title":"Didaktische Betonung","checklist":["Drill: 1 Satz, 3 Bedeutungen","Bestes Pattern merken"]},{"day":4,"title":"Tempo stabil","checklist":["90 Sekunden stabil sprechen","Self-Check: Tempo/Klarheit"]},{"day":5,"title":"Checkpoint","checklist":["1 Drill deiner Wahl","Mini-Fazit schreiben"]}]},
    {"skill_key":"all","title":"Demo-Plan in 7 Tagen","short_description":"Struktur schaffen, Genres wählen, nächste Schritte klar machen.","is_premium":true,"steps":[{"day":1,"title":"Ziel definieren","checklist":["Wähle 2 Hauptgenres","Notiz: Wo willst du hin?"]},{"day":2,"title":"Text-Pool anlegen","checklist":["5 Scripts in Library markieren","1 eigenes Thema ergänzen"]},{"day":3,"title":"Stil-Referenzen","checklist":["3 Referenzen notieren","Was gefällt dir daran?"]},{"day":4,"title":"Takes planen","checklist":["Pro Genre: 2 Takes planen","Worauf achtest du?"]},{"day":5,"title":"Feinschliff-Checkliste","checklist":["Keywords markieren","Pausen setzen","Tempo definieren"]},{"day":6,"title":"Mini-Demo Rohfassung","checklist":["3 Takes üben (lokal)","Self-Check ausfüllen"]},{"day":7,"title":"Ergebnis & nächste Schritte","checklist":["Was fehlt noch?","Was ist dein nächstes To-do?"]}]}
  ]
}', true);
    }
}
