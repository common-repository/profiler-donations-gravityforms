<?php

class GFProfilerEvents extends GFProfilerCommon {
    protected $_slug = "profiler-events-gf";
    protected $_title = "Profiler / Gravity Forms - Events Integration Feed";
    protected $_short_title = "Profiler Events";
    protected $formid;
    protected $form;
    protected $gateways;
    protected static $_instance = null;

    protected $apifield_endpoint = "/ProfilerPROG/api/api_events.cfm";
    protected $apifield_apikey = "api_user";
    protected $apifield_apipass = "api_pass";

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFProfilerEvents();
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

        $field_settings = $this->formFields();
        $hiddenFields = $this->hiddenFields();
        $checkboxRadioFields = $this->checkboxRadioFields();
        $userdefinedfields = $this->userDefinedFields();
        
        // All the extra fields to add to the feed:
        $fields = array();

        $fields[] = array(
            "label" => 'Client: Organisation',
            "type" => "select",
            "name" => "profilerevent_clientorganisation",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "org",
        );

        $fields[] = array(
            "label" => 'Client: First Name',
            "type" => "select",
            "name" => "profilerevent_clientfname",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "firstname",
        );

        $fields[] = array(
            "label" => 'Client: Last Name',
            "type" => "select",
            "name" => "profilerevent_clientlname",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "surname",
        );

        $fields[] = array(
            "label" => 'Client: Salutation',
            "type" => "select",
            "name" => "profilerevent_clientsalutation",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "salutation",
        );
        
        $fields[] = array(
            "label" => 'Event Heading',
            "type" => "select",
            "name" => "profilerevent_eventheading",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "heading",
        );

        $fields[] = array(
            "label" => 'Public: Email',
            "type" => "select",
            "name" => "profilerevent_publicemail",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "public_email",
        );

        $fields[] = array(
            "label" => 'Public: Website',
            "type" => "select",
            "name" => "profilerevent_publicwebsite",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "public_website",
        );

        $fields[] = array(
            "label" => 'Public: Phone',
            "type" => "select",
            "name" => "profilerevent_publicphone",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "public_phone",
        );

        $fields[] = array(
            "label" => 'Event Category',
            "type" => "select",
            "name" => "profilerevent_eventcategory",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "event_category",
        );

        $fields[] = array(
            "label" => 'Location: Name',
            "type" => "select",
            "name" => "profilerdonation_locationname",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "location_name",
        );

        $fields[] = array(
            "label" => 'Location: Address',
            "type" => "select",
            "name" => "profilerdonation_locationaddress",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "location_address",
        );

        $fields[] = array(
            "label" => 'Location: Suburb',
            "type" => "select",
            "name" => "profilerdonation_locationsuburb",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "location_suburb",
        );

        $fields[] = array(
            "label" => 'Private: Email',
            "type" => "select",
            "name" => "profilerdonation_privateemail",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "private_email",
        );

        $fields[] = array(
            "label" => 'Private: Phone',
            "type" => "select",
            "name" => "profilerdonation_privatephone",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "private_phone",
        );

        $fields[] = array(
            "label" => 'Event: Cost',
            "type" => "select",
            "name" => "profilerdonation_eventcost",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "cost",
        );

        $fields[] = array(
            "label" => 'Event: Date',
            "type" => "select",
            "name" => "profilerdonation_eventdate",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "event_date",
        );

        $fields[] = array(
            "label" => 'Event: Expiry',
            "type" => "select",
            "name" => "profilerdonation_eventexpiry",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "expiry_date",
        );

        $fields[] = array(
            "label" => 'Event: Start Time',
            "type" => "select",
            "name" => "profilerdonation_eventstarttime",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "event_start_time",
        );

        $fields[] = array(
            "label" => 'Event: End Time',
            "type" => "select",
            "name" => "profilerdonation_eventendtime",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "event_end_time",
        );

        $fields[] = array(
            "label" => 'Event: Description (Long)',
            "type" => "select",
            "name" => "profilerdonation_eventendtime",
            "required" => false,
            "choices" => $field_settings,
            "pf_apifield" => "description_long",
        );

        return $fields;
        
    }

    public function process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway = false) {
        $postData['call'] = "submit";
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