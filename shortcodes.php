<?php

add_shortcode('donate_setoptions', 'profilerdonate_setoptions');
add_action('wp_footer', 'profilerdonate_clearoptions');

function profilerdonate_setoptions($atts, $content = null) {
    // The purpose of this shortcode is to manually set donation form options per-page.
    // This sort of stuff could be setup in the form itself or with GET params,
    // but we want to have different source-codes per-page without duplicating the forms.
    
    global $profilerdonate_sourcecode_onpage;
    global $gfDonationIdSmall;
    global $gfDonationIdRegular;
    
    if(!session_id()) {
        session_start(); 
    }
    
    $a = shortcode_atts( array(
        'sourcecode' => '',
        'pledgesourcecode' => '',
        'pledgeacquisitioncode' => '',
        'formid_small' => '',
        'formid_regular' => '',
    ), $atts );
    
    // Assign the Source Code to the global variable
    $_SESSION['profilerdonation_sourcecode'] = $a['sourcecode'];
    
    // Assign the Source Code to the global variable
    $_SESSION['profilerdonation_pledgesourcecode'] = $a['pledgesourcecode'];
    
    // Assign the Acquisition Code to the global variable
    $_SESSION['profilerdonation_pledgeacquisitioncode'] = $a['pledgeacquisitioncode'];
    
    // Store the request URI, too. This ensures we don't carry custom sourcecodes between pages
    $_SESSION['profilerdonation_codes_page'] = $_SERVER['REQUEST_URI'];
    
    // Allow the default form to be replaced on this page (small)
    if(!empty($a['formid_small'])) {
        $gfDonationIdSmall = $a['formid_small'];
    }

    // Allow the default form to be replaced on this page (everything but small)
    if(!empty($a['formid_regular'])) {
        $gfDonationIdRegular = $a['formid_regular'];
    }

    // We set this global variable here so it can be checked again on every page load
    $profilerdonate_sourcecode_onpage = true;
    
    // This shortcode doesn't output anything - just sets a global variable to be used by the Profiler GF plugin
    return '';
}

function profilerdonate_clearoptions() {
    // Checks to see if $_SESSION['profilerdonation_codes_pagee'] has been set on this page load.
    // If not, clear it and all the other related session variables.
    
    global $profilerdonate_sourcecode_onpage;
    
    if(isset($_SESSION['profilerdonation_codes_page']) && !isset($profilerdonate_sourcecode_onpage)) {
        unset($_SESSION['profilerdonation_sourcecode']);
        unset($_SESSION['profilerdonation_pledgeacquisitioncode']);
        unset($_SESSION['profilerdonation_pledgesourcecode']);
        unset($_SESSION['profilerdonation_codes_page']);
    }
}
