<?php

function profilerdonation_creditcardfilter($field_content, $field) {
    if ($field->type == 'creditcard') {
        $field_content = str_replace(".1' id", ".1' class='gf_processcardnumber' id", $field_content);
    }
    
    return $field_content;
}

function profilerdonation_cardprocessscript($form) {
    wp_enqueue_script('profilerdonation_cardprocessscript', plugin_dir_url(__FILE__).'/cardprocess.js');
}

add_filter('gform_field_content', 'profilerdonation_creditcardfilter', 10, 2);
add_action('gform_enqueue_scripts', 'profilerdonation_cardprocessscript', 10, 2);