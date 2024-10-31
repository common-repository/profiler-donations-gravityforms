<?php

function profilerdonate_states_australia($addressTypes, $form_id) {
    $addressTypes['australia'] = array(
        'label'       =>   'Australia',
        'zip_label'   =>   'Post Code',
        'state_label' =>   'State',
        'states' => array(
            '',
            'ACT' => 'Australian Capital Territory',
            'NSW' => 'New South Wales',
            'NT' => 'Northern Territory',
            'QLD' => 'Queensland',
            'SA' => 'South Australia',
            'TAS' => 'Tasmania',
            'VIC' => 'Victoria',
            'WA' => 'Western Australia',
            'Other' => 'Outside Australia'
            )
    );
    return $addressTypes;
}

add_filter('gform_address_types', 'profilerdonate_states_australia', 10, 2);