<?php
/**
 * Plugin Name: Switchboard
 * Description: Client work request workflow with queue management, approvals, file exchange, scheduling, and billing.
 * Version: 0.25.15
 * Author: Custom
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WP_PQ_VERSION', '0.25.15');
define('WP_PQ_PLUGIN_FILE', __FILE__);
define('WP_PQ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_PQ_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-installer.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-roles.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-db.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-workflow.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-housekeeping.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-api.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-manager-api.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-ai-importer.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-admin.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-portal.php';
require_once WP_PQ_PLUGIN_DIR . 'includes/class-wp-pq-plugin.php';

register_activation_hook(__FILE__, ['WP_PQ_Installer', 'activate']);
register_deactivation_hook(__FILE__, ['WP_PQ_Installer', 'deactivate']);

add_action('plugins_loaded', static function () {
    WP_PQ_Plugin::instance()->boot();
});
