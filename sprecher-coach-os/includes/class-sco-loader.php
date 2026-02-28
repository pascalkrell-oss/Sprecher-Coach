<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once SCO_PLUGIN_PATH . 'includes/class-sco-db.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-utils.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-permissions.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-seeder.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-shortcodes.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-api.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-admin.php';

class SCO_Loader {
    /** @var SCO_Shortcodes */
    private $shortcodes;

    /** @var SCO_API */
    private $api;

    /** @var SCO_Admin */
    private $admin;

    public function __construct() {
        $this->shortcodes = new SCO_Shortcodes();
        $this->api = new SCO_API();
        $this->admin = new SCO_Admin();
    }

    public function run() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this->shortcodes, 'register_assets']);
        add_action('admin_enqueue_scripts', [$this->admin, 'register_assets']);
        add_action('admin_menu', [$this->admin, 'register_menu']);

        $this->shortcodes->register_shortcodes();
        $this->api->register_hooks();
    }

    public function load_textdomain() {
        load_plugin_textdomain('sprecher-coach-os', false, dirname(plugin_basename(SCO_PLUGIN_FILE)) . '/languages');
    }
}
