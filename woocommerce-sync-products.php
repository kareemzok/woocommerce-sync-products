<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://zeesweb.com
 * @since             1.0.0
 * @package           zeesweb
 *
 * @wordpress-plugin
 * Plugin Name:       Products Sync for WooCommerce
 * Plugin URI:        https://zeesweb.com
 * Description:       Sync external product from Api into Woocommerce.
 * Version:           1.0.0
 * Author:            ZeeSWEB
 * Author URI:        https://zeesweb.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-sync
 * Domain Path:       /languages
 * Requires at least: 4.9
 * Tested up to: 5.6
 * WC requires at least: 3.5
 * WC tested up to: 4.8
 */
// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

include_once "bootstrap.php";


// plugins loaded callback
add_action('plugins_loaded', 'woocommerce_sync_all_plugins_loaded', 12);
