<?php

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$woocommerce_syn_products_autoloader = true;

function woocommerce_syn_products_plugin_status() {

    return true;
}

function woocommerce_sync_all_plugins_loaded() {
    if (woocommerce_syn_products_plugin_status()) {
        include_once 'woocommerce-sync-products-admin.php';

    }
}
