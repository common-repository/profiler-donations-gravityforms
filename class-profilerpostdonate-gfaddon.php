<?php

class GFProfilerPostDonate extends GFProfilerCommon {
    protected $_slug = "profiler-postdonation-gf";
    protected $_title = "Profiler / Gravity Forms - Post-Donation Integration Feed";
    protected $_short_title = "Profiler Post-Donation";
    protected $formid;
    protected $form;
    protected $gateways;
    protected static $_instance = null;

    protected $apifield_endpoint = "/ProfilerAPI/LegacyPost/";
    protected $apifield_apikey = "api_user";
    protected $apifield_apipass = "api_pass";
    protected $apifield_ipaddress = 'udf';
    protected $apifield_formurl = true;
    protected $gffield_legacyname = "donation";
    protected $supports_custom_fields = true;
    protected $supports_mailinglists = true;

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFProfilerPostDonate();
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

        // All the fields to add to the feed:
        $fields = array();

        $fields[] = array(
            "label" => 'Comments',
            "type" => "select",
            "name" => "profilerdonation_comments",
            "required" => false,
            "choices" => $field_settings,
        );

        $fields[] = array(
            "label" => 'UDF: Comments',
            "type" => "select",
            "name" => "profilerdonation_userdefined_comments",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish to use for the Comments field",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'Extra Comments Text',
            "type" => "textarea",
            "name" => "profilerdonation_commentsextra",
            "required" => true,
            "class" => "merge-tag-support",
            "tooltip" => "This is extra text to be sent to Profiler as an Interaction. Protip: Include Gravity Forms Merge Fields in this textarea to accept user input.",
        );

        $fields[] = array(
            "label" => 'Existing Profiler Integeration ID',
            "type" => "select",
            "name" => "profilerdonation_profilerid",
            "tooltip" => 'Link it to a Hidden field that will hold the existing Profiler Integeration ID',
            "required" => false,
            "choices" => $hiddenFields
        );

        $fields[] = array(
            "label" => 'Existing GF Entry ID',
            "type" => "select",
            "name" => "profilerdonation_gfentryid",
            "tooltip" => 'Link it to a Hidden field that will hold the existing Gravity Forms Entry ID',
            "required" => false,
            "choices" => $hiddenFields
        );

        $fields[] = array(
            "label" => 'User-Submitted Token',
            "type" => "select",
            "name" => "profilerdonation_token",
            "tooltip" => 'Link it to a Hidden field that will hold the existing generated token',
            "required" => false,
            "choices" => $hiddenFields
        );

        return $fields;
        
    }

    public function process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway = false) {

        $postData['method'] = "integration.send";
        $postData['datatype'] = "OLDON";

        $comments = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_comments']);

        $comments .= GFCommon::replace_variables($feed['meta']['profilerdonation_commentsextra'], $form, $entry, false, true, false, 'text');
        $comments = html_entity_decode($comments);

        // Only allow ASCII printable characters.
        // This is a work-around to the API endpoint not allowing some characters
        $comments = preg_replace('/[^\x20-\x7E]/','', $comments);

        // Comments
        $postData['comments'] = $comments;
        $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_comments']] = $comments;
        
        $gfEntryId = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_gfentryid']);
        $pfIntegrationId = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_profilerid']);
        $token = $this->get_field_value($form, $entry, $feed['meta']['profilerdonation_token']);

        $token_required = md5(crypt($pfIntegrationId . "/" . $gfEntryId, '$6$rounds=5000$' . md5(NONCE_SALT) . '$'));

        if($token !== $token_required) {
            $entry[$feed['meta']['profilerdonation_logs']] = "Invalid security token was provided!";
            GFAPI::update_entry($entry);
            return false;
        }

        $originalEntryTime = GFAPI::get_entry($gfEntryId)['date_created'];

        if(strtotime($originalEntryTime) <= time() - 3600) {
            // Don't allow entries older than 1hr
            $entry[$feed['meta']['profilerdonation_logs']] = "Original Gravity Forms entry is too old - it was created " . $originalEntryTime;
            GFAPI::update_entry($entry);
            return false;
        }

        // This is the ID of the actual donation entry
        $postData['HoldingID'] = $pfIntegrationId;

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
