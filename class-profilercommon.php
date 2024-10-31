<?php

class GFProfilerCommon extends GFFeedAddOn {
    protected $_path = "profiler-donation-gf/index.php";
    protected $_full_path = __FILE__;
    protected $_url = "";
    protected $_title = "Profiler / Gravity Forms - Integration Feed";

    protected $apifield_apikey = "apikey";
    protected $apifield_apipass = "apipass";
    protected $apifield_endpoint = "";
    protected $apifield_ipaddress = false;
    protected $apifield_formurl = false;
    protected $gffield_legacyname = "";
    protected $supports_custom_fields = false;
    protected $supports_mailinglists = false;

    protected $_capabilities_form_settings = 'gravityforms_edit_settings';

    public function init() {
        parent::init();

        // Stripe - force Customer creation
        add_filter('gform_stripe_customer_id',              array($this, 'stripe_customer_id'), 10, 4);
        add_action('gform_stripe_customer_after_create',    array($this, 'stripe_customer_id_save'), 10, 4);
        add_filter('gform_stripe_charge_pre_create',        array($this, 'stripe_payment_intent'), 10, 5);
        add_filter('gform_stripe_charge_description',       array($this, 'stripe_payment_description'), 10, 5);
        add_filter('gform_stripe_payment_element_initial_payment_information', array($this, 'stripe_elements_setup'), 10, 3);
    }

