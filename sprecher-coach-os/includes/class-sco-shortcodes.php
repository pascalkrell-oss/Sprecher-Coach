<?php

if (!defined('ABSPATH')) {
    exit;
}

class SCO_Shortcodes {
    private $needs_assets = false;

    public function register_shortcodes() {
        add_shortcode('sprecher_coach_app', [$this, 'render_app']);
        add_shortcode('sprecher_coach_launcher', [$this, 'render_launcher']);
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
            'labelMaps' => SCO_Utils::label_maps(),
            'userId' => get_current_user_id(),
        ]);
    }

    private function current_url() {
        $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
        return home_url($request_uri);
    }

    private function render_template($template, $data = []) {
        $this->needs_assets = true;
        if (is_array($data) && !empty($data)) {
            extract($data, EXTR_SKIP);
        }

        ob_start();
        include SCO_PLUGIN_PATH . 'templates/' . $template . '.php';
        return ob_get_clean();
    }

    public function render_app() {
        return $this->render_template('app');
    }

    public function render_launcher($atts = []) {
        $atts = shortcode_atts([
            'label' => 'Sprecher Coach öffnen',
            'style' => 'primary',
            'auto_open' => '0',
        ], $atts, 'sprecher_coach_launcher');

        $style = sanitize_key((string) $atts['style']);
        $button_class = $style === 'secondary' ? '' : 'sco-btn-primary';

        return $this->render_template('launcher', [
            'label' => sanitize_text_field((string) $atts['label']),
            'button_class' => $button_class,
            'auto_open' => ((string) $atts['auto_open'] === '1') ? '1' : '0',
        ]);
    }
}
