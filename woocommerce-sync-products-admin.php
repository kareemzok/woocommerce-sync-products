<?php

include_once 'class-woocommerce-sync-products.php';

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : (isset($options['active_tab']) ? $options['active_tab'] : 'api_key');
//if (!class_exists('WC_products_sync')) {

    $links['setting'] = '<a href="admin.php?page=wc-settings&tab=integration&section=products-sync-integration">Settings</a>';


    $WC_products_sync = new WC_products_sync(__FILE__, array(
        'slug' => 'woocommerce-sync-products',
        'title' => 'Products Sync for WooCommerce',
        'desc' => 'Settings of the Products Sync for WooCommerce plugin',
        'icon' => 'dashicons-welcome-widgets-menus',
        'position' => 99,
    ));
    $WC_products_sync->add_field(array(
        'name' => 'loading-bar',
        'type' => 'div',
        'title' => '',
        'default' => '',
        'id' => 'sync-loading'
    ));



    $readonly = true;

    $css_background = "background-color:#D3D3D3";
    if (!isset($_POST[$WC_products_sync->settings_id . '_mapping'])) {
        $WC_products_sync->add_field(array(
            'name' => 'field_api_link',
            'title' => 'External link to sync product',
            'desc' => 'This link can be changed from the Woocommerce integration ' . $links['setting'] . ' page',
            'default' => get_option('field_api_link', $integration_variable['rest_api_link']),
            'style' => 'width:30%;' . $css_background,
            'readonly' => $readonly
        ));
    }
    if (isset($_POST[$WC_products_sync->settings_id . '_mapping'])) {


        $WC_products_sync->products_sync_process_mapping_fields($_POST);
    }
//}