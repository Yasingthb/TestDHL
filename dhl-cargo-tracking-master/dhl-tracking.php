<?php
/**
 * Plugin Name: DHL Tracking
 * Description: Gurmehub DHL tracking plugin
 * Plugin URI: https://gurmehub.com/
 * Version: 1.0.0
 * Author: Gurmehub
 * Author URI: https://gurmehub.com/
 * Text Domain:
 * Requires at least: 5.7
 * Requires PHP: 7.0
 */

 require_once __DIR__ . '/includes/class-dhl-tracking.php';
new DHL_Tracking();