    public function feed_settings_fields() {
        // This function adds all the feed setting fields we need to communicate with Profiler
        
        $feed = $this->get_current_feed();

        // Get lists of the various types of fields
        $field_settings = $this->formFields();
        $hiddenFields = $this->hiddenFields();
        $checkboxRadioFields = $this->checkboxRadioFields();
        $checkboxFields = $this->checkboxFields();
        $userdefinedfields = $this->userDefinedFields();

        $numbers = array();
        for($i = 0; $i <= 99; $i++) {
            $numbers[] = array(
                "value" => $i,
                "label" => $i
            );
        }
        
        // All the fields to add to the feed:
        $fields = array();
        
        $fields[] = array(
            "label" => "Feed Name",
            "type" => "text",
            "name" => "feedName",
            "required" => true,
            "tooltip" => 'Enter a feed name to uniquely identify this setup'
        );
        
        $fields[] = array(
            "label" => 'Profiler Instance Domain Name',
            "type" => "text",
            "name" => "profiler".$this->gffield_legacyname."_instancedomainname",
            "required" => true,
            "tooltip" => "Your Instance Domain Name can be found in your login URL: e.g. 'https://instance.profiler.net.au/' is 'instance.profiler.net.au'",
        );
        
        $fields[] = array(
            "label" => 'Profiler Database Name',
            "type" => "text",
            "name" => "profiler".$this->gffield_legacyname."_dbname",
            "required" => true,
        );
        
        $fields[] = array(
            "label" => 'Profiler API Key',
            "type" => "text",
            "name" => "profiler".$this->gffield_legacyname."_apikey",
            "required" => true,
        );
        
        $fields[] = array(
            "label" => 'Profiler API Password',
            "type" => "text",
            "name" => "profiler".$this->gffield_legacyname."_apipass",
            "required" => true,
        );

        $fields[] = array(
            "label" => 'Profiler Errors Email Address',
            "type" => "text",
            "name" => "profiler".$this->gffield_legacyname."_erroremailaddress",
            "required" => false,
        );

        // Add in all the fields required by the child feed class
        $fields = array_merge($fields, $this->feed_settings_fields_custom());

        if($this->apifield_ipaddress == 'udf') {
            // Client's IP Address - UDF Field
            $fields[] = array(
                "label" => 'UDF: Client IP Address',
                "type" => "select",
                "name" => "profiler".$this->gffield_legacyname."_userdefined_clientip",
                "required" => false,
                "tooltip" => "Pick the Profiler User Defined Field you wish the client's IP address to be sent to",
                "choices" => $userdefinedfields,
            );
        }

        if($this->apifield_formurl === true) {
            $fields[] = array(
                "label" => 'UDF: Form URL',
                "type" => "select",
                "name" => "profiler".$this->gffield_legacyname."_userdefined_formurl",
                "required" => false,
                "tooltip" => "Pick the Profiler User Defined Field you wish the form's URL to be sent to.",
                "choices" => $userdefinedfields,
            );
        }

        // Mailing list support
        if($this->supports_mailinglists === true) {
            $fields[] = array(
                "label" => 'Number of Mailing Lists',
                "type" => "select",
                "name" => "profiler".$this->gffield_legacyname."_mailinglist_count",
                "required" => false,
                "tooltip" => "Select a quantity of Mailing Lists, save this page, and then configure them. You will need to refresh this page after saving to see the extra fields.",
                "choices" => $numbers,
                "default" => 0,
            );

            if(isset($feed['meta']['profiler'.$this->gffield_legacyname.'_mailinglist_count']) && is_numeric($feed['meta']['profiler'.$this->gffield_legacyname.'_mailinglist_count'])) {
                for($i = 1; $i <= $feed['meta']['profiler'.$this->gffield_legacyname.'_mailinglist_count']; $i++) {
                    // Loop over mailing list fields
    
                    $fields[] = array(
                        "label" => 'Mailing List #'.$i.': UDF',
                        "type" => "select",
                        "name" => "profiler".$this->gffield_legacyname."_mailinglist_".$i."_udf",
                        "required" => false,
                        "tooltip" => "Pick the Profiler User Defined Field you wish to use for this mailing",
                        "choices" => $userdefinedfields,
                    );
    
                    $fields[] = array(
                        "label" => 'Mailing List #'.$i.': UDF Text',
                        "type" => "text",
                        "name" => "profiler".$this->gffield_legacyname."_mailinglist_".$i."_udftext",
                        "required" => false,
                        "tooltip" => "Enter the string Profiler is expecting in this UDF",
                    );
    
                    $fields[] = array(
                        "label" => 'Mailing List #'.$i.': Field',
                        "type" => "select",
                        "name" => "profiler".$this->gffield_legacyname."_mailinglist_".$i."_field",
                        "tooltip" => 'Link it to a checkbox field - when checked, the mailing will be sent',
                        "required" => false,
                        "choices" => $checkboxFields
                    );
                }
            }
        }

        if($this->supports_custom_fields === true) {
            $fields[] = array(
                "label" => 'Number of Custom Fields',
                "type" => "select",
                "name" => "profiler_customfields_count",
                "required" => false,
                "tooltip" => "How many custom fields do you want to send back to Profiler? You will need to refresh this page after saving to see the extra fields.",
                "choices" => $numbers,
            );

            if(isset($feed['meta']['profiler_customfields_count']) && is_numeric($feed['meta']['profiler_customfields_count'])) {
                for($i = 1; $i <= $feed['meta']['profiler_customfields_count']; $i++) {
                    // Loop over custom fields
    
                    $fields[] = array(
                        "label" => 'Custom Field #'.$i.': UDF',
                        "type" => "select",
                        "name" => "profiler_customfield_".$i."_pffield",
                        "required" => false,
                        "tooltip" => "Pick the UDF field in Profiler you wish to use",
                        "choices" => $userdefinedfields,
                    );
    
                    $fields[] = array(
                        "label" => 'Custom Field #'.$i.': Gravity Forms Field',
                        "type" => "select",
                        "name" => "profiler_customfield_".$i."_gffield",
                        "required" => false,
                        "tooltip" => "Pick the field in Gravity Forms you wish to use",
                        "choices" => $field_settings,
                    );
                }
            }
        }

        $fields[] = array(
            "label" => 'Profiler Logs',
            "type" => "select",
            "name" => "profiler".$this->gffield_legacyname."_logs",
            "tooltip" => 'Link it to a Hidden field that will hold Profiler Response Logs',
            "required" => false,
            "choices" => $hiddenFields
        );
        
        $fields[] = array(
            "label" => 'SSL Mode',
            "type" => "select",
            "name" => "profiler".$this->gffield_legacyname."_sslmode",
            "required" => false,
            "choices" => array(
                array(
                    "value" => "normal",
                    "label" => "Normal"
                ),
                array(
                    "value" => "bundled_ca",
                    "label" => "Use Plugin Bundled CA Certs"
                ),
                array(
                    "value" => "dontverifypeer",
                    "label" => "Don't Verify SSL Peers (Super dangerous. Don't use this!!)"
                )
            ),
            "tooltip" => "Only change this if there is a legitimate technical reasons for doing so. This will cause insecurities. Use with caution."
        );

        $fields[] = array(
            'type'           => 'feed_condition',
            'name'           => 'feed_condition',
            'label'          => 'Feed Condition',
            'checkbox_label' => 'Enable Conditional Logic for this Feed',
            'instructions'   => 'This Feed will only be processed if the condition(s) specified here are met.'
        );

        return array(
            array(
                "title" => "Profiler Integration Settings",
                "fields" => $fields
            )
        );
        
    }

