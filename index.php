<?php
/*
Plugin Name: Profiler Donations - Gravity Forms Add-On
Plugin URI: https://mediarealm.com.au/
Description: Integrates Gravity Forms with Profiler, enabling donation data and more to be sent directly to Profiler.
Version: 2.4.3

Author: Media Realm
Author URI: https://www.mediarealm.com.au/

License: GPL2

'Profiler Donations - Gravity Forms Add-On' is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
'Profiler Donations - Gravity Forms Add-On' is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with 'Profiler Donations - Gravity Forms Add-On'. If not, see https://www.gnu.org/licenses/gpl-2.0.html.

*/

add_action('gform_loaded', array('ProfilerDonation_GF_Launch', 'ProfilerDonation_load'), 5);

class ProfilerDonation_GF_Launch {
    public static function ProfilerDonation_load() {

        if (!class_exists('GFForms') || !method_exists('GFForms', 'include_payment_addon_framework')) {
            return;
        }

        // Ensure the framework is included
        GFForms::include_payment_addon_framework();

        // Include the new common class
        require_once('class-profilercommon.php');

        // Inclue the feed classes
        require_once('class-profilerdonate-gfaddon.php');
        GFAddOn::register('GFProfilerDonate');

        require_once('class-profilerinteraction-gfaddon.php');
        GFAddOn::register('GFProfilerInteraction');

        require_once('class-profilerpostdonate-gfaddon.php');
        GFAddOn::register('GFProfilerPostDonate');

        require_once('class-profilerlists-gfaddon.php');
        GFAddOn::register('GFProfilerLists');

        require_once('class-profilerlistsbasic-gfaddon.php');
        GFAddOn::register('GFProfilerListsBasic');

        require_once('class-profilermembership-gfaddon.php');
        GFAddOn::register('GFProfilerMembership');

        require_once('class-profilerupdate-gfaddon.php');
        GFAddOn::register('GFProfilerUpdate');

        //require_once('class-profilerevents-gfaddon.php');
        //GFAddOn::register('GFProfilerEvents');

        // Include some random helper functions
        require_once(plugin_dir_path(__FILE__) . '/shortcodes.php');
        require_once(plugin_dir_path(__FILE__) . '/states_australia.php');
        require_once(plugin_dir_path(__FILE__) . '/cardprocess.php');

        // Feature to allow importing user accounts from Profiler
        require_once(plugin_dir_path(__FILE__) . '/sync_users.php');

        // Feature to allow importing Organisations from Profiler
        require_once(plugin_dir_path(__FILE__) . '/sync_orgtype.php');
        
        if(isset($_POST['gform_submit'])) {
            // If we're receiving a Gravity Form submission, ensure the session is started
            // We use sessions to communicate with shortcodes
            
            if(!session_id()) {
                session_start();
            }
        }

        // Warning if the SimpleXML Functions aren't available on this system
        add_action('admin_notices', function() {
            if(!function_exists('simplexml_load_string')) {
                echo '<div class="notice notice-warning">
                    <p>SimpleXML Module is not found in PHP. Your Gravity Forms + Profiler integration will not work. Please install the PHP XML package.</p>
                </div>';
            }
        });
    }
}

// Load WooCommerce extension
require_once(plugin_dir_path(__FILE__) . '/woocommerce/load.php');
