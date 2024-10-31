<?php

if(!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class WC_Integration_Profiler extends WC_Integration {
	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		global $woocommerce;
		$this->id                 = 'profiler';
		$this->method_title       = 'Profiler';
		$this->method_description = 'Basic integration with Profiler CRM. Pushes all purchases to RAPID.';

        // Load the settings.
		$this->init_form_fields();
		$this->init_settings();

        // Define user set variables.
		$this->instancedomainname          = $this->get_option( 'instancedomainname' );
		$this->dbname                      = $this->get_option( 'dbname' );
        $this->apikey                      = $this->get_option( 'apikey' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_' .  $this->id, array( $this, 'process_admin_options' ) );

        // Cron scheduling
        add_filter('cron_schedules', array($this, 'cron_interval') );
        $this->cron_schedule();
        add_action('wc_profiler_sync_orders', array($this, 'sync_orders'));

        // Metabox in Orders
        add_action('add_meta_boxes', function() {
            add_meta_box(
                'profiler_status',
                'Profiler Integration',
                array($this, 'metabox'),
                'shop_order',
                'side',
                'core'
            );  
        });
        add_action('admin_init', array($this, 'metabox_sync_now'));
	}

	/**
	 * Initialize integration settings form fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'instancedomainname' => array(
				'title'             => 'Profiler Instance Domain Name',
				'type'              => 'text',
				'description'       => 'e.g. example.profilersystem.com',
				'desc_tip'          => true,
				'default'           => '',
			),
            'dbname' => array(
				'title'             => 'Profiler Database Name',
				'type'              => 'text',
				'description'       => 'e.g. pf_example',
				'desc_tip'          => true,
				'default'           => ''
			),
            'apikey' => array(
				'title'             => 'Profiler API Key',
				'type'              => 'text',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => ''
			),
            'apipass' => array(
				'title'             => 'Profiler API Password',
				'type'              => 'text',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => ''
			),
            'sourcecode' => array(
				'title'             => 'Payment Source Code',
				'type'              => 'text',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => ''
			),
            'udf_sourcecode' => array(
				'title'             => 'UDF: Donation Source Code',
				'type'              => 'select',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => '',
                'options'           => $this->options_udf(),
			),
            'udf_clientip' => array(
				'title'             => 'UDF: Client IP Address',
				'type'              => 'select',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => '',
                'options'           => $this->options_udf(),
			),
            'udf_transactionid' => array(
				'title'             => 'UDF: Gateway Transaction ID',
				'type'              => 'select',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => '',
                'options'           => $this->options_udf(),
			),
		);
	}

    function sync_orders() {
        // Function to push pending orders to Profiler. Called via wp-cron.			

        if(empty($this->instancedomainname) || empty($this->dbname) || empty($this->apikey)) {
            return false;
        }

        // Fetch orders in the last hour
        $query = new WC_Order_Query(array(
            'date_created' => '>' . (time() - 3600), // Limit to 1 hour
            'status' => array('wc-processing', 'wc-completed', 'wc-pending'),
            'orderby' => 'date',
            'order' => 'DESC',
        ));

        $orders = $query->get_orders();

        // Loop over each order
        foreach($orders as $order) {

            // Ignore orders that have already processed successfully
            if($order->get_meta('profiler_success', true) == 'true') {
                continue;
            }

            // Don't sync orders made in the last 60 seconds. This is a workaround for Payment Gateway status callback timing issues.
            $order_date = $order->get_date_created();
            if($order_date != null && $order_date->getTimestamp() > time() - 60 && $order->needs_payment() == true) {
                continue;
            }

            $this->order_set_meta($order->get_id(), 'profiler_progress', 'started_cron_' . time());

            // Run the logic to place the order
            $this->place_order($order->get_id());
        }
    }

    function order_set_meta($order_id, $meta_field, $meta_value) {
        $order = wc_get_order($order_id);
        $order->update_meta_data($meta_field, $meta_value);
        $order->save();
    }

    public function place_order( $order_id ) {
        // Sends the data to Profiler

        // Get the Order object
        $order = new WC_Order( $order_id );

        // Get customer details
        $customer_id = $order->get_customer_id();
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();
        $email = $order->get_billing_email();
        $company_name = $order->get_billing_company();

        // Billing address
        $billing_address_1 = $order->get_billing_address_1();
        $billing_address_2 = $order->get_billing_address_2();
        $billing_city = $order->get_billing_city();
        $billing_state = $order->get_billing_state();
        $billing_postcode = $order->get_billing_postcode();
        $billing_country = $order->get_billing_country();

        // Get order details
        $order_items = $order->get_items();
        $total = $order->get_total();

        $order_comment = "Items: \n";

        foreach ($order_items as $item_id => $item_data) {
            $product = $item_data->get_product();
            $title = $product->get_title();
		    $sku = $product->get_sku();
            $qty = $item_data->get_quantity();

            $unit_price = ( $item_data->get_total() + $item_data->get_total_tax() ) / $item_data->get_quantity();
            $unit_price = number_format((float) $unit_price, 2, '.', '');

            $order_comment .= (!empty($sku) ? $sku . ": " : '') . $title . " (QTY: ".$qty.")" . "; \n";
        }

        // Is Payment still pending?
        if($order->needs_payment() == true) {
            $order_comment = "WOOCOMMERCE PENDING PAYMENT; \n" . $order_comment;
        }

        $order_comment .= "WooCommerce Payment Method: " . $order->get_payment_method_title() . "; ";

        // Build API payload
        $url = 'https://' . $this->instancedomainname . '/ProfilerAPI/Legacy/';
        $profiler_data = array(
            'DB' => $this->dbname,
            'apikey' => $this->apikey,
            'apipass' => $this->get_option('apipass'),
            'method' => 'integration.send',
            'datatype' => 'OLDON',

            'amount' => $total,
            'donationamount' => $total,
            'sourcecode' => $this->get_option('sourcecode'),
            'status' => 'Approved',

            'clientname' => $first_name . ' ' . $last_name,
            'firstname' => $first_name,
            'surname' => $last_name,

            'email' => $email,

            'address' => implode("\n", array($billing_address_1, $billing_address_2)),
            'suburb' => $billing_city,
            'state' => $billing_state,
            'postcode' => $billing_postcode,
            'country' => $billing_country,

            'org' => $company_name,
            'comments' => $order_comment,
        );

        if($this->get_option('udf_sourcecode') != '') {
            // Source code as a UDF
            $profiler_data['userdefined' . $this->get_option('udf_sourcecode')] = $this->get_option('sourcecode');
        }

        if($order->needs_payment()) {
            // Change status for payment pending
            $profiler_data['status'] = 'Pending';
        }

        if($this->get_option('udf_clientip') != '') {
            // Client IP Address
            $profiler_data['userdefined' . $this->get_option('udf_clientip')] = $this->get_client_ip_address();
        }

        if($this->get_option('udf_transactionid') != '') {
            // Payment Gateway Transaction ID
            $profiler_data['userdefined' . $this->get_option('udf_transactionid')] = $order->get_transaction_id();
        }

        // Push data to Profiler
        $api_result = $this->api_profiler($url, $profiler_data);

        // Store results as meta field
        $logsToStore = json_encode($api_result);
        $logsToStore = str_replace($this->get_option( 'apikey' ), "--REDACTED--", $logsToStore);
        $logsToStore = str_replace($this->get_option( 'apipass' ), "--REDACTED--", $logsToStore);

        $this->order_set_meta($order_id, 'profiler_success', 'true');
        $this->order_set_meta($order_id, 'profiler_log', $logsToStore);
        $this->order_set_meta($order_id, 'profiler_progress', 'completed_' . time());
    }

    public function metabox($post) {
        // Creates an Admin metabox on order screens to display the status and allow manual syncing

        if(!isset($post) || !isset($post->ID)) {
            return;
        }

        // Get current status
        $status = get_post_meta( $post->ID, 'profiler_success', true );

        // URL for forcing a sync now
        $sync_url = wp_nonce_url(get_admin_url(null, 'plugins.php?profiler_sync_now=true&order_id='.$post->ID), 'profiler_wc_sync_' . $post->ID);

        if($status == 'true') {
            echo "<p><strong>Successfuly sent to Profiler</strong></p>";

            // Status fields
            echo '<p>Last Progress: '.get_post_meta( $post->ID, 'profiler_progress', true ).'</p>';
            echo '<p>Log Data: <pre style="width: 100%; overflow: scroll;">'.get_post_meta( $post->ID, 'profiler_log', true ).'</pre></p>';

        } elseif($status == 'false') {
            echo "<p><strong>Failed to send to Profiler</strong></p>";

            // Status fields
            echo '<p>Last Progress: '.get_post_meta( $post->ID, 'profiler_progress', true ).'</p>';
            echo '<p>Log Data: <pre style="width: 100%; overflow: scroll;">'.get_post_meta( $post->ID, 'profiler_log', true ).'</pre></p>';

            echo '<a href="'.esc_url($sync_url).'" class="button button-primary">Push Now</a>';
        } else {
            echo "<p><strong>Profiler data push hasn't been attempted yet</strong></p>";

            echo '<a href="'.esc_url($sync_url).'" class="button button-primary">Push Now</a>';
        }

        $post_status = get_post_status($post);
        if($post_status != "wc-processing" && $post_status != "wc-completed" && $post_status != "wc-pending") {
            echo '<p>This Order can not be sent to Profiler while the status is '.$post_status.'</p>';
        }

    }
    
    public function metabox_sync_now() {

        if(!isset($_GET['order_id']) || !isset($_GET['profiler_sync_now'])) {
            return;
        }

        // Verify authenticity
    	check_admin_referer('profiler_wc_sync_' . $_GET['order_id']);

        if(isset($_GET['profiler_sync_now']) && $_GET['profiler_sync_now'] == "true") {
            if(isset($_GET['order_id']) && is_numeric($_GET['order_id'])) {
                $this->place_order($_GET['order_id']);
            }

            wp_die('Profiler Sync Finished');
        }
    }

    public function cron_interval( $schedules ) {
        $schedules['profiler_cron_interval'] = array(
            'interval'  =>  600,
            'display'   => 'Profiler 10 Minute Schedule',
        );

        return $schedules;
    }

    public function cron_schedule() {
        // Schedule for pushing order to Profiler
        if(!wp_next_scheduled('wc_profiler_sync_orders')) {
            wp_schedule_event(time(), 'profiler_cron_interval', 'wc_profiler_sync_orders');
        }
    }

    protected function api_profiler($url, $profiler_query, $ssl_mode = "normal") {
        // Sends the order data to Profiler via POST

        // Remove whitespace
        foreach($profiler_query as $key => $val) {
            $profiler_query[$key] = trim($val);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query(array("DB" => $profiler_query['DB'], "Call" => 'submit')));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen(http_build_query($profiler_query))));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($profiler_query));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if($ssl_mode == "bundled_ca") {
            // Use the CA Cert bundled with this plugin
            // Sourced from https://curl.haxx.se/ca/cacert.pem
            curl_setopt($ch, CURLOPT_CAINFO, plugin_dir_path(__DIR__) . "cacert.pem");

        } elseif($ssl_mode == "dontverifypeer") {
            // Don't verify the SSL peer. This is bad. No one should do this in production.
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        }

        $result = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if(curl_error($ch)) {
            $cURL_error = curl_error($ch);
        } else {
            $cURL_error = null;
        }

        curl_close($ch);

        // Redact some details
        $profiler_query['apikey'] = '--REDACTED--';
        $profiler_query['apipass'] = '--REDACTED--';

        return array(
            "httpstatus" => $status_code,
            "dataSent" => $profiler_query,
            "data" => $result,
            "dataXML" => simplexml_load_string($result),
            "dataArray" => json_decode(json_encode((array)simplexml_load_string($result)), 1),
            "cURLError" => $cURL_error,
            "cURL_SSL_Mode" => $ssl_mode,
        );
    }

    private function options_udf() {
        $fields = array("0" => "");

        for($i = 1; $i <= 99; $i++) {
            $fields[$i] = "User Defined Field " . $i;
        }

        return $fields;
    }

    private function get_client_ip_address() {
        // Returns the client's IP Address

        if (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
        } else if(getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        } else if(getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
        } else if(getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        } else if(getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
        } else if(getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
        } else {
            $ipaddress = 'UNKNOWN';
        }

        return $ipaddress;
    }
}
