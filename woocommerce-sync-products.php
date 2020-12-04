<?php
/**
 * Plugin Name: Products Sync for WooCommerce
 * Plugin URI: 
 * Description: Sync external product from Api into Woocommerce
 * Author: Kareem zok
 * Author URI: http://www.kareemzok.com
 * Version: 1.0
 */
include_once 'wordpress-settings.php';
define('WOOCOMMERCESYN__PLUGIN_URL', plugin_dir_url(__FILE__));
define('ASSETS', 'assets/');
define('WOOCOMMERCESYN_VERSION', '1.0.0');

if (!class_exists('WC_products_sync')) :

    $integration_variable = get_option('woocommerce_products-sync-integration_settings');

    class WC_products_sync extends WordPressSettings {

        /**
         * Default options
         * @var array
         */
        public $defaultOptions = array(
            'slug' => '', // Name of the menu item
            'title' => '', // Title displayed on the top of the admin panel
            'page_title' => '',
            'parent' => null, // id of parent, if blank, then this is a top level menu
            'id' => '', // Unique ID of the menu item
            'capability' => 'manage_options', // User role
            'icon' => 'dashicons-admin-generic', // Menu icon for top level menus only http://melchoyce.github.io/dashicons/
            'position' => null, // Menu position. Can be used for both top and sub level menus
            'desc' => '', // Description displayed below the title
            'function' => ''
        );

        /**
         * Gets populated on submenus, contains slug of parent menu
         * @var null
         */
        public $parent_id = null;

        /**
         * Menu options
         * @var array
         */
        public $menu_options = array();

        /**
         * Construct the plugin.
         */
        public function __construct($path, $options) {
            add_action('plugins_loaded', array($this, 'init'));


            $this->menu_options = array_merge($this->defaultOptions, $options);

            if ($this->menu_options['slug'] == '') {

                return;
            }

            $this->settings_id = $this->menu_options['slug'];

            $this->prepopulate();

            add_action('admin_menu', array($this, 'add_page'));

            //add_action( 'wordpressmenu_page_save_' . $this->settings_id, array( $this, 'save_settings' ) );

            register_activation_hook(__FILE__, array($this, 'run_on_activate'));
            add_action('admin_notices', array($this, 'initial_notice'));
            add_filter('cron_schedules', array($this, 'wc_sync_product_custom_cron_schedule'));
            register_deactivation_hook(__FILE__, array($this, 'on_deactivation'));
        }

        /**
         * Initialize the plugin.
         */
        public function init() {
            // Checks if WooCommerce is installed.
            if (class_exists('WC_Integration')) {
                // Include our integration class.
                include_once 'class-wc-integration.php';
                // Register the integration.
                add_filter('woocommerce_integrations', array($this, 'add_integration'));
                add_action('admin_enqueue_scripts', array($this, 'load_resources'));

                // Set the plugin slug
                define('MY_PLUGIN_SLUG', 'wc-settings');
                // Setting action for plugin
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'WC_products_sync_action_links'));
                add_action('wc_product_price_updater', array($this, 'wc_product_price_update'));
            }
        }

        /**
         * Displays notice when plugin is installed but not yet configured working withotu woocommerce.
         */
        public function initial_notice() {

            // check if woocommerce active then check the  config of the plugin itself
            if (!$this->check_if_woocoommerce_active()) {
                $class = 'notice notice-warning is-dismissible';
                $message = sprintf(
                        /* translators: Placeholders %1$s - opening strong HTML tag, %2$s - closing strong HTML tag, %3$s - opening link HTML tag, %4$s - closing link HTML tag */
                        esc_html__(
                                '%1$sProducts sync for Woocommerce%2$s can not work without activating woocommerce. Please activate woocommerce plugin.', WOOCOMMERCESYN_VERSION
                        ), '<strong>', '</strong>', '<a href="' . admin_url('admin.php?page=page=wc-settings&tab=integration&section=products-sync-integration') . '">', '</a>'
                );
                printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
            } elseif (!$this->woocommerce_sync_product_is_configured()) {
                $class = 'notice notice-warning is-dismissible';
                $message = sprintf(
                        /* translators: Placeholders %1$s - opening strong HTML tag, %2$s - closing strong HTML tag, %3$s - opening link HTML tag, %4$s - closing link HTML tag */
                        esc_html__(
                                '%1$sProducts sync for Woocommerce%2$s is not yet connected configured.To complete the configuration, %3$svisit the plugin settings page%4$s.', WOOCOMMERCESYN_VERSION
                        ), '<strong>', '</strong>', '<a href="' . admin_url('admin.php?page=wc-settings&tab=integration&section=products-sync-integration') . '">', '</a>'
                );
                printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
            }
        }

        /**
         *  Plugin load resources .
         */
        function load_resources() {

            global $hook_suffix;

            if (in_array($hook_suffix, array(
                        'toplevel_page_woocommerce-sync-products',
                    ))) {

                //loading css
                wp_register_style('woocommerce-sync', WOOCOMMERCESYN__PLUGIN_URL . ASSETS . 'css/woocommerce-sync-products.css', array(), WOOCOMMERCESYN_VERSION);
                wp_enqueue_style('woocommerce-sync');
                // loading js
                wp_register_script('woocommerce-sync', WOOCOMMERCESYN__PLUGIN_URL . ASSETS . 'js/woocommerce-sync-products.js', array(), WOOCOMMERCESYN_VERSION);
                wp_enqueue_script('woocommerce-sync');
            }
        }

        /**
         * function called on plugin activation
         */
        function run_on_activate() {
            if (!wp_next_scheduled('wc_product_price_updater')) {
                wp_schedule_event(time(), 'every-24-hours', 'wc_product_price_updater');
            }
            add_action('woocommerce_init', array($this, 'wc_product_price_update'));
        }

        /**
         * function called using cron to update product prices
         */
        function wc_product_price_update() {



            if (function_exists('wc_get_product_id_by_sku')) {

                $integration_variable = get_option('woocommerce_products-sync-integration_settings');
                $settings_data['field_api_link'] = $integration_variable['rest_api_link'];

                $i = 0;
                $data = $this->call_external_data_url($settings_data);
                if (!empty($data)) {
                    foreach ($data as $product) {

//                        if ($i == 3) {
//                            exit;
//                        }
                        $i++;
                        $sku = "SKU-" . $product->SKU;
                        $updated_price = $product->Price_LBP;
                        $product_id = wc_get_product_id_by_sku($sku);
                        $price = get_post_meta($product_id, '_regular_price', true);
                        if ($price != $updated_price) {

                            update_post_meta($product_id, '_price', $updated_price);
                            update_post_meta($product_id, '_regular_price', $updated_price);
                        }
                    }
                }
            }
        }

        /**
         * Adds a custom cron schedule for every minutes/hours....
         *
         * @param array $schedules An array of non-default cron schedules.
         * @return array Filtered array of non-default cron schedules.
         */
        function wc_sync_product_custom_cron_schedule($schedules) {
            $schedules['every-24-hours'] = array(
                'interval' => 60 * 24 * MINUTE_IN_SECONDS, // 60 seconds
                'display' => __('Every 24 hours', 'MY_PLUGIN_SLUG')
            );
            return $schedules;
        }

        /**
         * Populate some of required options for the page such title
         * @return void 
         */
        public function prepopulate() {

            if ($this->menu_options['title'] == '') {
                $this->menu_options['title'] = ucfirst($this->menu_options['slug']);
            }

            if ($this->menu_options['page_title'] == '') {
                $this->menu_options['page_title'] = $this->menu_options['title'];
            }
        }

        /**
         * Add the menu page using WordPress API
         * @return [type] [description]
         */
        public function add_page() {

            $functionToUse = $this->menu_options['function'];

            if ($functionToUse == '') {
                $functionToUse = array($this, 'create_menu_page');
            }

            if ($this->parent_id != null) {

                add_submenu_page($this->parent_id, $this->menu_options['page_title'], $this->menu_options['title'], $this->menu_options['capability'], $this->menu_options['slug'], $functionToUse);
            } else {

                add_menu_page($this->menu_options['page_title'], $this->menu_options['title'], $this->menu_options['capability'], $this->menu_options['slug'], $functionToUse, $this->menu_options['icon'], $this->menu_options['position']);
            }
        }

        /**
         * Create the menu page
         * @return void 
         */
        public function create_menu_page() {

            $this->save_if_submit();

            $tab = 'general';

            if (isset($_GET['tab'])) {
                $tab = $_GET['tab'];
            }
            $this->init_settings();
            ?>
            <div class="wrap">
                <h2><?php echo $this->menu_options['page_title'] ?></h2>
                <?php
                if (!empty($this->menu_options['desc'])) {
                    ?><p class='description'><?php echo $this->menu_options['desc'] ?></p><?php
                }

                $this->render_tabs($tab);
                if ($this->check_if_woocoommerce_active()) {
                    ?>
            
					<?php if(isset($_POST[$this->settings_id . '_mapping'])) { ?>
					  <form method="POST" action="" >
                        <div class="postbox">
                            <div class="inside">
                                <table class="form-table">
                                    <?php $this->render_fields($tab); ?>
                                </table>
                                <?php $this->submit_button("btn-sync-button"); ?>
                            </div>
                        </div>
                    </form>
					<?php }else{  ?>
					        <form method="POST" action="" >
                        <div class="postbox">
                            <div class="inside">
                                <table class="form-table">
                                    <?php $this->render_fields($tab); ?>
                                </table>
                                <?php $this->custom_button("start-mapping","mapping","Get started"); ?>
                            </div>
                        </div>
                    </form>
					<?php } ?>
                </div>
				
                <?php
            } else {

                $woocommerce_plugin_link = '<a target="_blank" href="https://wordpress.org/plugins/woocommerce/">Woocommerce plugin</a>';
                ?>
                <div class="postbox">
                    <div class="inside">
                        <h2>Install and Activate <?php echo $woocommerce_plugin_link; ?> to get started</h2>
                    </div>
                </div>
                <?php
            }
        }

        /**
         * Render the registered tabs
         * @param  string $active_tab the viewed tab
         * @return void          
         */
        public function render_tabs($active_tab = 'general') {

            if (count($this->tabs) > 1) {

                echo '<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">';

                foreach ($this->tabs as $key => $value) {

                    echo '<a href="' . admin_url('admin.php?page=' . $this->menu_options['slug'] . '&tab=' . $key) . '" class="nav-tab ' . ( ( $key == $active_tab ) ? 'nav-tab-active' : '' ) . ' ">' . $value . '</a>';
                }

                echo '</h2>';
                echo '<br/>';
            }
        }

        /**
         * Render the save button
         * @return void 
         */
        protected function save_button() {
            ?>
            <button type="submit" name="<?php echo $this->settings_id; ?>_save" class="button button-primary">
                <?php _e('Save', 'MY_PLUGIN_SLUG'); ?>
            </button>
            <?php
        }

        /**
         * Render the Submitt button
         * @return void 
         */
        protected function submit_button($elem_id) {
            ?>
            <button type="submit" id="<?php echo $elem_id; ?>" name="<?php echo $this->settings_id; ?>_submit" class="button button-primary">
                <?php _e('Submit', 'MY_PLUGIN_SLUG'); ?>
            </button>
            <?php
        }
		
		        /**
         * Render the Submitt button
         * @return void 
         */
        protected function custom_button($elem_id, $name, $title) {
            ?>
            <button type="submit" id="<?php echo $elem_id; ?>" name="<?php echo $this->settings_id; ?>_<?php echo $name ?>" class="button button-primary">
                <?php _e($title, 'MY_PLUGIN_SLUG'); ?>
            </button>
            <?php
        }

     /**
         * Render the dropdown list
         * @return void 
         */
        protected function dropdown_list($elem_id, $data) {
			
            ?>
			<select name="<?php echo $this->settings_id; ?>_<?php echo $elem_id ?>"  id="<?php echo $elem_id; ?>" class="" >
			<?php foreach($data as $key => $value){ ?>
			  <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
			<?php } ?>
			</select>

            <?php
			
			
        }
		
        /**
         * Save if the button for this menu is submitted
         * @return void 
         */
        protected function save_if_submit() {
	
            if (isset($_POST[$this->settings_id . '_save'])) {
                do_action('wordpressmenu_page_save_' . $this->settings_id);
            } elseif (isset($_POST[$this->settings_id . '_submit'])) {
                //$this->products_sync_process_import($_POST);
             

            } elseif (isset($_POST[$this->settings_id . '_mapping'])) {
				//$this->products_sync_process_mapping_fields($_POST);
			}
			
			return;
        }

        /**
         * Add a new integration to WooCommerce.
         */
        public function add_integration($integrations) {
            $integrations[] = 'WC_products_sync_Integration';
            return $integrations;
        }

        /**
         * action links that are shown in plugin page for the plugin such as delete, setting...
         * @param type $links
         * @return type
         */
        function WC_products_sync_action_links($links) {

            $action_links[] = '<a href="' . menu_page_url(MY_PLUGIN_SLUG, false) . '&tab=integration&section=products-sync-integration">Settings</a>';

            return array_merge($action_links, $links);
        }
		
		  /**
         * Processing sync and add products from external link(resource)
         * @param type $post_data data passed to process the sync (ex: api link)
         */
        function products_sync_process_mapping_fields($post_data) {
            echo '<h3>Mapping fields</h3>' . '<br>';

			
            $data = $this->call_external_data_url($post_data);
            $i = 0;
	
			$fields = array_keys(get_object_vars($data[0])); 
			
			?>

			<table>
			 <tbody>
				  <?php 
				  
				  $product_fields = array(""=> "No match",
				  "post_title"=>"Title",
				  "post_content"=>"Description",
				  "post_status"=>"Status",
				  "_regular_price"=>"Regular price",
				  "_price"=>"Price",
				  "_sku"=>"Sku");
				  
					  foreach ($fields as $field) {
						?>
							<tr>
							
								<td><?php echo $field; ?></td> 
								<td><?php $this->dropdown_list('product-fields', $product_fields);?></td>
							</tr>
				  <?php }  ?>
			 </tbody>
			</table>
			<?php
	
        }
		
		

        /**
         * Processing sync and add products from external link(resource)
         * @param type $post_data data passed to process the sync (ex: api link)
         */
        function products_sync_process_import($post_data) {
            echo '<h3>Products are added check products page</h3>' . '<br>';
            echo 'Processing...';

            $data = $this->call_external_data_url($post_data);
            $i = 0;
            if (!empty($data)) {

                foreach ($data as $product) {
                    if ($product->Name != "" || $product->Name != null) {

//                    if ($i == 3) {
//                        exit;
//                    }
                        $i++;
                        echo ($product->Name) . '<br>';
                        //echo ($product->Variant_Code).'<br>';
                        //echo ($product->Variant_Description).'<br>';
                        //	echo ($product->Price_Discounted).'<br>';
                        //	echo ($product->Discount_Percentage).'<br>';
                        //	echo ($product->Division).'<br>';
                        //	echo ($product->Net_Weight).'<br>';
                        //	echo ($product->Brand).'<br>';
                        //echo ($product->Published).'<br>';

                        $product_category = $product->Category;
                        // check if category exist 

                        if (empty(get_term_by('name', $product_category, 'product_cat'))) {

                            $category_term_data = wp_insert_term($product_category, 'product_cat', array(
                                'description' => '', // optional
                                'parent' => 0, // optional
                            ));

                            $category_id = $category_term_data['term_id'];
                        } else {
                            $category = get_term_by('name', $product_category, 'product_cat');
                            $category_id = $category->term_id;
                        }
                        if ($product->Published == 1) {
                            $status = "publish";
                        } else {
                            $status = "draft";
                        }
                        $product_array = array(
                            'post_title' => $product->Name,
                            'post_content' => $product->Description,
                            'post_status' => $status,
                            'post_type' => "product"
                        );
                        $sku = "SKU-" . $product->SKU;

                        $post_id = wp_insert_post($product_array);
                        wp_set_object_terms($post_id, 'simple', 'product_type');
                        wp_set_object_terms($post_id, $category_id, 'product_cat');
                        update_post_meta($post_id, '_regular_price', $product->Price_LBP);
                        update_post_meta($post_id, '_price', $product->Price_LBP);
                        update_post_meta($post_id, '_sku', $sku);
                        update_post_meta($post_id, '_visibility', 'visible');
                        update_post_meta($post_id, '_stock_status', 'instock');
                    }
                }
            }



            echo 'end Processing...';
        }

        /**
         * get content of external link (resource)
         * @param type $post_data
         * @return type
         */
        function call_external_data_url($post_data) {


        //$context = stream_context_create(array('http' => array('header' => 'Accept: application/x-www-form-urlencoded; charset=UTF-8')));
            //
            //$xml = file_get_contents($post_data['field_api_link'], false, $context);
            // for some reason from the external file we decoded it twice
            //$data = json_decode(json_decode($xml, true));

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $post_data['field_api_link']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch,CURLOPT_VERBOSE, true);
            if (curl_exec($ch) === FALSE) {
               die("Curl Failed: " . curl_error($ch));
            }

            $content = curl_exec($ch);
            curl_getinfo($ch);
            curl_close($ch);
            // for some reason from the external file we decoded it twice
            $data = json_decode(json_decode($content, true));
        
            return $data;
            
        }

        /**
         *  Plugin deactivation action .
         */
        function on_deactivation() {
            // Get the timestamp of the next scheduled run
            $timestamp = wp_next_scheduled('wc_product_price_updater');

            // Un-schedule the event
            wp_unschedule_event($timestamp, 'wc_product_price_updater');
        }

    }

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
	if(!isset($_POST[$WC_products_sync->settings_id . '_mapping'])) { 
		$WC_products_sync->add_field(array(
			'name' => 'field_api_link',
			'title' => 'External link to sync product',
			'desc' => 'This link can be changed from the Woocommerce integration ' . $links['setting'] . ' page',
			'default' => get_option('field_api_link', $integration_variable['rest_api_link']),
			'style' => 'width:30%;' . $css_background,
			'readonly' => $readonly
		));
	}
	if(isset($_POST[$WC_products_sync->settings_id . '_mapping'])) { 

		
		$WC_products_sync->products_sync_process_mapping_fields($_POST);
	}

endif;
