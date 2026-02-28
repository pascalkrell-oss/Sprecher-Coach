<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Utils {
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
                'streak_save' => 'Streak sichern: 1 Drill – fertig.',
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

    public static function xp_for_completion($base_xp, $score) {
        $bonus = (int) floor($score / 10);
        return (int) $base_xp + $bonus;
    }

    public static function level_from_xp($xp) {
        return max(1, (int) floor($xp / 120) + 1);
    }
}
