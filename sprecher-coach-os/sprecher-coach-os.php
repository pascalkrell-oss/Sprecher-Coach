<?php
/**
 * Plugin Name: Sprecher Coach OS
 * Description: Conversionstarkes Daily-Drill-System für Sprecher-Neulinge mit Skilltree, Missions und Bibliothek.
 * Version: 1.0.0
 * Author: Sprecher Coach
 * Text Domain: sprecher-coach-os
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SCO_VERSION', '1.0.0');
define('SCO_PLUGIN_FILE', __FILE__);
define('SCO_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SCO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once SCO_PLUGIN_PATH . 'includes/class-sco-loader.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-activator.php';
require_once SCO_PLUGIN_PATH . 'includes/class-sco-deactivator.php';

register_activation_hook(__FILE__, ['SCO_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['SCO_Deactivator', 'deactivate']);

function sco_run_plugin() {
    $plugin = new SCO_Loader();
    $plugin->run();
}

sco_run_plugin();
