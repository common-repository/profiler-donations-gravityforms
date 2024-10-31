<?php

class GFProfilerLists extends GFProfilerCommon {
    protected $_slug = "profiler-lists-gf";
    protected $_title = "Profiler / Gravity Forms - Mailing Lists (Advanced) Integration Feed";
    protected $_short_title = "Profiler Mailing Lists (Advanced)";
    protected $formid;
    protected $form;
    protected $gateways;
    protected static $_instance = null;

    protected $apifield_endpoint = "/ProfilerPROG/api/api_call.cfm";
    protected $apifield_apikey = "apikey";
    protected $apifield_apipass = "apipass";
    protected $supports_custom_fields = true;

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFProfilerLists();
        }

        self::$_instance->form = self::$_instance->get_current_form();
        if(is_array(self::$_instance->form)) {
            self::$_instance->formid = self::$_instance->form["id"];
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();
    }
    
    public function feed_settings_fields_custom() {
        // This function adds all the feed setting fields we need to communicate with Profiler

        $form = $this->get_current_form();
        $feed = $this->get_current_feed();

        $field_settings = self::$_instance->formFields();
        $hiddenFields = self::$_instance->hiddenFields();
        $checkboxRadioFields = self::$_instance->checkboxRadioFields();
        $userdefinedfields = self::$_instance->userDefinedFields();

        $mailingnumbers = array();
        for($i = 0; $i <= 99; $i++) {
            $mailingnumbers[] = array(
                "value" => $i,
                "label" => $i
            );
        }

        // All the fields to add to the feed:
        $fields = array();

        $fields[] = array(
            "label" => 'Client: Title',
            "type" => "select",
            "name" => "profilerlist_clienttitle",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "title",
        );
        
        $fields[] = array(
            "label" => 'Client: First Name',
            "type" => "select",
            "name" => "profilerlist_clientfname",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "firstname",
        );
        
        $fields[] = array(
            "label" => 'Client: Last Name',
            "type" => "select",
            "name" => "profilerlist_clientlname",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "surname",
        );
        
        $fields[] = array(
            "label" => 'Client: Email',
            "type" => "select",
            "name" => "profilerlist_clientemail",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "email",
        );
        
        $fields[] = array(
            "label" => 'Client: Address',
            "type" => "select",
            "name" => "profilerlist_clientaddress",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "address",
        );

        $fields[] = array(
            "label" => 'Client: City',
            "type" => "select",
            "name" => "profilerlist_clientcity",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "suburb",
        );
        
        $fields[] = array(
            "label" => 'Client: State',
            "type" => "select",
            "name" => "profilerlist_clientstate",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "state",
        );
        
        $fields[] = array(
            "label" => 'Client: Zip/Postcode',
            "type" => "select",
            "name" => "profilerlist_clientpostcode",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "postcode",
        );
        
        $fields[] = array(
            "label" => 'Client: Country',
            "type" => "select",
            "name" => "profilerlist_clientcountry",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "country",
        );
        
        $fields[] = array(
            "label" => 'Client: Organisation',
            "type" => "select",
            "name" => "profilerlist_clientorganisation",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "org",
        );
        
        $fields[] = array(
            "label" => 'Client: Home Phone',
            "type" => "select",
            "name" => "profilerlist_clientphoneah",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "phoneah",
        );
        
        $fields[] = array(
            "label" => 'Client: Business Phone',
            "type" => "select",
            "name" => "profilerlist_clientphonebus",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "phonebus",
        );
        
        $fields[] = array(
            "label" => 'Client: Mobile Phone',
            "type" => "select",
            "name" => "profilerlist_clientphonemobile",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "phonemobile",
        );
        
        $fields[] = array(
            "label" => 'Client: Website',
            "type" => "select",
            "name" => "profilerlist_clientwebsite",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "website",
        );

        $fields[] = array(
            "label" => 'Number of Mailing Lists',
            "type" => "select",
            "name" => "profilerlist_mailinglist_count",
            "required" => false,
            "tooltip" => "Select a quantity of Mailing Lists, save this page, and then configure them. You may need to refresh this page after saving to see the extra fields.",
            "choices" => $mailingnumbers,
            "default" => 0,
        );

        for($i = 1; $i <= $feed['meta']['profilerlist_mailinglist_count']; $i++) {
            // Loop over mailing list fields

            $fields[] = array(
                "label" => 'Mailing List #'.$i.': UDF',
                "type" => "select",
                "name" => "profilerlist_mailinglist_".$i."_udf",
                "required" => false,
                "tooltip" => "Pick the Profiler User Defined Field you wish to use for this mailing",
                "choices" => $userdefinedfields,
            );

            $fields[] = array(
                "label" => 'Mailing List #'.$i.': UDF Text',
                "type" => "text",
                "name" => "profilerlist_mailinglist_".$i."_udftext",
                "required" => false,
                "tooltip" => "Enter the string Profiler is expecting in this UDF",
            );

            $fields[] = array(
                "label" => 'Mailing List #'.$i.': Field',
                "type" => "select",
                "name" => "profilerlist_mailinglist_".$i."_field",
                "tooltip" => 'Link it to a checkbox field - when checked, the mailing will be sent',
                "required" => false,
                "choices" => array_merge($checkboxRadioFields, array(array("value" => "always", "label" => "Always Subscribe"))),
            );
        }

        $fields[] = array(
            "label" => 'UDF: Client IP Address',
            "type" => "select",
            "name" => "profilerlist_userdefined_clientip",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the client's IP address to be sent to",
            "choices" => $userdefinedfields,
            "pf_apifield" => "",
        );

        $fields[] = array(
            "label" => 'UDF: Form URL',
            "type" => "select",
            "name" => "profilerlist_userdefined_formurl",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish the donation's form's URL to be sent to.",
            "choices" => $userdefinedfields,
            "pf_apifield" => "",
        );

        return $fields;
        
    }

    public function process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway = false) {

        $postData['method'] = "integration.send";
        $postData['datatype'] = "LISTS";
        $postData['clientname'] = $postData['firstname'] . " " . $postData['surname'];

        // Calculate mailing list subscriptions
        for($i = 1; $i <= $feed['meta']['profilerlist_mailinglist_count']; $i++) {
            // Loop over mailing list fields
            $mailingFieldValue = $this->get_field_value($form, $entry, $feed['meta']["profilerlist_mailinglist_".$i."_field"]);
            $udf = $feed['meta']["profilerlist_mailinglist_".$i."_udf"];
            $udfText = $feed['meta']["profilerlist_mailinglist_".$i."_udftext"];

            if(!empty($udf) && !empty($udfText) && (!empty($mailingFieldValue) || $feed['meta']["profilerlist_mailinglist_".$i."_field"] == "always")) {
                $postData['userdefined' . $udf] = $udfText;
            }

        }

        return $postData;
    }

    public function process_feed_success($feed, $entry, $form, $pfResponse, $postData) {

        if(!isset($pfResponse['dataArray']['status']) || $pfResponse['dataArray']['status'] != "Pass") {
            // Profiler failed. Send the failure email.
            $this->sendFailureEmail($entry, $form, $pfResponse, $feed['meta']['profiler_erroremailaddress']);

        } else {
            // Store the Integration ID as meta so we can use it later
            if(isset($pfResponse['dataArray']['id']))
                gform_add_meta($entry["id"], "profiler_integrationid", $pfResponse['dataArray']['id'], $form['id']);
        }

    }

}

?>