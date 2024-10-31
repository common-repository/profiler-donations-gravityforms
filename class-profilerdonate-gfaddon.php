<?php

class GFProfilerDonate extends GFProfilerCommon {
    protected $_slug = "profiler-donation-gf";
    protected $_title = "Profiler / Gravity Forms - Donation Integration Feed";
    protected $_short_title = "Profiler Donations";
    protected $formid;
    protected $form;
    protected $gateways;
    protected static $_instance = null;

    protected $apifield_endpoint = "/ProfilerAPI/Legacy/";
    protected $apifield_apikey = "apikey";
    protected $apifield_apipass = "apipass";
    protected $apifield_ipaddress = 'udf';
    protected $apifield_formurl = true;
    protected $gffield_legacyname = "donation";
    protected $supports_custom_fields = true;
    protected $supports_mailinglists = true;

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFProfilerDonate();
        }

        self::$_instance->form = self::$_instance->get_current_form();
        if(is_array(self::$_instance->form)) {
            self::$_instance->formid = self::$_instance->form["id"];
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();

        //Add the "total amount" merge field:
        add_filter('gform_replace_merge_tags',          array($this, 'mergeTag_totalAmount'), 10, 7);
        add_action('gform_admin_pre_render',            array($this, 'addMergeTags'));

        // Filter to allow Profiler to process payments internally (instead of a gateway in Gravity Forms)
        add_filter("gform_validation",                  array($this, "validate_payment"), 1000);

        // Enable credit card fields
        add_filter('gform_enable_credit_card_field',    array($this, "enable_creditcard"), 11);

        // Filter to ensure the Payment Meta-Box is displayed
        add_filter("gform_entry_detail_meta_boxes",     array($this, "metabox_payments"), 10, 3);

        // Allow processing this feed after PayPal payments have been made
        $this->add_delayed_payment_support(
            array(
                'option_label' => 'Send data to Profiler only when payment is received.',
            )
        );
    }
    
    public function feed_settings_fields_custom() {
        // This function adds all the feed setting fields we need to communicate with Profiler

        $form = $this->get_current_form();
        $feed = $this->get_current_feed();

        $field_settings = self::$_instance->formFields();
        $product_field_settings = self::$_instance->productFields();
        $hiddenFields = self::$_instance->hiddenFields();
        $checkboxRadioFields = self::$_instance->checkboxRadioFields();
        $checkboxFields = $this->checkboxFields();
        $userdefinedfields = self::$_instance->userDefinedFields();

        // All the fields to add to the feed:
        $fields = array();

        $field_gateway_choices = array(
            array(
                'label'         => 'No - Gravity Forms will Process Payments',
                'value'         => 'false',
            ),
        );

        if(apply_filters('profiler_integration_allow_profiler_gateway', true) == true) {
            // This filter allows us to disable the Profiler Gateway feature on a site
            $field_gateway_choices[] = array(
                'label'         => 'Yes - Profiler will Process Payments',
                'value'         => 'true',
            );
        }

        $fields[] = array(
            "label" => 'Use Profiler As A Gateway?',
            "type" => "select",
            "name" => "profilerdonation_useasgateway",
            "required" => false,
            "tooltip" => "Set this to 'Yes' if you want Profiler to be responsible for processing the payment (instead of a
                          Gravity Forms Payment Plugin). If you use this option, disable any other Payment Plugins.",
            'choices' => $field_gateway_choices,
        );

        $fields[] = array(
            "label" => 'Amount Field',
            "type" => "select",
            "name" => "profilerdonation_amount",
            "required" => true,
            "choices" => $product_field_settings
        );
        
        $fields[] = array(
            "label" => 'Donation Type',
            "type" => "select",
            "name" => "profilerdonation_donationtype",
            "required" => false,
            "choices" => $field_settings,
            "tooltip" => "The value of this field must be set to 'once' or 'regular'"
        );
        
        $fields[] = array(
            "label" => 'Payment Method',
            "type" => "select",
            "name" => "profilerdonation_paymentmethod",
            "required" => false,
            "choices" => $field_settings,
            "tooltip" => "The value of this field must be set to 'creditcard', 'bankdebit', 'bankdeposit', or 'paypal'. If this field isn't set, or an invalid value is passed, we assume it's a credit card."
        );
        
        $fields[] = array(
            "label" => 'Pledge Frequency',
            "type" => "select",
            "name" => "profilerdonation_pledgefreq",
            "required" => false,
            "choices" => $field_settings,
            "tooltip" => "The value of this field must be set to 'monthly' or 'yearly'. This field will be used if 'Donation Type' is set to 'regular'."
        );
        
        $fields[] = array(
            "label" => 'Pledge Amount',
            "type" => "select",
            "name" => "profilerdonation_pledgeamount",
            "required" => false,
            "choices" => $product_field_settings,
            "tooltip" => "This amount field will be used if Donation Type is set to 'regular'.",
        );

        $fields[] = array(
            "label" => 'UDF: Pledge Type ID',
            "type" => "select",
            "name" => "profilerdonation_userdefined_pledgetypeid",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the Pledge Type ID to be sent to",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'Pledge Type ID',
            "type" => "select",
            "name" => "profilerdonation_pledgetypeid",
            "required" => false,
            "choices" => $field_settings,
            "tooltip" => "Set this field to the Pledge Type ID"
        );

        $fields[] = array(
            "label" => 'Pledge Type ID (Default)',
            "type" => "text",
            "name" => "profilerdonation_pledgetypeid_default",
            "required" => false,
            "tooltip" => "Set a default Pledge Type ID, in case the above field isn't set"
        );

        $fields[] = array(
            "label" => 'Client: Title',
            "type" => "select",
            "name" => "profilerdonation_clienttitle",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "title",
        );
        
        $fields[] = array(
            "label" => 'Client: First Name',
            "type" => "select",
            "name" => "profilerdonation_clientfname",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "firstname",
        );
        
        $fields[] = array(
            "label" => 'Client: Last Name',
            "type" => "select",
            "name" => "profilerdonation_clientlname",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "surname",
        );
        
        $fields[] = array(
            "label" => 'Client: Email',
            "type" => "select",
            "name" => "profilerdonation_clientemail",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "email",
        );
        
        $fields[] = array(
            "label" => 'Client: Address',
            "type" => "select",
            "name" => "profilerdonation_clientaddress",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "address",
        );

        $fields[] = array(
            "label" => 'Client: City',
            "type" => "select",
            "name" => "profilerdonation_clientcity",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "suburb",
        );
        
        $fields[] = array(
            "label" => 'Client: State',
            "type" => "select",
            "name" => "profilerdonation_clientstate",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "state",
        );
        
        $fields[] = array(
            "label" => 'Client: Zip/Postcode',
            "type" => "select",
            "name" => "profilerdonation_clientpostcode",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "postcode",
        );
        
        $fields[] = array(
            "label" => 'Client: Country',
            "type" => "select",
            "name" => "profilerdonation_clientcountry",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "country",
        );
        
        $fields[] = array(
            "label" => 'Client: Organisation',
            "type" => "select",
            "name" => "profilerdonation_clientorganisation",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "org",
        );
        
        $fields[] = array(
            "label" => 'Client: Home Phone',
            "type" => "select",
            "name" => "profilerdonation_clientphoneah",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "phoneah",
        );
        
        $fields[] = array(
            "label" => 'Client: Business Phone',
            "type" => "select",
            "name" => "profilerdonation_clientphonebus",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "phonebus",
        );
        
        $fields[] = array(
            "label" => 'Client: Mobile Phone',
            "type" => "select",
            "name" => "profilerdonation_clientphonemobile",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "phonemobile",
        );
        
        $fields[] = array(
            "label" => 'Client: Website',
            "type" => "select",
            "name" => "profilerdonation_clientwebsite",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "website",
        );
        
        $fields[] = array(
            "label" => 'Bank Debit: Account Name',
            "type" => "select",
            "name" => "profilerdonation_bankdebit_accountname",
            "required" => false,
            "choices" => $field_settings
        );
        
        $fields[] = array(
            "label" => 'Bank Debit: BSB',
            "type" => "select",
            "name" => "profilerdonation_bankdebit_bsb",
            "required" => false,
            "choices" => $field_settings
        );
        
        $fields[] = array(
            "label" => 'Bank Debit: Account Number',
            "type" => "select",
            "name" => "profilerdonation_bankdebit_accountnumber",
            "required" => false,
            "choices" => $field_settings
        );
        
        $fields[] = array(
            "label" => 'Comments',
            "type" => "select",
            "name" => "profilerdonation_comments",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "comments",
        );

        $fields[] = array(
            "label" => 'UDF: Receipt Name',
            "type" => "select",
            "name" => "profilerdonation_userdefined_receiptname",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the donation receipt name to be sent to",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'Receipt Name Field',
            "type" => "select",
            "name" => "profilerdonation_receiptname",
            "required" => false,
            "choices" => $field_settings,
        );
        
        $fields[] = array(
            "label" => 'UDF: Donation Source Code',
            "type" => "select",
            "name" => "profilerdonation_userdefined_sourcecode",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the donation source code to be sent to",
            "choices" => $userdefinedfields,
        );
        
        $fields[] = array(
            "label" => 'Donation Source Code - Default Value',
            "type" => "text",
            "name" => "profilerdonation_sourcecode",
            "required" => false,
            "tooltip" => "Can be overriden by GET parameter or Short Code. Sent to the UDF specificed above.",
        );

        $fields[] = array(
            "label" => 'Donation Source Code - Mode',
            "type" => "select",
            "name" => "profilerdonation_sourcecodemode",
            "required" => false,
            "tooltip" => "Donation Source Codes can be set the normal way (from shortcodes, URL Parameters, and defaults), or set based on the value of a specific form field.",
            "choices" => array(
                    array(
                        "label" => "Regular Behaviour (use Shortcodes, URL Parameters and Defaults)",
                        "value" => "normal"
                    )
                )
                + self::$_instance->formFields("Form Field: "),
        );
        
        $fields[] = array(
            "label" => 'UDF: Pledge Source Code',
            "type" => "select",
            "name" => "profilerdonation_userdefined_pledgesourcecode",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the pledge source code to be sent to",
            "choices" => $userdefinedfields,
        );
        
        $fields[] = array(
            "label" => 'Pledge Source Code - Default Value',
            "type" => "text",
            "name" => "profilerdonation_pledgesourcecode",
            "required" => false,
            "tooltip" => "Can be overriden by GET parameter or Short Code. Sent to the UDF specificed above.",
        );

        $fields[] = array(
            "label" => 'Pledge Source Code - Mode',
            "type" => "select",
            "name" => "profilerdonation_pledgesourcecodemode",
            "required" => false,
            "tooltip" => "Pledge Source Codes can be set the normal way (from shortcodes, URL Parameters, and defaults), or set based on the value of a specific form field.",
            "choices" => array(
                    array(
                        "label" => "Regular Behaviour (use Shortcodes, URL Parameters and Defaults)",
                        "value" => "normal"
                    )
                )
                + self::$_instance->formFields("Form Field: "),
        );
        
        $fields[] = array(
            "label" => 'UDF: Pledge Acquisition Code',
            "type" => "select",
            "name" => "profilerdonation_userdefined_pledgeacquisitioncode",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the pledge acquisition code to be sent to",
            "choices" => $userdefinedfields,
        );
        
        $fields[] = array(
            "label" => 'Pledge Acquisition Code - Default Value',
            "type" => "text",
            "name" => "profilerdonation_pledgeacquisitioncode",
            "required" => false,
            "tooltip" => "Can be overriden by GET parameter or Short Code",
        );

        $fields[] = array(
            "label" => 'UDF: Client Acquisition',
            "type" => "select",
            "name" => "profilerdonation_userdefined_clientacquisitioncode",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the client acquisition code to be sent to.",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'Client Acquisition Field',
            "type" => "select",
            "name" => "profilerdonation_clientacquisitioncode",
            "required" => false,
            "tooltip" => "This field's value should match the Client Acquisition Codes setup within Profiler.",
            "choices" => $field_settings
        );

        $fields[] = array(
            "label" => 'UDF: Donation Purpose',
            "type" => "select",
            "name" => "profilerdonation_userdefined_donationpurposecode",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the donation's purpose code to be sent to",
            "choices" => $userdefinedfields,
        );
        
        $fields[] = array(
            "label" => 'Donation Purpose Field',
            "type" => "select",
            "name" => "profilerdonation_donationpurposecode",
            "required" => false,
            "tooltip" => "This field's value should match the Purpose Codes setup within Profiler.",
            "choices" => $field_settings
        );

        $fields[] = array(
            "label" => 'UDF: Donation Tag',
            "type" => "select",
            "name" => "profilerdonation_userdefined_donationtagcode",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the donation's tag code to be sent to. Do not set this up if you use Tag Automation within Profiler.",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'Donation Tag Field',
            "type" => "select",
            "name" => "profilerdonation_donationtagcode",
            "required" => false,
            "tooltip" => "This field's value should match the Tag Codes setup within Profiler.",
            "choices" => $field_settings
        );

        $fields[] = array(
            "label" => 'UDF: Client Preferred Contact Method',
            "type" => "select",
            "name" => "profilerdonation_userdefined_clientpreferredcontactmethod",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the client's preferred contact method to be sent to",
            "choices" => $userdefinedfields,
        );
        
        $fields[] = array(
            "label" => 'Client Preferred Contact Method Field',
            "type" => "select",
            "name" => "profilerdonation_clientpreferredcontactmethod",
            "required" => false,
            "tooltip" => "This field's value should match one of these codes: E = Email; P = Post; T = Phone; S = SMS; N = No Contact Preferred",
            "choices" => $field_settings
        );

        $fields[] = array(
            "label" => 'UDF: Client Privacy Preference',
            "type" => "select",
            "name" => "profilerdonation_userdefined_clientprivacypreference",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the client's privacy preference (true/false) to be sent to",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'Client Privacy Preference Field',
            "type" => "select",
            "name" => "profilerdonation_clientprivacypreference",
            "required" => false,
            "tooltip" => "If this checkbox is true, this client will be marked as 'private'.",
            "choices" => $checkboxFields
        );

        $fields[] = array(
            "label" => 'UDF: Client IP Address',
            "type" => "select",
            "name" => "profilerdonation_userdefined_clientip",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the client's IP address to be sent to",
            "choices" => $userdefinedfields,
        );
        
        $fields[] = array(
            "label" => 'UDF: Gateway Transaction ID / Gateway Response',
            "type" => "select",
            "name" => "profilerdonation_userdefined_gatewaytransactionid",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the gateway transaction ID to be sent to (certain gateways only)",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'UDF: Payment Gateway ID Used',
            "type" => "select",
            "name" => "profilerdonation_userdefined_paymentgatewayidused",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the payment gateway ID to be sent to",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'Payment Gateway ID Used (Field)',
            "type" => "select",
            "name" => "profilerdonation_paymentgatewayidused",
            "required" => false,
            "tooltip" => "Pick the Profiler Gateway ID used for this transaction (if you are conditionally using multiple gateways in Gravity Forms)",
            "choices" => $field_settings
        );

        $fields[] = array(
            "label" => 'Payment Gateway ID Used (Default)',
            "type" => "text",
            "name" => "profilerdonation_paymentgatewayidused_default",
            "required" => false,
            "tooltip" => "Specify a default Payment Gateway ID to send through to Profiler",
            "choices" => $field_settings
        );

        if(function_exists('gf_stripe') || class_exists('GF_PayFURL')) {
            // Stripe & PayFURL only fields
            $fields[] = array(
                "label" => 'UDF: Payment Gateway Card Token',
                "type" => "select",
                "name" => "profilerdonation_userdefined_gatewaycardtoken",
                "required" => false,
                "tooltip" => "Pick the Profiler User Defined Field you wish to send the payment gateway Card Token to",
                "choices" => $userdefinedfields,
            );
        }
    
        if(function_exists('gf_stripe')) {
            // Stripe only fields
            $fields[] = array(
                "label" => 'UDF: Payment Gateway Customer ID',
                "type" => "select",
                "name" => "profilerdonation_userdefined_gatewaycustomerid",
                "required" => false,
                "tooltip" => "Pick the Profiler User Defined Field you wish to send the payment gateway Customer ID to",
                "choices" => $userdefinedfields,
            );
        }


        return $fields;
        
    }

    public function process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway = false, $forceSendCard = false) {
        // Processes the feed and prepares to send it to Profiler
        // This can either do a gateway payment, or just an integration

        if($feed['meta']['profilerdonation_useasgateway'] == "true" && $fromValidatorProcessPFGateway == true) {
            $useAsGateway = true;

        } elseif($feed['meta']['profilerdonation_useasgateway'] !== "true" && $fromValidatorProcessPFGateway == true) {
            // This shouldn't happen. Let's catch it just in case.
            return false;

        } else {
            $useAsGateway = false;

        }

        global $gf_profiler_gatewaydata;
        if(isset($gf_profiler_gatewaydata) && is_array($gf_profiler_gatewaydata)) {
            $entry = $this->gformEntryPostSave($entry, $form, $gf_profiler_gatewaydata);
        }

        if($useAsGateway == false && isset($entry['payment_status']) && $entry['payment_status'] == "Failed") {
            GFCommon::log_error("GFProfilerDonate: Skipped sending to Profiler due to Failed payment_status");
            return false;
        }

        if($useAsGateway == true) {
            // Profiler processes this payment
            $postData['method'] = "gateway.payment";
            $postData['apiurl_override'] = "/ProfilerAPI/payments/";
        } else {
            // Profiler will just record integration data
            $postData['method'] = "integration.send";
            $postData['datatype'] = "OLDON";
        }

        // Calculate the total or just use one field:
        if($feed['meta']['profilerdonation_amount'] == "total") {
            $postData['amount'] = $this->getTotalAmount($entry, $form);
            $postData['donationamount'] = $this->getTotalAmount($entry, $form);
            $postData['pledgeamount'] = $this->getTotalAmount($entry, $form);
        } else {
            $postData['amount'] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_amount']);
            $postData['donationamount'] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_amount']);
            $postData['pledgeamount'] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_amount']);
        }

        $postData['clientname'] = $postData['firstname'] . ' ' . $postData['surname'];

        // Credit card fields:
        $cardDetails = $this->getCardDetails($form);
        $postData['cardtype'] = $cardDetails['type'];
        $postData['cardnumber'] = $cardDetails['number'];
        $postData['maskcard'] = $this->creditcard_mask($cardDetails['number']);
        $postData['cardexpiry'] = $cardDetails['expiry_month'] . " " . $cardDetails['expiry_year'];
        $postData['ccv'] = $cardDetails['ccv'];

        if($feed['meta']['profilerdonation_userdefined_receiptname'] !== "") {
            // Receipt Name
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_receiptname']] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_receiptname']);
        }

        if($feed['meta']['profilerdonation_userdefined_paymentgatewayidused'] !== "") {
            // Payment Gateway ID Used
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_paymentgatewayidused']] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_paymentgatewayidused']);

            if(empty($postData['userdefined' . $feed['meta']['profilerdonation_userdefined_paymentgatewayidused']])) {
                // Use default value
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_paymentgatewayidused']] = $feed['meta']['profilerdonation_paymentgatewayidused_default'];
            }
        }


        // Source codes
        if(isset($feed['meta']['profilerdonation_sourcecodemode']) && $feed['meta']['profilerdonation_sourcecodemode'] !== "normal") {
            // The source code is a value of a specified field
            $donationSourceCode = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_sourcecodemode']);

        } else {
            // Regular behaviour
            $donationSourceCode = $this->getDonationCode($feed, 'sourcecode', $form);
        }

        $postData['sourcecode'] = $donationSourceCode;

        if($feed['meta']['profilerdonation_userdefined_sourcecode'] !== "") {
            // Donation Source Code
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_sourcecode']] = $donationSourceCode;
        }
        
        if($feed['meta']['profilerdonation_userdefined_pledgesourcecode'] !== "") {
            // Pledge Source Code

            if(isset($feed['meta']['profilerdonation_pledgesourcecodemode']) && $feed['meta']['profilerdonation_pledgesourcecodemode'] !== "normal") {
                // The source code is a value of a specified field
                $pledgeSourceCode = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_pledgesourcecodemode']);

            } else {
                // Regular behaviour
                $pledgeSourceCode = $this->getDonationCode($feed, 'pledgesourcecode', $form);
            }

            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_pledgesourcecode']] = $pledgeSourceCode;
        }
        
        if($feed['meta']['profilerdonation_userdefined_pledgeacquisitioncode'] !== "") {
            // Pledge Acqusition code
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_pledgeacquisitioncode']] = $this->getDonationCode($feed, 'pledgeacquisitioncode', $form);
        }

        if($feed['meta']['profilerdonation_userdefined_pledgetypeid'] !== "") {
            // Pledge Type ID
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_pledgetypeid']] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_pledgetypeid']);

            if(empty($postData['userdefined' . $feed['meta']['profilerdonation_userdefined_pledgetypeid']])) {
                // Default value if above field is empty
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_pledgetypeid']] = $feed['meta']['profilerdonation_pledgetypeid_default'];
            }
        }

        if($feed['meta']['profilerdonation_userdefined_clientacquisitioncode'] !== "" && $feed['meta']['profilerdonation_clientacquisitioncode'] !== "") {
            // Client Acqusition code
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_clientacquisitioncode']] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_clientacquisitioncode']);

        } else if ($feed['meta']['profilerdonation_userdefined_clientacquisitioncode'] !== "") {
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_clientacquisitioncode']] = $this->getDonationCode($feed, 'clientacquisitioncode', $form);
        }
        
        if($feed['meta']['profilerdonation_userdefined_donationpurposecode'] !== "" && $feed['meta']['profilerdonation_donationpurposecode'] !== "") {
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_donationpurposecode']] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_donationpurposecode']);
        }

        if($feed['meta']['profilerdonation_userdefined_donationtagcode'] !== "" && $feed['meta']['profilerdonation_donationtagcode'] !== "") {
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_donationtagcode']] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_donationtagcode']);
        }

        if($feed['meta']['profilerdonation_userdefined_gatewaytransactionid'] !== "" && isset($entry['transaction_id'])) {
            // Gateway transaction id
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_gatewaytransactionid']] = $entry['transaction_id'];
        }

        if($feed['meta']['profilerdonation_userdefined_gatewaytransactionid'] !== "" && isset($_POST['payfurl_payment_details']['captured_payment']['payfurl_transaction_id'])) {
            // PayFURL-supplied Gateway Response
            $payfurl_gateway_response = $_POST['payfurl_payment_details']['captured_payment']['payfurl_transaction_id'];

            if(!empty($payfurl_gateway_response)) {
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_gatewaytransactionid']] = $payfurl_gateway_response;
            }
        }

        if(isset($_POST['payfurl_payment_details']['captured_payment']['masked_card_number'])) {
            // PayFURL Masked Card Number
            $postData['maskcard'] = $_POST['payfurl_payment_details']['captured_payment']['masked_card_number'];
        }

        if($feed['meta']['profilerdonation_userdefined_clientpreferredcontactmethod'] !== "") {
            // Client Preferred Contact Method
            $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_clientpreferredcontactmethod']] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_clientpreferredcontactmethod']);
        }

        if($feed['meta']['profilerdonation_userdefined_clientprivacypreference'] !== "") {
            // Client Privacy Preference

            $privacy_field_value = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_clientprivacypreference']);

            if(!empty($privacy_field_value)) {
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_clientprivacypreference']] = 'true';
            }
        }

        if($this->get_field_value($form, $entry, $feed['meta']['profilerdonation_donationtype']) == "regular") {
            // Recurring donation
            $postData['datatype'] = "PLG";
            $postData['pledgetype'] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_pledgefreq']);

            if(empty($postData['pledgetype'])) {
                // We assume a monthly pledge if no frequency is specified
                $postData['pledgetype'] = "monthly";
            }

            if($feed['meta']['profilerdonation_userdefined_sourcecode'] !== "") {
                // If it's recurring, the donation gets the pledge source code instead of the donation code
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_sourcecode']] = $this->getDonationCode($feed, 'pledgesourcecode', $form);
            }

            // Store the donation type
            gform_add_meta($entry["id"], "profiler_type", "regular", $form_id);

        } elseif($useAsGateway == false) {
            // Once-off donation (not using Profiler as the gateway)
            
            if($forceSendCard == false) {
                $postData['cardnumber'] = "4444333322221111"; //PF expects a card number and expiry even for once-offs which have already been processed
                $postData['cardexpiry'] = date("m") . " " . date("Y");
                $postData['ccv'] = "";
            }

            unset($postData['pledgeamount']);
            unset($postData['userdefined' . $feed['meta']['profilerdonation_userdefined_pledgeacquisitioncode']]);
            unset($postData['userdefined' . $feed['meta']['profilerdonation_userdefined_pledgesourcecode']]);

        } else {
            // Store the donation type
            gform_add_meta($entry["id"], "profiler_type", "onceoff", $form_id);
        }
        
        if($this->get_field_value($form, $entry, $feed['meta']['profilerdonation_paymentmethod']) == "bankdeposit") {
            // Send a pending payment through to Profile for a Bank Deposit
            $postData['status'] = "Pending";
            unset($postData['cardtype']);
            unset($postData['cardnumber']);
            unset($postData['ccv']);
            unset($postData['cardexpiry']);

        } elseif($this->get_field_value($form, $entry, $feed['meta']['profilerdonation_paymentmethod']) == "bankdebit") {
            // Bank Debit - not currently wired up to Profiler correctly
            $postData['status'] = "Pending";
            unset($postData['cardtype']);
            unset($postData['cardnumber']);
            unset($postData['ccv']);
            unset($postData['cardexpiry']);

            $postData['bankaccountname'] = $this->get_field_value($form, $entry, $feed['meta']["profilerdonation_bankdebit_accountname"]);
            $postData['bankbsb'] = $this->get_field_value($form, $entry, $feed['meta']["profilerdonation_bankdebit_bsb"]);
            $postData['bankaccountnumber'] = $this->get_field_value($form, $entry, $feed['meta']["profilerdonation_bankdebit_accountnumber"]);
            
        } elseif($this->get_field_value($form, $entry, $feed['meta']['profilerdonation_paymentmethod']) == "paypal") {
            // PayPal within Profiler requires this value in the 'maskcard' field
            $postData['maskcard'] = 'paypal';

        } elseif($useAsGateway == false) {
            // Credit Card
            // This feed only processes on success - so we assume an approved transaction
            $postData['status'] = "Approved";
        }

        return $postData;
    }

    public function process_feed_success($feed, $entry, $form, $pfResponse, $postData) {

        if(!isset($pfResponse['dataArray']['status']) || $pfResponse['dataArray']['status'] != "Pass") {
            // Profiler failed. Send the failure email.
            $this->sendFailureEmail($entry, $form, $pfResponse, $feed['meta']['profiler_erroremailaddress']);

        } else {
            // Store the Integration ID as meta so we can use it later
            if(isset($pfResponse['dataArray']['id'])) {
                gform_add_meta($entry["id"], "profiler_integrationid", $pfResponse['dataArray']['id'], $form['id']);
                gform_add_meta($entry["id"], "profiler_sourcecode", $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_sourcecode']], $form['id']);
            }
        }

    }

    public function validate_payment($gform_validation_result) {
        // This function allows Profiler to process the credit card (instead of a separate gateway plugin)

        if(!$gform_validation_result['is_valid']) {
            // If it's already failed validation...
            return $gform_validation_result;
        }

        $form = $gform_validation_result['form'];
        $entry = GFFormsModel::create_lead($form);
        $feed = $this->get_feed_instance($form, $entry);

        if($feed === false || !is_array($feed)) {
            return $gform_validation_result;
        }

        if($feed['meta']['profilerdonation_useasgateway'] !== "true" || $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_paymentmethod']) == "bankdebit" || $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_paymentmethod']) == "paypal") {
            // If we're not using Profiler as a gateway, we don't need to continue validation here.
            return $gform_validation_result;
        }

        // Is multi page form?
        if(isset($form['pagination']['type']) && $form['pagination']['type'] != 'none') {

            // Submitting the final page of the form?
            if(!isset($_POST['gform_target_page_number_' . $form['id']]) || $_POST['gform_target_page_number_' . $form['id']] != '0') {
                // Not submitting - skip payment process
                return $gform_validation_result;
            }
        }

        if($this->hasFormBeenProcessed($form)) {
            // Entry has already been created
            $gform_validation_result['is_valid'] = false;

            foreach($form['fields'] as &$field) {
                if($field->type == "creditcard") {
                    $field->failed_validation = true;
                    $field->validation_message = 'Sorry, this transaction has already been processed.';
                }
                
            }

            $gform_validation_result['form'] = $form;
            return $gform_validation_result;

        }

        // Send the data through to process_feed with a special flag that makes it try to take the money
        $result = $this->process_feed($feed, $entry, $form, true);

        if($result['dataArray']['gateway']['response'] == "True") {
            // The form passed validation. Good times.
            $gform_validation_result['is_valid'] = true;

            global $gf_profiler_gatewaydata;
            $gf_profiler_gatewaydata = array(
                "payment_status"                => "Approved",
                "payment_date"                  => date('Y-m-d H:i:s'),
                "transaction_id"                => $result['dataArray']['gateway']['txn'],
                "transaction_type"              => 1,
                "payment_method"                => '',
                "gfprofilergateway_unique_id"   => GFFormsModel::get_form_unique_id($form['id']),
            );

            if($feed['meta']['profilerdonation_amount'] == "total") {
                $gf_profiler_gatewaydata['payment_amount'] = $this->getTotalAmount($entry, $form);
            } else {
                $gf_profiler_gatewaydata['payment_amount'] = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_amount']);
            }

        } else {
            // The form (overall) failed validation
            $gform_validation_result['is_valid'] = false;

            // Add the error message to the credit card field:
            foreach($form['fields'] as &$field) {
                if($field->type == "creditcard") {
                    $field->failed_validation = true;
                    $field->validation_message = 'We could not process your credit card. Please check your details and try again.';
                }
                
            }
        }

        $gform_validation_result['form'] = $form;
        return $gform_validation_result;

    }

    public function mergeTag_totalAmount($text, $form, $entry, $url_encode, $esc_html, $nl2br, $format) {
        //Populate the custom merge field "{total_amount}"
        
        if(!is_array($text)) {
            $text = array($text);
            $returnAsStr = true;
        } else {
            $returnAsStr = false;
        }
        
        foreach($text as $key => $val) {
            if(strpos($text[$key], '{total_amount}') !== false) {
                $total = $this->getTotalAmount($entry, $form);
                $text[$key] = str_replace('{total_amount}', "$" . number_format($total, 2), $text[$key]);
            }
        }
        
        if($returnAsStr == true) {
            return $text[0];
        } else {
            return $text;
        }
        
    }
    
    public function addMergeTags($form) {
        // Adds the merge tag to the admin form drop-down
        
        echo '
        <script type="text/javascript">
            gform.addFilter("gform_merge_tags", "add_merge_tags");
            function add_merge_tags(mergeTags, elementId, hideAllFields, excludeFieldTypes, isPrepop, option){
                mergeTags["custom"].tags.push({ tag: "{total_amount}", label: "Total Amount" });
                return mergeTags;
            }
        </script>
        ';
        
        // Return the form object from the php hook
        return $form;
    }

    protected function getDonationCodes($feed) {
        // Returns an array of the various donation codes based on the heirachy
        
        return array(
            "sourcecode" => $this->getDonationCode($feed, "sourcecode"),
            "pledgesourcecode" => $this->getDonationCode($feed, "pledgesourcecode"),
            "pledgeacquisitioncode" => $this->getDonationCode($feed, "pledgeacquisitioncode"),
        );
        
    }
    
    protected function getDonationCode($feed, $code, $form = null) {
        // Returns a single donation code based on this heirachy:
        // 1. Page GET paramater
        // 2. Value passed in via $form object
        // 3. Page Short Code
        // 4. Feed Default Settings
        
        if(isset($_GET[$code])) {
            // Strip out all non-alphanumeric characters
            $outputcode = preg_replace('/[^a-z\d ]/i', '', $_GET[$code]);
        } else {
            $outputcode = "";
        }

        if(empty($outputcode)
            && isset($form['addon_profiler_values'][$code])
            && !empty($form['addon_profiler_values'][$code])) {
                $outputcode = $form['addon_profiler_values'][$code];
        }

        if(empty($outputcode)
            && isset($_SESSION['profilerdonation_codes_page'])
            && isset($_SESSION['profilerdonation_' . $code])
            && !empty($_SESSION['profilerdonation_' . $code])
            && $_SERVER['REQUEST_URI'] == $_SESSION['profilerdonation_codes_page']) {
                // This is a global/session variable used to override the sourcecode per-page - it's set via a shortcode
                $outputcode = $_SESSION['profilerdonation_' . $code];
        }
        
        if(empty($outputcode)) {
            $outputcode = $feed['meta']['profilerdonation_' . $code];
        }
        
        return strtoupper($outputcode);
    }

    private function hasFormBeenProcessed($form) {
        global $wpdb;

        $unique_id = RGFormsModel::get_form_unique_id($form['id']);

        $sql = "select entry_id from {$wpdb->prefix}gf_entry_meta where meta_key='gfprofilergateway_unique_id' and meta_value = %s";
        $lead_id = $wpdb->get_var($wpdb->prepare($sql, $unique_id));

        return !empty($lead_id);
    }

}
