<?php

/**
 * Integration .
 *
 * @package   Woocommerce Products Sync Integration
 * @category Integration
 * @author   Kareem zok.
 */
if (!class_exists('WC_products_sync_Integration')) :

    class WC_products_sync_Integration extends WC_Integration {

        /**
         * Init and hook in the integration.
         */
        public function __construct() {
            
            global $woocommerce;
            $this->id = 'products-sync-integration';
            $this->method_title = __('Products Sync for WooCommerce');
            $this->method_description = __('This Plugin integrate WooCommerce to get exxternal products and import them.');
            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();
            // Define user set variables.
            $this->rest_api_link = $this->get_option('rest_api_link');
            // Actions.
            add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
        }

        /**
         * Initialize integration settings form fields.
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'rest_api_link' => array(
                    'title' => __('Api link'),
                    'type' => 'text',
                    'description' => __('Enter external Api link to get products as json data'),
                    'desc_tip' => true,
                    'default' => '',
                    'css' => 'width:30%;',
                ),
            );
        }

    }

    
endif; 
 
