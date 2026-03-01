<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Shortcodes {
    private $needs_assets = false;

    public function register_shortcodes() {
        add_shortcode('sprecher_coach_app', [$this, 'render_app']);
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
            'loginUrl' => wp_login_url($this->current_url()),
            'registerUrl' => wp_registration_url(),
            'myAccountUrl' => home_url('/my-account/'),
            'hasRegistration' => (bool) get_option('users_can_register'),
        ]);
    }

    private function current_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        return home_url($request_uri);
    }

    private function render_template($template) {
        $this->needs_assets = true;
        ob_start();
        include SCO_PLUGIN_PATH . 'templates/' . $template . '.php';
        return ob_get_clean();
    }

    public function render_app() {
        return $this->render_template('app');
    }
}
