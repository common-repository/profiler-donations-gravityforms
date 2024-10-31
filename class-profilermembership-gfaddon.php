<?php

class GFProfilerMembership extends GFProfilerDonate {
    protected $_slug = "profiler-membership-gf";
    protected $_title = "Profiler / Gravity Forms - Membership Integration Feed";
    protected $_short_title = "Profiler Membership";
    protected $formid;
    protected $form;
    protected $gateways;
    protected static $_instance = null;

    protected $apifield_endpoint = "/ProfilerAPI/Legacy/";
    protected $apifield_apikey = "apikey";
    protected $apifield_apipass = "apipass";
    protected $apifield_ipaddress = 'udf';
    protected $apifield_formurl = true;
    protected $gffield_legacyname = "membership";

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new GFProfilerMembership();
        }

        self::$_instance->form = self::$_instance->get_current_form();
        if(is_array(self::$_instance->form)) {
            self::$_instance->formid = self::$_instance->form["id"];
        }

        return self::$_instance;
    }

    public function init() {
        parent::init();

        add_filter("gform_validation", array($this, "remove_payment_validators"), 1);
    }

    public function feed_settings_fields_custom() {
        // Add extra fields for processing memberships later

        $userdefinedfields = self::$_instance->userDefinedFields();

        $fields = parent::feed_settings_fields_custom();

        foreach($fields as $fieldKey => $fieldVal) {
            if($fieldVal['label'] == "Use Profiler As A Gateway?") {
                $fields[$fieldKey]['choices'][] = array(
                    'label'         => 'Manual - Profiler will Process Payments, manually later on via a Pledge',
                    'value'         => 'manual',
                );
            }
        }

        $fields[] = array(
            "label" => 'UDF: Stored Card Number',
            "type" => "select",
            "name" => "profilerdonation_userdefined_storedcard_number",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish to store the card number in",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'UDF: Stored Card Expiry',
            "type" => "select",
            "name" => "profilerdonation_userdefined_storedcard_expiry",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish to store the card expiry in",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'UDF: Stored Card CCV',
            "type" => "select",
            "name" => "profilerdonation_userdefined_storedcard_ccv",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish to store the card CCV in",
            "choices" => $userdefinedfields,
        );

        $fields[] = array(
            "label" => 'UDF: Stored Card Name',
            "type" => "select",
            "name" => "profilerdonation_userdefined_storedcard_name",
            "required" => false,
            "tooltip" => "Pick the Profiler User Defined Field you wish to store the card name in",
            "choices" => $userdefinedfields,
        );

        return $fields;

    }

    public function process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway = false, $forceSendCard = false) {
        // Processes the feed and prepares to send it to Profiler
        // This can either do a gateway payment, or just an integration

        $postData = parent::process_feed_custom($feed, $entry, $form, $postData, $fromValidatorProcessPFGateway, true);

        $payLater = false;

        if($feed['meta']['profilerdonation_useasgateway'] == "true" && $fromValidatorProcessPFGateway == true) {
            $useAsGateway = true;

        } elseif($feed['meta']['profilerdonation_useasgateway'] !== "true" && $fromValidatorProcessPFGateway == true) {
            // This shouldn't happen. Let's catch it just in case.
            return false;

        } elseif($feed['meta']['profilerdonation_useasgateway'] == "manual" && $fromValidatorProcessPFGateway == false) {
            $useAsGateway = false;
            $payLater = true;

        } elseif($feed['meta']['profilerdonation_useasgateway'] == "manual" && $fromValidatorProcessPFGateway == true) {
            // Shouldn't happen
            return false;

        } else {
            $useAsGateway = false;

        }

        if($useAsGateway != true) {
            // Profiler processes this payment
            $postData['datatype'] = "MEM";
        }

        $postData['pledgeamount'] = $postData['amount'];

        if($payLater === true) {
            $cardDetails = $this->getCardDetails($form);

            if($feed['meta']['profilerdonation_userdefined_storedcard_number'] !== "") {
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_storedcard_number']] = $cardDetails['number'];
            }

            if($feed['meta']['profilerdonation_userdefined_storedcard_expiry'] !== "") {
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_storedcard_expiry']] = $cardDetails['expiry_month'] . " " . $cardDetails['expiry_year'];
            }

            if($feed['meta']['profilerdonation_userdefined_storedcard_ccv'] !== "") {
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_storedcard_ccv']] = $cardDetails['ccv'];
            }

            if($feed['meta']['profilerdonation_userdefined_storedcard_name'] !== "") {
                $postData['userdefined' . $feed['meta']['profilerdonation_userdefined_storedcard_name']] = $cardDetails['name'];
            }

            unset($postData['cardtype']);
            unset($postData['cardnumber']);
            unset($postData['ccv']);
            unset($postData['cardexpiry']);
        }

        return $postData;

    }

    public function remove_payment_validators($gform_validation_result) {
        // Attempts to remove other payment gateways. This is a bit gateway-specific, and will not work universally

        if(!$gform_validation_result['is_valid']) {
            // If it's already failed validation...
            return $gform_validation_result;
        }

        $form = $gform_validation_result['form'];
        $entry = GFFormsModel::create_lead($form);
        $feed = $this->get_feed_instance($form, $entry);

        if(!$feed) {
            return $gform_validation_result;
        }

        if($feed['meta']['profilerdonation_useasgateway'] !== "manual") {
            // If we're not in manual mode, we don't need to continue here
            return $gform_validation_result;
        }

        // Remove SecurePay payment filter
        if(class_exists('GFSPPlugin')) {
            remove_filter('gform_validation', array(GFSPPlugin::getInstance(), 'gformValidation'), 100);
        }

        // Remove EWay payment filter
        if(class_exists('GFEwayPlugin')) {
            remove_filter('gform_validation', array(GFEwayPlugin::getInstance(), 'gformValidation'), 100);
        }

        return $gform_validation_result;
    }

}
