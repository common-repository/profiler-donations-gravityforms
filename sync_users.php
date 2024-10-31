<?php

// This feature allows users to automatically be created in Wordpress for active subscribers

class ProfilerUsers {
    
    public $settings_prefix = "profiler_users_";
    public $errors_prefix = "Profiler Users ERROR: ";
    public $settings = array(
        "pf_domain" => array(
            "title" => "Profiler Domain",
            "type" => "text",
        ),
        "auth_apikey" => array(
            "title" => "API Key",
            "type" => "text",
        ),
        "auth_apipass" => array(
            "title" => "API Password",
            "type" => "password",
        ),
        "pf_database" => array(
            "title" => "Profiler Database (e.g. pf_demo)",
            "type" => "text",
        ),
    );

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('profiler_users_cron', array($this, 'job'));
        add_filter('cron_schedules', array($this, 'cron_schedules'));
    }

    public function activate() {
        wp_schedule_event(time(), 'fifteen_minutes', 'profiler_users_cron');
    }

    private function log($log)  {
        if(is_array($log) || is_object($log)) {
           error_log($this->errors_prefix . print_r($log, true));
        } else {
           error_log($this->errors_prefix . $log);
        }

        // Display message on Admin Console
        if(is_admin() && isset($_GET['page']) && $_GET['page'] == 'profiler_users') {
            echo $log . "<br />";
        }
     }

    public function admin_menu() {
        add_submenu_page('users.php', "Profiler Users", "Profiler Users", 'manage_options', 'profiler_users', array($this, 'options_page'));
    }

    public function settings_init() { 

        register_setting($this->settings_prefix, $this->settings_prefix . 'settings');

        add_settings_section(
            $this->settings_prefix . 'section',
            __('Profiler Users Settings', $this->settings_prefix),
            false,
            $this->settings_prefix
        );

        foreach($this->settings as $settingId => $setting) {
            add_settings_field( 
                $this->settings_prefix . $settingId, 
                __($setting['title'], $this->settings_prefix),
                array($this, 'setting_render'),
                $this->settings_prefix,
                $this->settings_prefix . 'section',
                array(
                    "field_key" => $settingId
                )
            );
        }

        if(!wp_get_schedule('profiler_users_cron')) {
            // The scheduled task has disappeared - add it again
            $this->activate();
        }

    }

    public function setting_render($args = array()) {
        if(!isset($this->settings[$args['field_key']])) {
            echo "Field not found:" . $args['field_key'];
        }

        $field = $this->settings[$args['field_key']];
        $options = get_option($this->settings_prefix . 'settings');

        if(isset($options[$args['field_key']])) {
            $value = $options[$args['field_key']];
        } else {
            $value = "";
        }

        if($field['type'] == "text") {
            // Text fields
            echo '<input type="text" name="profiler_users_settings['.$args['field_key'].']" value="'.htmlspecialchars($value, ENT_QUOTES).'" />';
        } elseif($field['type'] == "password") {
            // Password fields
            echo '<input type="password" name="profiler_users_settings['.$args['field_key'].']" value="'.htmlspecialchars($value, ENT_QUOTES).'" />';
        } elseif($field['type'] == "select") {
            // Select / drop-down fields
            echo '<select name="profiler_users_settings['.$args['field_key'].']">';
            foreach($field['options'] as $selectValue => $name) {
                echo '<option value="'.$selectValue.'" '.($value == $selectValue ? "selected" : "").'>'.$name.'</option>';
            }
            echo '</select>';
        } elseif($field['type'] == "checkbox") {
            // Checkbox fields
            echo '<input type="checkbox" name="profiler_users_settings['.$args['field_key'].']" value="true" '.("true" == $value ? "checked" : "").' />';
        }
    }

    public function options_page() {
        echo '<form action="options.php" method="POST">';
        echo '<h1>Profiler Users <span style="font-size: 0.6em; font-weight: normal;">by <a href="https://mediarealm.com.au/" target="_blank">Media Realm</a></span></h1>';

        settings_fields($this->settings_prefix);
        do_settings_sections($this->settings_prefix);
        submit_button();

        echo '</form>';

        // Display a history of successful syndication runs
        $runs = get_option($this->settings_prefix . 'history', array());
        $last_attempt = get_option($this->settings_prefix . 'last_attempt', 0);
        krsort($runs);

        echo '<h2>User Sync History</h2>';
        echo '<p>Last Attempted Run: '.($last_attempt > 0 ? date("Y-m-d H:i:s", $last_attempt) : "NEVER").'</p>';
        echo '<p>Successful Runs:</p>';
        echo '<ul>';
        $runCount = 0;
        foreach($runs as $time => $count) {
            echo '<li>'.date("Y-m-d H:i:s", $time).': '.$count.' '.($count == 1 ? "user" : "userss").' imported from master site</li>';
            $runCount++;

            if($runCount > 10)
                break;
        }
        if(count($runs) === 0) {
            echo '<li>No users have ever been imported by this plugin</li>';
        }
        echo '</ul>';


        echo '<h2>Import Users Now</h2>';
        echo "<p>This plugin uses WP-Cron to automatically import users from Profiler every 15 minutes. If you're impatient, you can do it now using the button below.</p>";
        if(isset($_GET['importnow']) && $_GET['importnow'] == "true") {
            echo "<p><strong>Attempting user import now...</strong></p>";
            $this->job();
            echo '<p><strong>Import complete!</strong></p>';
        } else {
            echo '<p class="submit"><a href="?page='.$_GET['page'].'&importnow=true" class="button button-primary">Import Users Now</a></p>';
        }
    }

    public function job() {
        // Bulk job (to call from cron or admin interface), which adds users from Wordpress

        $api_data = $this->api_list_subscriptions();

        if(!is_array($api_data)) {
            return;
        }

        if($api_data['httpstatus'] != 200) {
            $this->log("HTTP Status from Profiler API: " . $api_data['httpstatus']);
            $this->log("Error Data: " . $api_data['cURLError']);
            return;
        }

        if(!is_array($api_data['dataArray']['client'])) {
            $this->log("Profiler API returned unexpected data");
            return;
        }

        $count = 0;

        foreach($api_data['dataArray']['client'] as $key => $client_data) {
            // Loop over all clients, find missing accounts, add them

            if(email_exists($client_data['client_email'])) {
                continue;
            }

            $user_add = wp_insert_user(array(
                'user_pass' => '',
                'user_login' => 'client_' . $client_data['clientid'],
                'user_email' => $client_data['client_email'],
                'role' => 'subscriber',
                'first_name' => $client_data['client_firstname'],
                'last_name' => $client_data['client_surname'],
                'display_name' => $client_data['client_firstname'] . ' ' . $client_data['client_surname'],
            ));

            if(is_numeric($user_add)) {
                // Success log
                $this->log('Created User from Client ID #' . $client_data['clientid']);

                // Add Client ID as meta field
                update_user_meta($user_add, 'profiler_clientid', $client_data['clientid']);

                $count++;

            } else {
                // Failure log
                $this->log('ERROR Failed to create User from Client ID #' . $client_data['clientid']);
            }
        }

        if($count > 0) {
            $runs = get_option($this->settings_prefix . 'history', array());
            $runs[time()] = $count;
            update_option($this->settings_prefix . 'history', $runs);
        }

        update_option($this->settings_prefix . 'last_attempt', time());

    }

    private function api_list_subscriptions() {
        // Returns a list of active subscribers from Profiler

        $options = get_option($this->settings_prefix . 'settings');

        if(empty($options['auth_apikey']) || empty($options['auth_apipass']) || empty($options['pf_database']) || empty($options['pf_domain'])) {
            $this->log("Profiler Users not configured correctly");
            return;
        }

        $fields = array(
            'apikey' => $options['auth_apikey'],
            'apipass' => $options['auth_apipass'],
            'DB' => $options['pf_database'],
            'method' => 'active.subscriptions',
        );

        return $this->sendDataToProfiler('https://'.$options['pf_domain'].'/ProfilerAPI/subscriptions/', $fields);
    }

    protected function sendDataToProfiler($url, $profiler_query, $ssl_mode = "normal") {
        // Sends the donation and client data to Profiler via POST

        // Remove whitespace
        foreach($profiler_query as $key => $val) {
            $profiler_query[$key] = trim($val);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query(array("DB" => $profiler_query['DB'], "Call" => 'submit')));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen(http_build_query($profiler_query))));
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($profiler_query));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if($ssl_mode == "bundled_ca") {
            // Use the CA Cert bundled with this plugin
            // Sourced from https://curl.haxx.se/ca/cacert.pem
            curl_setopt($ch, CURLOPT_CAINFO, plugin_dir_path(__FILE__) . "cacert.pem");

        } elseif($ssl_mode == "dontverifypeer") {
            // Don't verify the SSL peer. This is bad. No one should do this in production.
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        }

        $result = curl_exec($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if(curl_error($ch)) {
            $cURL_error = curl_error($ch);
        } else {
            $cURL_error = null;
        }
        
        curl_close($ch);
        
        return array(
            "httpstatus" => $status_code,
            "dataSent" => $profiler_query,
            "data" => $result,
            "dataXML" => simplexml_load_string($result),
            "dataArray" => json_decode(json_encode((array)simplexml_load_string($result)), 1),
            "cURLError" => $cURL_error,
            "cURL_SSL_Mode" => $ssl_mode,
        );
    }

    public function cron_schedules($schedules) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 900,
            'display'  => esc_html__('Every Fifteen Minutes'),
        );
     
        return $schedules;
    }

}

$ProfilerUsersObj = New ProfilerUsers();
register_activation_hook(__FILE__, array($ProfilerUsersObj, 'activate'));
