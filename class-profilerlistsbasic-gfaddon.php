<?php
    
class GFProfilerListsBasic extends GFProfilerCommon {
    protected $_slug = "profiler-listsbasic-gf";
    protected $_title = "Profiler / Gravity Forms - Mailing Lists (Basic Email) Integration Feed";
    protected $_short_title = "Profiler Mailing Lists (Basic Email)";
    protected $formid;
    protected $form;
    protected $gateways;
    protected static $_instance = null;

    protected $apifield_endpoint = "/ProfilerAPI/mailings/subscribe/";
    protected $apifield_apikey = "apiuser";
    protected $apifield_apipass = "apipass";

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFProfilerListsBasic();
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

        $fields = array();

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
            "label" => 'Client: Phone',
            "type" => "select",
            "name" => "profilerlist_clientphone",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "phonenumber",
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
                "label" => 'Mailing List #'.$i.': Code',
                "type" => "text",
                "name" => "profilerlist_mailinglist_".$i."_code",
                "required" => false,
                "tooltip" => "Enter the mailing list code from Profiler",
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
            "label" => 'Client Acquisition Field',
            "type" => "select",
            "name" => "profilerlist_clientacquisitioncode",
            "required" => false,
            "tooltip" => "This field's value should match the Client Acquisition Codes setup within Profiler.",
            "choices" => $field_settings,
            "pf_apifield" => "cliacq",
        );

        $fields[] = array(
            "label" => 'Mail List Acquisition Field',
            "type" => "select",
            "name" => "profilerlist_mailingacquisitioncode",
            "required" => false,
            "tooltip" => "This field's value should match the Mailing List Acquisition Codes setup within Profiler.",
            "choices" => $field_settings,
            "pf_apifield" => "mailacq",
        );

        return $fields;

    }

    public function process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway = false) {

        $postData['method'] = "subscribe";

        $postData['MailtypeID'] = "";

        // Calculate mailing list subscriptions
        for($i = 1; $i <= $feed['meta']['profilerlist_mailinglist_count']; $i++) {
            // Loop over mailing list fields
            $mailingFieldValue = $this->get_field_value($form, $entry, $feed['meta']["profilerlist_mailinglist_".$i."_field"]);
            $code = $feed['meta']["profilerlist_mailinglist_".$i."_code"];

            if(!empty($code) && (!empty($mailingFieldValue) || $feed['meta']["profilerlist_mailinglist_".$i."_field"] == "always")) {
                $postData['MailtypeID'] .= $code . ",";
            }

        }

        if(substr($postData['MailtypeID'], -1) == ",") {
            $postData['MailtypeID'] = substr($postData['MailtypeID'], 0, -1);
        }

        return $postData;
    }

    public function process_feed_success($feed, $entry, $form, $pfResponse, $postData) {

        if(!isset($pfResponse['dataArray']['status']) || $pfResponse['dataArray']['status'] != "Pass") {
            // Profiler failed. Send the failure email.
            $this->sendFailureEmail($entry, $form, $pfResponse, $feed['meta']['profiler_erroremailaddress']);
        }

    }

}

?>