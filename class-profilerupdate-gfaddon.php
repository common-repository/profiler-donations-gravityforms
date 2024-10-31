<?php

class GFProfilerUpdate extends GFProfilerDonate {
    protected $_slug = "profiler-update-gf";
    protected $_title = "Profiler / Gravity Forms - Update Details Integration Feed";
    protected $_short_title = "Profiler Update Details";
    protected $formid;
    protected $form;
    protected $gateways;
    protected static $_instance = null;

    protected $apifield_endpoint = "/ProfilerAPI/Legacy/";
    protected $apifield_apikey = "apikey";
    protected $apifield_apipass = "apipass";
    protected $apifield_ipaddress = 'udf';
    protected $apifield_formurl = true;
    protected $gffield_legacyname = "update";
    protected $supports_custom_fields = true;

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFProfilerUpdate();
        }

        self::$_instance->form = self::$_instance->get_current_form();
        if(is_array(self::$_instance->form)) {
            self::$_instance->formid = self::$_instance->form["id"];
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();

        // Filter to allow Profiler to process payments internally (instead of a gateway in Gravity Forms)
        remove_filter("gform_validation", array($this, "validate_payment"), 1000);
    }

    public function feed_settings_fields_custom() {
        // This function adds all the feed setting fields we need to communicate with Profiler

        $form = $this->get_current_form();
        $feed = $this->get_current_feed();

        $field_settings = self::$_instance->formFields();
        $product_field_settings = self::$_instance->productFields();
        $hiddenFields = self::$_instance->hiddenFields();
        $checkboxRadioFields = self::$_instance->checkboxRadioFields();
        $userdefinedfields = self::$_instance->userDefinedFields();

        // All the fields to add to the feed:
        $fields = array();

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
            "label" => 'UDF: Client IP Address',
            "type" => "select",
            "name" => "profilerdonation_userdefined_clientip",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the client's IP address to be sent to",
            "choices" => $userdefinedfields,
        );



        return $fields;

    }

    public function process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway = false, $forceSendCard = false) {
        // Processes the feed and prepares to send it to Profiler

        $postData = parent::process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway, true);
        $postData['datatype'] = "UPD";

        return $postData;

    }

}
