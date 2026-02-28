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
            if (!empty($answer['type']) && $answer['type'] === 'scale') {
                $value = max(1, min(5, (int) ($answer['value'] ?? 1)));
                $sum += $value;
                $max += 5;
            }
            if (!empty($answer['type']) && $answer['type'] === 'checkbox') {
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
            return 'Stark! Du klingst kontrolliert, präsent und professionell.';
        }
        if ($score >= 65) {
            return 'Sehr gut. Mit etwas mehr Präzision in Pausen und Betonung knackt du das nächste Level.';
        }
        if ($score >= 45) {
            return 'Guter Schritt. Wiederhole den Drill langsam und fokussiere Artikulation + Klarheit.';
        }

        return 'Dranbleiben! Nimm dir heute 3 Minuten extra für Warmup und Atmung.';
    }

    public static function xp_for_completion($base_xp, $score) {
        $bonus = (int) floor($score / 10);
        return (int) $base_xp + $bonus;
    }

    public static function level_from_xp($xp) {
        return max(1, (int) floor($xp / 120) + 1);
    }
}