    public function process_feed($feed, $entry, $form, $fromValidatorProcessPFGateway = false) {
        // Processes the feed and prepares to send it to Profiler

        $form_id = $form["id"];
        $settings = $this->get_form_settings($form);
        
        // All the POST data for Profiler gets stored in this variable
        $postData = array();

        $postData['DB'] = $feed['meta']['profiler'.$this->gffield_legacyname.'_dbname'];
        $postData[$this->apifield_apikey] = $feed['meta']['profiler'.$this->gffield_legacyname.'_apikey'];
        $postData[$this->apifield_apipass] = $feed['meta']['profiler'.$this->gffield_legacyname.'_apipass'];

        if(empty($feed['meta']['profiler'.$this->gffield_legacyname.'_instancedomainname']) && !empty($feed['meta']['profiler'.$this->gffield_legacyname.'_instancename'])) {
            // Respect the setting from when we only accepted the first part of the domain name
            $feed['meta']['profiler'.$this->gffield_legacyname.'_instancedomainname'] = $feed['meta']['profiler'.$this->gffield_legacyname.'_instancename'] . ".profiler.net.au";
        }

        if(empty($feed['meta']['profiler'.$this->gffield_legacyname.'_instancedomainname']) && !empty($feed['meta']['profiler'.$this->gffield_legacyname.'_serveraddress'])) {
            $parse = parse_url($feed['meta']['profiler'.$this->gffield_legacyname.'_serveraddress']);
            $feed['meta']['profiler'.$this->gffield_legacyname.'_instancedomainname'] = $parse['host'];
        }

        // Build the URL for this API call
        $API_URL = "https://" . $feed['meta']['profiler'.$this->gffield_legacyname.'_instancedomainname'] . $this->apifield_endpoint;

        // Work out GF/API field mappings
        $fields = $this->feed_settings_fields()[0]['fields'];

        foreach($fields as $field) {
            if(isset($field['pf_apifield']) && $this->get_field_value($form, $entry, $feed['meta'][$field['name']]) != '') {
                $postData[$field['pf_apifield']] = trim($this->get_field_value($form, $entry, $feed['meta'][$field['name']]));
            }
        }

        if(isset($postData['country'])) {
            $postData['country'] = $this->get_country_name($postData['country']);
        }

        if($this->apifield_ipaddress != false && $this->apifield_ipaddress != 'udf') {
            // Client's IP Address - fixed field name
            $postData[$this->apifield_ipaddress] = $this->get_client_ip_address();

        } else if($this->apifield_ipaddress != false && $this->apifield_ipaddress == 'udf') {
            // Client's IP Address - UDF field
            $postData['userdefined' . $feed['meta']['profiler'.$this->gffield_legacyname.'_userdefined_clientip']] = $this->get_client_ip_address();

        }

        if($this->apifield_formurl === true && !empty($feed['meta']['profiler'.$this->gffield_legacyname.'_userdefined_formurl'])) {
            $postData['userdefined' . $feed['meta']['profiler'.$this->gffield_legacyname.'_userdefined_formurl']] = $entry['source_url'];
        }

        if(substr($entry['transaction_id'], 0, 3) == "pi_") {
            // Stripe Payment - find the Customer ID and Card ID, and pass it to the PF API

            try {
                if(!class_exists('\Stripe\Stripe')) {
                    require_once(plugin_dir_path(__DIR__) . 'gravityformsstripe/includes/autoload.php');
                }

                // Set Stripe API key.
                $stripe_options = get_option('gravityformsaddon_gravityformsstripe_settings');
                \Stripe\Stripe::setApiKey( $stripe_options[$stripe_options['api_mode'] . '_secret_key'] );
                
                // Get the Payment Intent
                $payment_intent = \Stripe\PaymentIntent::retrieve($entry['transaction_id']);

            } catch(Exception $e) {
                error_log("STRIPE/PROFILER SETUP ERROR: " . print_r($e, true));
            }

            if($feed['meta']['profilerdonation_userdefined_gatewaycustomerid'] !== "" && isset($payment_intent)) {
                // Gateway Customer ID
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_gatewaycustomerid']] = $payment_intent->customer;
            }

            if($feed['meta']['profilerdonation_userdefined_gatewaycardtoken'] !== "" && isset($payment_intent)) {
                // Gateway Card Token
                try {
                    $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_gatewaycardtoken']] = $payment_intent->charges->data[0]->payment_method;
                } catch(Exception $e) {
                    error_log("STRIPE/PROFILER CARD TOKEN ERROR: " . print_r($e, true));
                }
            }
        }

        // PayFURL-supplied Gateway Token
        if($feed['meta']['profilerdonation_userdefined_gatewaycardtoken'] !== "" && isset($_POST['payfurl_payment_details']['captured_payment']['payfurl_payment_method_id_provider'])) {
            $payfurl_provider_token = $_POST['payfurl_payment_details']['captured_payment']['payfurl_payment_method_id_provider'];
            
            if(!empty($payfurl_provider_token)) {
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_gatewaycardtoken']] = $payfurl_provider_token;
            }
        }

        // Custom Fields
        if($this->supports_custom_fields === true && !empty($feed['meta']['profiler_customfields_count'])) {
            for($i = 1; $i <= $feed['meta']['profiler_customfields_count']; $i++) {
                $postData["userdefined" . $feed['meta']["profiler_customfield_".$i."_pffield"]] = trim($this->get_field_value($form, $entry, $feed['meta']["profiler_customfield_".$i."_gffield"]));
            }
        }

        // Calculate mailing list subscriptions
        if($this->supports_mailinglists === true && isset($feed['meta']['profiler'.$this->gffield_legacyname.'_mailinglist_count']) && is_numeric($feed['meta']['profiler'.$this->gffield_legacyname.'_mailinglist_count'])) {
            for($i = 1; $i <= $feed['meta']['profiler'.$this->gffield_legacyname.'_mailinglist_count']; $i++) {
                // Loop over mailing list fields
                $mailingFieldValue = $this->get_field_value($form, $entry, $feed['meta']["profiler".$this->gffield_legacyname."_mailinglist_".$i."_field"]);
                $udf = $feed['meta']["profiler".$this->gffield_legacyname."_mailinglist_".$i."_udf"];
                $udfText = $feed['meta']["profiler".$this->gffield_legacyname."_mailinglist_".$i."_udftext"];

                if(!empty($udf) && !empty($udfText) && !empty($mailingFieldValue)) {
                    $postData['userdefined' . $udf] = $udfText;
                }

            }
        }

        // Allow filtering this via the child class
        if(method_exists($this, 'process_feed_custom')) {
            $postData = $this->process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway);

            if($postData === false) {
                return false;
            }

        }

