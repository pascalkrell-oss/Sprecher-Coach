<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Utils {
    public static function label_maps() {
        return [
            'skills' => [
                'hoerbuch' => 'Hörbuch',
                'doku' => 'Dokumentarfilm',
                'erklaervideo' => 'Erklärvideo',
                'elearning' => 'E-Learning',
                'telefon' => 'Telefonansagen',
                'imagefilm' => 'Imagefilm / Produktvideo',
                'werbung' => 'Werbung',
                'all' => 'Alle Skills',
            ],
            'categories' => [
                'warmup' => 'Warmups',
                'tongue_twister' => 'Zungenbrecher',
                'script' => 'Skripte',
                'business' => 'Business',
                'random' => 'Zufällig',
            ],
        ];
    }

    public static function label_skill($key) {
        $maps = self::label_maps();
        $label = $maps['skills'][$key] ?? $key;
        return (string) apply_filters('sco_label_skill', $label, $key);
    }

    public static function label_category($key) {
        $maps = self::label_maps();
        $label = $maps['categories'][$key] ?? $key;
        return (string) apply_filters('sco_label_category', $label, $key);
    }

    public static function get_settings() {
        $defaults = [
            'accent_color' => '#1a93ee',
            'weekly_goal' => 5,
            'free_library_limit' => 5,
            'free_skills_enabled' => ['werbung', 'elearning'],
            'checkout_url' => '',
            'premium_user_ids' => [],
        ];

        return wp_parse_args(get_option('sco_settings', []), $defaults);
    }

    public static function get_copy() {
        $copy = [
            'completion_messages' => [
                'score_low' => 'Guter Start. Morgen wiederholen – Konsistenz schlägt Perfektion.',
                'score_mid' => 'Sauber. Du wirst stabiler – behalte dieses Pattern bei.',
                'score_high' => 'Stark. Genau so fühlt sich Fortschritt an – weiter so.',
            ],
            'premium_tooltips' => [
                'locked_skill' => 'Mit Premium schaltest du diesen Pfad frei – inkl. Level, Verlauf & Bonus-Drills.',
                'locked_library' => 'Premium: Vollzugriff auf die Bibliothek + tägliche Script-Variationen.',
                'locked_missions' => 'Premium: Mehr Missionen, mehr Struktur – und schnellerer Fortschritt.',
                'locked_history' => 'Premium: Fortschritts-Verlauf & Vergleiche über Wochen/Monate.',
                'free_limit_reached' => 'Für heute ist das Free-Limit erreicht. Morgen geht’s weiter – oder schalte Premium frei.',
            ],
            'cta' => [
                'upgrade_primary' => 'Premium freischalten',
                'upgrade_secondary' => 'Mehr Infos',
                'dashboard_nudge' => 'Du bist dran: Heute 6 Minuten Training – und ein Level näher.',
                'streak_save' => 'Trainingsserie halten: 1 Drill – fertig.',
                'level_up' => 'Noch {n} Drills bis Level {level}.',
            ],
            'onboarding' => [
                'title' => 'Wähle deinen Fokus',
                'subtitle' => 'Du bekommst passende Drills und eine sinnvolle Rotation.',
                'options' => [
                    ['key' => 'werbung', 'label' => 'Werbung'],
                    ['key' => 'imagefilm', 'label' => 'Imagefilm / Produktvideo'],
                    ['key' => 'erklaervideo', 'label' => 'Erklärvideo'],
                    ['key' => 'elearning', 'label' => 'E-Learning'],
                    ['key' => 'telefon', 'label' => 'Telefonansagen'],
                    ['key' => 'hoerbuch', 'label' => 'Hörbuch'],
                    ['key' => 'doku', 'label' => 'Dokumentarfilm'],
                ],
                'continue' => 'Weiter',
                'skip' => 'Später',
            ],
        ];

        return apply_filters('sco_microcopy', $copy);
    }

    public static function copy($group, $key = null, $default = '') {
        $copy = self::get_copy();
        $value = $copy[$group] ?? [];

        if ($key === null) {
            return $value;
        }

        return $value[$key] ?? $default;
    }

    public static function today() {
        return wp_date('Y-m-d');
    }

    public static function week_monday($date = null) {
        $date = $date ?: self::today();
        $ts = strtotime($date);
        return wp_date('Y-m-d', strtotime('monday this week', $ts));
    }

    public static function score_from_answers($answers) {
        $sum = 0;
        $max = 0;

        foreach ($answers as $answer) {
            if (!empty($answer['type']) && in_array($answer['type'], ['scale', 'scale_1_5'], true)) {
                $value = max(1, min(5, (int) ($answer['value'] ?? 1)));
                $sum += $value;
                $max += 5;
            }
            if (!empty($answer['type']) && in_array($answer['type'], ['checkbox', 'checkbox_multi'], true)) {
                $value = !empty($answer['value']) ? 1 : 0;
                $sum += $value;
                $max += 1;
            }
        }

        if ($max === 0) {
            return 0;
        }

        return (int) round(($sum / $max) * 100);
    }

    public static function feedback_from_score($score) {
        if ($score >= 85) {
            return self::copy('completion_messages', 'score_high', 'Stark!');
        }
        if ($score >= 65) {
            return self::copy('completion_messages', 'score_mid', 'Sehr gut.');
        }

        return self::copy('completion_messages', 'score_low', 'Guter Start.');
    }


    public static function insight_text($key) {
        $map = [
            'low_checks' => 'Mehrere Self-Checks waren niedrig – plane morgen ein kurzes Warmup vor dem Drill.',
            'consistency' => 'Konstanz stark: Du hast diese Woche bereits mindestens 3 Trainings abgeschlossen.',
            'quality' => 'Qualität hoch: Dein durchschnittlicher Drill-Score liegt über 80%.',
            'rhythm' => 'Halte den Rhythmus: Ein täglicher 6-Minuten-Drill stabilisiert Stimme und Timing.',
        ];

        return $map[$key] ?? $map['rhythm'];
    }

    public static function xp_for_completion($base_xp, $score) {
        $bonus = (int) floor($score / 10);
        return (int) $base_xp + $bonus;
    }

    public static function level_from_xp($xp) {
        return max(1, (int) floor($xp / 120) + 1);
    }

    public static function text_pools() {
        return [
            'werbung' => [
                'Mehr Zeit für das, was zählt. Ein Klick – und du bist startklar. Jetzt ausprobieren.',
                'Kurz. Klar. Auf den Punkt. Starte heute – und mach es dir leichter.',
                'Schnell eingerichtet, sofort spürbar. Jetzt testen und direkt loslegen.',
                'Dein Alltag wird einfacher, wenn die Lösung im Hintergrund perfekt funktioniert. Jetzt starten.',
                'Heute entscheiden, morgen profitieren: Effizient, klar und ohne Umwege.',
                'Weniger Aufwand, mehr Wirkung. Hol dir jetzt die smarte Lösung für jeden Tag.',
                'In Sekunden eingerichtet, im Alltag unverzichtbar. Jetzt kostenlos testen.',
                'Ein Produkt, das mitdenkt. Spare Zeit und behalte den Fokus.',
                'Einfach wählen, aktivieren, loslegen. So fühlt sich Fortschritt an.',
                'Wenn es schnell gehen muss, zählt Verlässlichkeit. Jetzt selbst erleben.',
                'Mehr Klarheit im Prozess, mehr Ruhe im Team. Heute ausprobieren.',
                'Die bessere Entscheidung beginnt mit einem Klick. Jetzt entdecken.',
            ],
            'imagefilm' => [
                'Qualität entsteht aus Erfahrung, Ruhe und Präzision. Genau daran arbeiten wir – jeden Tag.',
                'Wenn Details stimmen, fühlt sich alles richtig an. Das ist unser Anspruch.',
                'Wir glauben an Lösungen, die nicht laut sind, sondern nachhaltig überzeugen.',
                'Unsere Arbeit beginnt dort, wo andere aufhören: beim feinen Unterschied.',
                'Vertrauen wächst, wenn Leistung konstant bleibt. Darauf kannst du bauen.',
                'Technologie ist nur dann gut, wenn sie den Menschen dient. Genau dafür entwickeln wir.',
                'Wir verbinden Handwerk und Innovation zu Ergebnissen, die langfristig tragen.',
                'Jedes Projekt erzählt eine Geschichte von Sorgfalt, Haltung und Klarheit.',
                'Was wir liefern, soll nicht nur funktionieren – es soll sich richtig anfühlen.',
                'Mit ruhiger Hand und klarem Blick schaffen wir Lösungen mit Substanz.',
                'Unsere Stärke liegt in der Verbindung aus Präzision, Tempo und Verantwortung.',
                'Am Ende zählt das Gefühl, die richtige Entscheidung getroffen zu haben.',
            ],
            'erklaervideo' => [
                'In drei Schritten zum Ziel: Erst auswählen, dann bestätigen, zum Schluss speichern. Fertig.',
                'Wichtig ist die Reihenfolge: zuerst vorbereiten, dann durchführen, anschließend prüfen.',
                'Schritt eins: Daten erfassen. Schritt zwei: Eingaben kontrollieren. Schritt drei: Vorgang abschließen.',
                'Damit alles sauber läuft, starte mit der Einrichtung. Danach folgt die Ausführung.',
                'Wenn du strukturiert vorgehst, sparst du Zeit: erst planen, dann umsetzen, zuletzt testen.',
                'Achte auf die Reihenfolge im Prozess. So vermeidest du Fehler von Anfang an.',
                'Zu Beginn definierst du das Ziel. Danach legst du die Parameter fest. Anschließend startest du den Ablauf.',
                'Erst die Basis, dann die Details, zum Schluss die Kontrolle. So bleibt der Ablauf stabil.',
                'Wir gehen Schritt für Schritt vor: Auswahl, Einstellung, Abschluss.',
                'Prüfe vor dem Start die Voraussetzungen. Dann gelingt die Umsetzung deutlich sicherer.',
                'Wenn ein Schritt fehlt, leidet das Ergebnis. Deshalb arbeiten wir klar strukturiert.',
                'Am Ende steht eine einfache Regel: systematisch starten, sauber durchführen, bewusst abschließen.',
            ],
            'elearning' => [
                'Nimm dir kurz Zeit: Wir gehen Schritt für Schritt vor. So bleibt es verständlich – und du kannst es sicher anwenden.',
                'Merke dir dieses Prinzip: erst verstehen, dann umsetzen, danach kontrollieren.',
                'In dieser Einheit lernst du die Grundlagen in ruhigem Tempo und klarer Struktur.',
                'Wichtig ist, dass du jeden Schritt bewusst nachvollziehst, bevor du weitergehst.',
                'Wir starten mit dem Kernkonzept, üben dann die Anwendung und sichern zum Schluss das Ergebnis.',
                'Wenn du den Ablauf einmal verinnerlicht hast, wird die Umsetzung deutlich leichter.',
                'Achte auf die Schlüsselbegriffe. Sie helfen dir, den roten Faden zu behalten.',
                'Heute geht es um Klarheit: kurze Schritte, präzise Begriffe, sichere Anwendung.',
                'Du brauchst kein Vorwissen. Wir bauen das Thema logisch und nachvollziehbar auf.',
                'Nutze die Pause nach jedem Abschnitt, um das Gelernte kurz zu reflektieren.',
                'Ziel dieser Lektion: verstehen, anwenden, überprüfen – in genau dieser Reihenfolge.',
                'Ruhiges Sprechen unterstützt das Lernen. Gib jedem Gedanken den nötigen Raum.',
            ],
            'telefon' => [
                'Willkommen. Sie erreichen uns montags bis freitags von 9 bis 18 Uhr. Hinterlassen Sie eine Nachricht. Vielen Dank.',
                'Danke für Ihren Anruf. Bitte nennen Sie Ihren Namen und Ihr Anliegen – wir melden uns schnellstmöglich zurück.',
                'Guten Tag. Unser Team ist aktuell im Gespräch. Bitte bleiben Sie kurz in der Leitung.',
                'Herzlich willkommen bei der Kundenbetreuung. Für Rückfragen hinterlassen Sie bitte Ihre Kontaktdaten.',
                'Sie sprechen mit dem Service-Team. Für eine schnelle Bearbeitung nennen Sie bitte Ihre Kundennummer.',
                'Vielen Dank für Ihren Anruf. Der nächste freie Mitarbeiter ist gleich für Sie da.',
                'Außerhalb unserer Öffnungszeiten erreichen Sie uns per E-Mail. Wir antworten zeitnah.',
                'Bitte halten Sie für die Identifikation Ihre Auftragsnummer bereit. Vielen Dank.',
                'Für den technischen Support drücken Sie die Eins. Für allgemeine Fragen bleiben Sie in der Leitung.',
                'Wir sind gleich für Sie da. Vielen Dank für Ihre Geduld.',
                'Bitte sprechen Sie nach dem Signalton deutlich Ihren Namen und Ihre Rückrufnummer.',
                'Ihr Anliegen ist uns wichtig. Wir kümmern uns schnell und zuverlässig darum.',
            ],
            'hoerbuch' => [
                'Am Morgen war die Luft kühl. Die Straße noch still. Und doch lag etwas in der Luft, das den Tag anders machte.',
                'Er blieb kurz stehen. Nicht aus Unsicherheit – sondern, weil er wusste, dass dieser Moment wichtig war.',
                'Der Wind strich durch die Bäume, als wollte er ein altes Geheimnis weitersagen.',
                'Sie öffnete das Fenster. Der Duft von Regen und Erde füllte den Raum.',
                'Zwischen den Häusern lag ein leiser Klang, wie ein Versprechen auf einen neuen Anfang.',
                'Er sah auf die Uhr, dann in den Himmel. Beides sagte ihm, dass keine Zeit zu verlieren war.',
                'Die Schritte auf dem Flur kamen näher, langsam und entschlossen.',
                'Hinter der Tür wartete keine Antwort, nur Stille – und eine Entscheidung.',
                'Die Nacht war lang gewesen, doch jetzt trug das Licht eine sanfte Zuversicht in sich.',
                'Sie lächelte kaum sichtbar. Genau dieses kleine Zeichen änderte alles.',
                'Ein Zug fuhr in der Ferne vorbei, als die Stadt den Atem anhielt.',
                'Manchmal beginnt eine große Geschichte mit einem Satz, der ganz leise gesprochen wird.',
            ],
            'doku' => [
                'Die Landschaft wirkt still. Doch über Jahre formen Wind, Wasser und Zeit ein Bild, das sich stetig verändert.',
                'Was wir sehen, ist das Ergebnis von Geduld – und Kräften, die länger wirken als ein einzelner Augenblick.',
                'Hier entscheidet nicht der Moment, sondern das Zusammenspiel vieler kleiner Prozesse.',
                'Seit Jahrzehnten dokumentieren Forscherinnen und Forscher diese Veränderungen mit großer Genauigkeit.',
                'Jeder Messwert erzählt von Entwicklungen, die mit bloßem Auge kaum sichtbar sind.',
                'Die Region gilt als sensibel: kleine Eingriffe können große Folgen auslösen.',
                'Im Jahresverlauf ändern sich Temperatur, Licht und Feuchtigkeit – und mit ihnen das gesamte Ökosystem.',
                'Zwischen Tradition und Moderne entstehen neue Antworten auf alte Fragen.',
                'Die Beobachtung zeigt: Stabilität ist oft das Ergebnis stetiger Anpassung.',
                'Hinter den nüchternen Zahlen steht eine klare Botschaft für die kommenden Jahre.',
                'Die Daten wurden über lange Zeiträume erhoben und sorgfältig ausgewertet.',
                'So entsteht ein präzises Bild davon, wie Natur und Mensch miteinander wirken.',
            ],
        ];
    }

    private static function deterministic_pool_pick(array $pool, $seed) {
        if (empty($pool)) {
            return '';
        }

        $index = abs(crc32((string) $seed));
        return (string) $pool[$index % count($pool)];
    }

    public static function resolve_drill_text($user_id, array $drill) {
        global $wpdb;

        $raw_text = trim((string) ($drill['script_text'] ?? ''));
        if ($raw_text !== '') {
            return [
                'text' => sanitize_textarea_field($raw_text),
                'source' => sanitize_key((string) ($drill['script_source'] ?? 'drill')) ?: 'drill',
                'title' => sanitize_text_field((string) ($drill['title'] ?? '')),
            ];
        }

        $skill = sanitize_key((string) ($drill['skill_key'] ?? 'werbung'));
        $difficulty = max(1, (int) ($drill['difficulty'] ?? 1));
        $seed_base = implode('|', [$user_id, self::today(), $skill, $difficulty, (int) ($drill['id'] ?? 0)]);

        $library_items = $wpdb->get_results($wpdb->prepare(
            'SELECT id, title, content FROM ' . SCO_DB::table('library') . ' WHERE category_key=%s AND skill_key=%s AND (difficulty=%d OR difficulty=0) ORDER BY id ASC',
            'script',
            $skill,
            $difficulty
        ), ARRAY_A);

        if (empty($library_items)) {
            $library_items = $wpdb->get_results($wpdb->prepare(
                'SELECT id, title, content FROM ' . SCO_DB::table('library') . ' WHERE category_key=%s AND skill_key=%s ORDER BY id ASC',
                'script',
                $skill
            ), ARRAY_A);
        }

        if (!empty($library_items)) {
            $index = abs(crc32($seed_base . '|library')) % count($library_items);
            $pick = $library_items[$index];
            $content = trim(wp_strip_all_tags((string) ($pick['content'] ?? '')));
            if ($content !== '') {
                return [
                    'text' => sanitize_textarea_field($content),
                    'source' => 'library',
                    'title' => sanitize_text_field((string) ($pick['title'] ?? 'Bibliothekstext')),
                ];
            }
        }

        $pool = self::text_pools()[$skill] ?? [];
        $pool_text = trim(self::deterministic_pool_pick($pool, $seed_base . '|pool'));
        if ($pool_text !== '') {
            return [
                'text' => sanitize_textarea_field($pool_text),
                'source' => 'pool',
                'title' => self::label_skill($skill) . ' Trainingstext',
            ];
        }

        return [
            'text' => 'Sprich den Text ruhig und klar. Konzentriere dich auf Atmung, Betonung und Pausen.',
            'source' => 'pool',
            'title' => 'Trainingstext (Fallback)',
        ];
    }
}

function sco_label_skill($key) {
    return SCO_Utils::label_skill(sanitize_key((string) $key));
}

function sco_label_category($key) {
    return SCO_Utils::label_category(sanitize_key((string) $key));
}

function sco_resolve_drill_text($user_id, $drill) {
    if (!is_array($drill)) {
        $drill = [];
    }

    return SCO_Utils::resolve_drill_text((int) $user_id, $drill);
}
