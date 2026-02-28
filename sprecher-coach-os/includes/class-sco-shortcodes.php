<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Shortcodes {
    private $needs_assets = false;

    public function register_shortcodes() {
        add_shortcode('sprecher_coach_dashboard', [$this, 'render_dashboard']);
        add_shortcode('sprecher_coach_daily', [$this, 'render_daily']);
        add_shortcode('sprecher_coach_skilltree', [$this, 'render_skilltree']);
        add_shortcode('sprecher_coach_missions', [$this, 'render_missions']);
        add_shortcode('sprecher_coach_library', [$this, 'render_library']);
        add_action('wp_footer', [$this, 'enqueue_runtime_assets']);
    }

    public function register_assets() {
        wp_register_style('sco-frontend', SCO_PLUGIN_URL . 'assets/css/frontend.css', [], SCO_VERSION);
        wp_register_script('sco-frontend', SCO_PLUGIN_URL . 'assets/js/frontend.js', [], SCO_VERSION, true);
    }

    public function enqueue_runtime_assets() {
        if (!$this->needs_assets) {
            return;
        }

        wp_enqueue_style('sco-frontend');
        wp_enqueue_script('sco-frontend');
        wp_localize_script('sco-frontend', 'scoData', [
            'nonce' => wp_create_nonce('wp_rest'),
            'restUrl' => esc_url_raw(rest_url('sco/v1/')),
            'isLoggedIn' => is_user_logged_in(),
        ]);
    }

    private function render_template($template) {
        $this->needs_assets = true;
        ob_start();
        include SCO_PLUGIN_PATH . 'templates/' . $template . '.php';
        return ob_get_clean();
    }

    public function render_dashboard() { return $this->render_template('dashboard'); }
    public function render_daily() { return $this->render_template('daily'); }
    public function render_skilltree() { return $this->render_template('skilltree'); }
    public function render_missions() { return $this->render_template('missions'); }
    public function render_library() { return $this->render_template('library'); }
}