        if(isset($postData['apiurl_override'])) {
            $API_URL = "https://" . $feed['meta']['profiler'.$this->gffield_legacyname.'_instancedomainname'] . $postData['apiurl_override'];
            unset($postData['apiurl_override']);
        }

        // Allow filtering the Profiler request
        $postData = apply_filters('profiler_integration_api_request_data', $postData, $form, $entry, $this->apifield_endpoint);

        // Send data to Profiler
        $pfResponse = $this->sendDataToProfiler($API_URL, $postData, $feed['meta']['profiler'.$this->gffield_legacyname.'_sslmode']);

        if($fromValidatorProcessPFGateway === false) {
            // Save Profiler response data back to the form entry
            $logsToStore = json_encode($pfResponse);
            $logsToStore = str_replace($postData['cardnumber'], "--REDACTED--", $logsToStore);
            $logsToStore = str_replace($postData[$this->apifield_apikey], "--REDACTED--", $logsToStore);
            $logsToStore = str_replace($postData[$this->apifield_apipass], "--REDACTED--", $logsToStore);
            $entry[$feed['meta']['profiler'.$this->gffield_legacyname.'_logs']] = htmlentities($logsToStore);
            GFAPI::update_entry($entry);

            if(method_exists($this, 'process_feed_success')) {
                $this->process_feed_success($feed, $entry, $form, $pfResponse, $postData);
            }   
        } else {
            return $pfResponse;
        }
    }

    public function feed_list_columns() {
        // Returns columns to feed index page
        return array(
            'feedName'  => 'Name',
            'profiler_dbname' => 'PF Database Name',
        );
    }
    
    protected function formFields($preface = "") {
        // Returns an array of all fields on this form
        
        $form = $this->get_current_form();

        if(!is_array($form) || !isset($form['fields'])) {
            return array();
        }

        $fields = $form['fields'];
        
        // An array holding all the fields on the form - will be returned
        $formfields = array(
            array(
                "value" => "",
                "label" => ""
            )
        );
        
        foreach ($fields as $key => $field) {
            if ($field["type"] == 'address' || ($field["type"] == 'name' && (!isset($field["nameFormat"]) || $field["nameFormat"] != 'simple'))) {
                // Address and name are handled specially
                foreach ($field['inputs'] as $keyvalue => $inputvalue) {
                    $field_settings = array();
                    $field_settings['value'] = $inputvalue['id'];
                    $field_settings['label'] = $preface . $inputvalue['label'];
                    $formfields[] = $field_settings;
                }
            } elseif($field["type"] != "creditcard") {
                // Process all fields except credit cards - we don't want them in the list
                $field_settings = array();
                $field_settings['value'] = $field['id'];
                $field_settings['label'] = $preface . $field['label'];
                $formfields[] = $field_settings;
            }
        }
        
        return $formfields;
    }
    
    
    protected function hiddenFields() {
        // Returns an array of hidden fields
        
        $form = $this->get_current_form();

        if(!is_array($form) || !isset($form['fields'])) {
            return array();
        }

        $fields = $form['fields'];
        
        // An array holding all the hidden fields on the form - will be returned
        $formfields = array(
            array(
                "value" => "",
                "label" => ""
            )
        );
        
        foreach ($fields as $key => $field) {
            if ($field['type'] == 'hidden') {
                $formfields[] = array(
                    "value" => $field['id'],
                    "label" => $field['label']
                );
            }
        }
        
        return $formfields;
    }

    protected function userDefinedFields() {
        
        $fields = array(array(
                "value" => "",
                "label" => "None",
            ));
        
        for($i = 1; $i <= 99; $i++) {
            $fields[] = array(
                "value" => $i,
                "label" => "User Defined Field " . $i,
            );
        }
        
        return $fields;
        
    }
    
    
    protected function checkboxRadioFields() {
        // Returns an array of checkbox and radio fields
        
        $form = $this->get_current_form();

        if(!is_array($form) || !isset($form['fields'])) {
            return array();
        }

        $fields = $form['fields'];
        
        // An array holding all the hidden fields on the form - will be returned
        $formfields = array(
            array(
                "value" => "",
                "label" => ""
            )
        );
        
        foreach ($fields as $key => $field) {
            if ($field['type'] == 'checkbox' || $field['type'] == 'radio') {
                foreach($field['inputs'] as $input) {
                    $formfields[] = array(
                        "value" => $input['id'],
                        "label" => "Field #" . $input['id'] . " - " . $field['label'] . " / " . $input['label']
                    );
                }
            }
        }

        return $formfields;
    }

    protected function checkboxFields() {
        // Returns an array of checkbox fields

        $form = $this->get_current_form();

        if(!is_array($form) || !isset($form['fields'])) {
            return array();
        }

        $fields = $form['fields'];

        // An array holding all the hidden fields on the form - will be returned
        $formfields = array(
            array(
                "value" => "",
                "label" => ""
            )
        );

        foreach ($fields as $key => $field) {
            if ($field['type'] == 'checkbox') {
                foreach($field['inputs'] as $input) {
                    $formfields[] = array(
                        "value" => $input['id'],
                        "label" => "Field #" . $input['id'] . " - " . $field['label'] . " / " . $input['label']
                    );
                }
            }
        }

        return $formfields;
    }

    protected function selectFields() {
        // Returns an array of checkbox and radio fields
        
        $form = $this->get_current_form();

        if(!is_array($form) || !isset($form['fields'])) {
            return array();
        }

        $fields = $form['fields'];
        
        // An array holding all the hidden fields on the form - will be returned
        $formfields = array(
            array(
                "value" => "",
                "label" => ""
            )
        );
        
        foreach ($fields as $key => $field) {
            if ($field['type'] == 'select') {
                $formfields[] = array(
                    "value" => $field['id'],
                    "label" => $field['label']
                );
            }
        }

        return $formfields;
    }

    protected function productFields() {
        // Returns product fields and total field
        
        $form = $this->get_current_form();

        if(!is_array($form) || !isset($form['fields'])) {
            return array();
        }

        $fields = $form['fields'];
        
        // An array holding all the product fields on the form - will be returned
        $formfields = array(
            array(
                "value" => "",
                "label" => ""
            )
        );
        
        foreach ($fields as $key => $field) {
            if ($field['type'] == 'product' || $field['type'] == 'profilerdonate' || $field['type'] == 'total') {
                if ($field['type'] == 'total') {
                    $totalFieldExists = True;
                }
                
                $formfields[] = array(
                    "value" => $field['id'],
                    "label" => $field['label']
                );
            }
        }

        // Add a form total field - handled specially by our plugin
        $formfields[] = array(
            "value" => "total",
            "label" => "Form Total"
        );

        return $formfields;
    }
    
    protected function sendDataToProfiler($url, $profiler_query, $ssl_mode = "normal") {
        // Sends the donation and client data to Profiler via POST

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
            curl_setopt($ch, CURLOPT_CAINFO, plugin_dir_path(__FILE__) . "cacert.pem");

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

    public function sendFailureEmail($entry, $form, $pfResponse, $sendTo) {
        // Sends an alert email if integration with Profiler failed

        if(!isset($pfResponse['dataArray']['error'])) {
            $pfResponse['dataArray']['error'] = "";
        }

        $headers = '';
        $message = "--- PROFILER DATA FAILURE #" . $form["id"] . "/" . $entry["id"] . " ---" . "\n\n";
        $message .= "Gravity Form #" . $form["id"] . " with Entry ID #" . $entry["id"] . " failed to be sent to the Profiler API.\r\n";
        $message .= "HTTP Status Code: " . $pfResponse['httpstatus'] . "\r\n";
        $message .= "Profiler Error Message: " . $pfResponse['dataArray']['error'] . "\r\n";
        $message .= "\r\n\r\n";
        $message .= "This is the data that was sent to the Profiler API:\r\n";

        foreach($pfResponse['dataSent'] as $key => $val) {
            if($key == "apikey" || $key == "apipass" || $key == "cardnumber" || $key == "api_user" || $key == "api_pass" || $key == $this->apifield_apikey || $key == $this->apifield_apipass) {
                $val = "--REDACTED--";
            }
            $message .= $key . ": " . $val . "\r\n";
        }

        wp_mail($sendTo, "Profiler API Failure", $message, $headers);
    }

    protected function get_feed_instance($form, $entry) {
        // Get all feeds and picks the first.
        // Realistically we'll only have one active Profiler feed per form

        $feeds = $this->get_feeds($form['id']);

        foreach($feeds as $feed) {
            if ($feed['is_active'] && $this->is_feed_condition_met($feed, $form, $entry)) {
                return $feed;
            }
        }

        return false;
    }

    public function get_country_name($country_code) {
        $countries = GF_Fields::get('address')->get_countries();

        foreach($countries as $key => $val) {
            if(strtoupper($key) == strtoupper($country_code)) {
                return $val;
            }
        }

        // Code not found, fall back to the supplied code...
        return $country_code;
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

    public function save_feed_settings($feed_id, $form_id, $settings) {
        // We override this function in order to trigger an update of the custom fields cache
        $result = parent::save_feed_settings($feed_id, $form_id, $settings);

        return $result;

    }

    public function getTotalAmount($entry, $form = null) {
        // Returns the total amount as a float
        if(!isset($entry['payment_amount']) && $form !== null) {
            return GFCommon::get_order_total($form, $entry);
        }
        
        return (float)$entry['payment_amount'];
        
    }

    public function getCardDetails($form) {
        // Returns an array with all the credit card details
        
        $details = array(
            "type" => false,
            "number" => False,
            "expiry_month" => False,
            "expiry_year" => False,
            "ccv" => false,
            "name" => False,
            "usingSpecialCardField" => False,
        );
        
        foreach ($form["fields"] as $fieldkey => $field) {
            if ($field['type'] == 'creditcard' && !RGFormsModel::is_field_hidden($form, $field, array())) {
                $details['number'] = rgpost('input_' . $field['id'] . '_1');
                $details['type'] = $this->getCardTypeFromNumber($details['number']);
                
                $ccdate_array = rgpost('input_' . $field['id'] . '_2');
                
                $details['expiry_month'] = $ccdate_array[0];
                if (strlen($details['expiry_month']) < 2) {
                    $details['expiry_month'] = '0' . $details['expiry_month'];
                }
                
                $details['expiry_year'] = $ccdate_array[1];
                if (strlen($details['expiry_year']) <= 2) {
                    $details['expiry_year'] = '20'.$ccdate_year;
                }
                
                $details['name'] = rgpost('input_' . $field['id'] . '_5');
                $details['ccv'] = rgpost('input_' . $field['id'] . '_3');
            }
        }
        
        if(isset($_POST['gf_pf_cardnum']) && !empty($_POST['gf_pf_cardnum'])) {
            $details['number'] = $_POST['gf_pf_cardnum'];
            $details['usingSpecialCardField'] = True;
            $details['type'] = $this->getCardTypeFromNumber($details['number']);
        }
        
        return $details;
        
    }
    
    public function getCardTypeFromNumber($number) {
        // Atempts to parse the credit card number and return the card type (Visa, MC, etc.)
        // From http://wephp.co/detect-credit-card-type-php/
        
        $number = preg_replace('/[^\d]/','', $number);
        
        if (preg_match('/^3[47][0-9]{13}$/', $number)) {
            return 'Amex';
        } elseif (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/', $number)) {
            return 'Diner';
        } elseif (preg_match('/^6(?:011|5[0-9][0-9])[0-9]{12}$/', $number)) {
            return 'Discover';
        } elseif (preg_match('/^(?:2131|1800|35\d{3})\d{11}$/', $number)) {
            return 'JCB';
        } elseif (preg_match('/^5[1-5][0-9]{14}$/', $number)) {
            return 'Master';
        } elseif (preg_match('/^4[0-9]{12}(?:[0-9]{3})?$/', $number)) {
            return 'Visa';
        } else {
            return 'Unknown';
        }

    }

    protected function clean_amount($entry) {
        // Clean up pricing amounts
        
        $entry = preg_replace("/\|(.*)/", '', $entry); // replace everything from the pipe symbol forward
        if (strpos($entry, '.') === false) {
            $entry .= ".00";
        }
        if (strpos($entry, '$') !== false) {
            $startsAt = strpos($entry, "$") + strlen("$");
            $endsAt = strlen($entry);
            $amount = substr($entry, 0, $endsAt);
            $amount = preg_replace("/[^0-9,.]/", "", $amount);
        } else {
            $amount = preg_replace("/[^0-9,.]/", "", sprintf("%.2f", $entry));
        }

        $amount = str_replace('.', '', $amount);
        $amount = str_replace(',', '', $amount);
        return $amount;
    }

    protected function creditcard_mask($number) {
        // Returns a credit card with all but the first six and last four numbers masked

        if(strlen($number) < 11) {
            // Prevents a fatal error on str_repeat in PHP8
            return '';
        }

        return implode("-", str_split(substr($number, 0, 6) . str_repeat("X", strlen($number) - 10) . substr($number, -4), 4));
    }

    public function enable_creditcard($is_enabled) {
        return true;
    }

    public function metabox_payments($meta_boxes, $entry, $form) {
        // Allows the Payment Meta Box to be displayed on the 'Entries' screen
        // From https://www.gravityhelp.com/documentation/article/gform_entry_detail_meta_boxes/
        
        if (!isset($meta_boxes['payment'])) {
            $meta_boxes['payment'] = array(
                'title'         => 'Payment Details',
                'callback'      => array('GFEntryDetail', 'meta_box_payment_details'),
                'context'       => 'side',
                'callback_args' => array($entry, $form),
            );
        }

        return $meta_boxes;
    }

    protected function gformEntryPostSave($entry, $form, $gatewaydata) {
        // Log the successful gateway data

        foreach ($gatewaydata as $key => $val) {
            switch ($key) {
                case 'payment_status':
                case 'payment_date':
                case 'payment_amount':
                case 'transaction_id':
                case 'transaction_type':
                case 'payment_gateway':
                case 'authcode':
                    // update entry
                    $entry[$key] = $val;
                    break;

                default:
                    // update entry meta
                    gform_update_meta($entry['id'], $key, $val);
                    break;
            }
        }

        GFAPI::update_entry($entry);

        return $entry;
    }

    public function stripe_customer_id($customer_id, $feed, $entry, $form) {
        // Create a new customer in Stripe

        // Find email address field
        foreach($form['fields'] as &$field) {
            if($field->type == "email") {
                $email = $this->get_field_value($form, $entry, $field->id);
            }
        }

        // Find name field on form
        $name = '';
        foreach($form['fields'] as $fieldKey => &$field) {
            if($field->type == "name") {
                $name = $entry[$field->id . '.3'] . ' ' . $entry[$field->id . '.6'];
            }
        }

        if(!isset($email)) {
            return $customer_id;
        }

        if(class_exists('\Stripe\Customer')) {
            // Find an existing customer by email
            $stripe_all_customers = \Stripe\Customer::all(array(
                'email' => $email,
                'limit' => 1
            ));

            if(isset($stripe_all_customers['data'][0]['id'])) {
                // Update the name of the customer
                if(!empty($name)) {
                    $update = \Stripe\Customer::update(
                        $stripe_all_customers['data'][0]['id'],
                        array(
                            'name' => $name
                        )
                    );
                }

                // Return existing customer ID
                return $stripe_all_customers['data'][0]['id'];
            }
        }

        // Create a new customer
        $customer_meta = array();
        $customer_meta['description'] = $email . ' ' . $name;
        $customer_meta['name'] = $name;
        $customer_meta['email'] = $email;

        $customer = gf_stripe()->create_customer($customer_meta, $feed, $entry, $form);
        return $customer->id;
    }

    public function stripe_customer_id_save($customer, $feed, $entry, $form) {
        // Get the new Stripe Customer ID and save for later use
        gform_update_meta($entry['id'], 'stripe_customer_id', $customer->id);
        return $customer;
    }

    public function stripe_payment_intent($charge_meta, $feed, $submission_data, $form, $entry) {
        $charge_meta['setup_future_usage'] = 'off_session';
        return $charge_meta;
    }

    public function stripe_elements_setup($intent_information, $feed, $form) {
        $intent_information['setup_future_usage'] = 'off_session';
        return $intent_information; 
    }

    public function stripe_payment_description($description, $strings, $entry, $submission_data, $feed) {

        if(!class_exists('GFAPI') || !isset($entry['form_id'])) {
            return $description;
        }

        $description = 'Form #' . $entry['form_id'] . ', Entry #' . $entry['id'];

        // Find Name field
        $form = GFAPI::get_form($entry['form_id']);
        foreach($form['fields'] as $fieldKey => $field) {
            if($field->type == "name") {
                $description .= ' - ' . $entry[$field->id . '.3'] . ' ' . $entry[$field->id . '.6'];
            }
        }

        return $description;

    }
}

?>