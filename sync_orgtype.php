<?php

// This feature allows orgtype to automatically be created in Wordpress for active subscribers

class ProfilerOrgType {
    
    public $settings_prefix = "profiler_orgtype_";
    public $errors_prefix = "Profiler Orgtype ERROR: ";
    public $settings = array(
        "pf_domain" => array(
            "title" => "Profiler Domain",
            "type" => "text",
        ),
        "pf_database" => array(
            "title" => "Profiler Database (e.g. pf_demo)",
            "type" => "text",
        ),
        "orgtype_list" => array(
            "title" => "List of OrgType IDs (one per line)",
            "type" => "textarea",
        ),
        "cpt_business" => array(
            "title" => "Enable in-built Business Custom Post Type",
            "type" => "checkbox",
        ),
    );

    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('profiler_orgtype_cron', array($this, 'job'));
        add_filter('init', array($this, 'init'));
        add_shortcode('profiler_directory', array($this, 'sc_directory'));
    }

    public function activate() {
        wp_schedule_event(time(), 'hourly', 'profiler_orgtype_cron');
    }

    public function init() {
        $options = get_option($this->settings_prefix . 'settings');

        // In-built 'Business' CPT
        if(isset($options['cpt_business']) && $options['cpt_business'] == true) {
            $labels = array(
                'name'                  => 'Profiler Businesses',
                'singular_name'         => 'Profiler Business',
            );
            $args = array(
                'label'                 => 'Profiler Business',
                'labels'                => $labels,
                'supports'              => array( 'title', 'editor', 'thumbnail' ),
                'hierarchical'          => false,
                'public'                => true,
                'show_ui'               => true,
                'show_in_menu'          => false,
                'menu_position'         => 5,
                'show_in_admin_bar'     => false,
                'show_in_nav_menus'     => true,
                'can_export'            => true,
                'has_archive'           => true,
                'exclude_from_search'   => false,
                'publicly_queryable'    => true,
                'capability_type'       => 'page',
                'show_in_rest'          => true,
            );
            register_post_type( 'profiler_business', $args );
        }

        // Get a list of all post types
        $post_types_objs = get_post_types(array(), 'objects');
        $post_types = array();
        foreach($post_types_objs as $key => $obj) {
            $post_types[$obj->name] = $obj->label;
        }

        // Create fields for every OrgType
        if(isset($options['orgtype_list']) && !empty($options['orgtype_list'])) {
            foreach(explode("\n", $options['orgtype_list']) as $OrgType) {

                if(empty($OrgType)) {
                    continue;
                }

                // Label for the Admin GUI
                $this->settings['orgtype_' . $OrgType . '_heading'] = array(
                    'title' => "Organisation Type: " . $OrgType,
                    "type" => "heading",
                );

                // Post type mapping
                $this->settings['orgtype_' . $OrgType . '_cpt'] = array(
                    'title' => "Post Type",
                    "type" => "select",
                    "options" => $post_types,
                );

                // API Type
                $this->settings['orgtype_' . $OrgType . '_apitype'] = array(
                    'title' => "API Type",
                    "type" => "select",
                    "options" => array(
                        'orgtype' => 'OrgType API',
                        'sales' => 'Sales Directory API',
                    ),
                );

                // Enable field mapping
                $this->settings['orgtype_' . $OrgType . '_field_enable'] = array(
                    'title' => "API Field Name: Enable",
                    "type" => "text",
                );
                $this->settings['orgtype_' . $OrgType . '_field_enable_value'] = array(
                    'title' => "Value of API Field for Enabled Organisations",
                    "type" => "text",
                );

                // Description field mapping
                $this->settings['orgtype_' . $OrgType . '_field_bodytext'] = array(
                    'title' => "API Field Name: Post Body Text",
                    "type" => "text",
                );

                // Meta field mappings
                $this->settings['orgtype_' . $OrgType . '_metamapping'] = array(
                    'title' => "Meta Field Mapping (One per line; profiler_apifieldname:wordpress_metafieldname)",
                    "type" => "textarea",
                );

            }
        }
    }

    private function log($log)  {
        if(is_array($log) || is_object($log)) {
           error_log($this->errors_prefix . print_r($log, true));
        } else {
           error_log($this->errors_prefix . $log);
        }

        // Display message on Admin Console
        if(is_admin() && isset($_GET['page']) && $_GET['page'] == 'profiler_orgtype') {
            echo $log . "<br />";
        }
     }

    public function admin_menu() {
        add_submenu_page('options-general.php', "Profiler Organisation Sync", "Profiler Organisation Sync", 'manage_options', 'profiler_orgtype', array($this, 'options_page'));
    }

    public function settings_init() { 

        register_setting($this->settings_prefix, $this->settings_prefix . 'settings');

        add_settings_section(
            $this->settings_prefix . 'section',
            __('Profiler OrgTypes Settings', $this->settings_prefix),
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

        if(!wp_get_schedule('profiler_orgtype_cron')) {
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
            echo '<input type="text" name="profiler_orgtype_settings['.$args['field_key'].']" value="'.htmlspecialchars($value, ENT_QUOTES).'" />';
        } elseif($field['type'] == "text") {
            // Text fields
            echo '<input type="text" name="profiler_orgtype_settings['.$args['field_key'].']" value="'.htmlspecialchars($value, ENT_QUOTES).'" />';
        } elseif($field['type'] == "textarea") {
            // Textarea fields
            echo '<textarea name="profiler_orgtype_settings['.$args['field_key'].']">'.htmlspecialchars($value, ENT_QUOTES)."</textarea>";
        } elseif($field['type'] == "select") {
            // Select / drop-down fields
            echo '<select name="profiler_orgtype_settings['.$args['field_key'].']">';
            foreach($field['options'] as $selectValue => $name) {
                echo '<option value="'.$selectValue.'" '.($value == $selectValue ? "selected" : "").'>'.$name.'</option>';
            }
            echo '</select>';
        } elseif($field['type'] == "checkbox") {
            // Checkbox fields
            echo '<input type="checkbox" name="profiler_orgtype_settings['.$args['field_key'].']" value="true" '.("true" == $value ? "checked" : "").' />';
        }
    }

    public function options_page() {
        echo '<form action="options.php" method="POST">';
        echo '<h1>Profiler Organisation Sync <span style="font-size: 0.6em; font-weight: normal;">by <a href="https://mediarealm.com.au/" target="_blank">Media Realm</a></span></h1>';

        settings_fields($this->settings_prefix);
        do_settings_sections($this->settings_prefix);
        submit_button();

        echo '</form>';

        // Display a history of successful syndication runs
        $runs = get_option($this->settings_prefix . 'history', array());
        $last_attempt = get_option($this->settings_prefix . 'last_attempt', 0);
        krsort($runs);

        echo '<h2>Organisation Import History</h2>';
        echo '<p>Last Attempted Run: '.($last_attempt > 0 ? date("Y-m-d H:i:s", $last_attempt) : "NEVER").'</p>';
        echo '<p>Successful Runs:</p>';
        echo '<ul>';
        $runCount = 0;
        foreach($runs as $time => $count) {
            echo '<li>'.date("Y-m-d H:i:s", $time).': '.$count.' '.($count == 1 ? "organisation" : "organisations").' imported from Profiler database</li>';
            $runCount++;

            if($runCount > 10)
                break;
        }
        if(count($runs) === 0) {
            echo '<li>No organisations have ever been imported by this plugin</li>';
        }
        echo '</ul>';


        echo '<h2>Import Organisations Now</h2>';
        echo "<p>This plugin uses WP-Cron to automatically import organisations from Profiler every 15 minutes. If you're impatient, you can do it now using the button below.</p>";
        if(isset($_GET['importnow']) && $_GET['importnow'] == "true") {
            echo "<p><strong>Attempting organisation import now...</strong></p>";
            $this->job();
            echo '<p><strong>Import complete!</strong></p>';
        } else {
            echo '<p class="submit"><a href="?page='.$_GET['page'].'&importnow=true" class="button button-primary">Import Organisations Now</a></p>';
        }
    }

    public function job() {
        // Bulk job (to call from cron or admin interface), which adds orgtype from Wordpress

        $options = get_option($this->settings_prefix . 'settings');

        $count = 0;

        if(!isset($options['orgtype_list']) || empty($options['orgtype_list'])) {
            return;
        }

        foreach(explode("\n", $options['orgtype_list']) as $OrgType) {

            if(empty($OrgType)) {
                continue;
            }

            $api_data = $this->api_list_organisations($OrgType);

            if($api_data['httpstatus'] != 200) {
                $this->log("HTTP Status from Profiler API: " . $api_data['httpstatus']);
                $this->log("Error Data: " . $api_data['cURLError']);
                return;
            }

            if(!is_array($api_data['dataArray']['row'])) {
                $this->log("Profiler API returned unexpected data");
                return;
            }

            // Store a list of Client IDs found in this API query
            $found_client_ids = array();

            // Loop over every record from the API
            foreach($api_data['dataArray']['row'] as $org) {
                
                // Find the Client ID:
                if(isset($org['id'])) {
                    $client_id = $org['id'];
                } elseif(isset($org['clientid'])) {
                    $client_id = $org['clientid'];
                } else {
                    // No client ID. Ignore this record
                    continue;
                }

                $found_client_ids[] = $client_id;

                // Check if client is 'enabled' (OrgType API Only)
                if($options['orgtype_'.$OrgType.'_apitype'] == "orgtype") {
                    if(isset($options['orgtype_'.$OrgType.'_field_enable'])
                    && !empty($options['orgtype_'.$OrgType.'_field_enable'])
                    && isset($options['orgtype_'.$OrgType.'_field_enable_value'])
                    && !empty($options['orgtype_'.$OrgType.'_field_enable_value'])) {

                        if(isset($org[$options['orgtype_'.$OrgType.'_field_enable']])
                        && $org[$options['orgtype_'.$OrgType.'_field_enable']] != $options['orgtype_'.$OrgType.'_field_enable_value']) {
                            // This Organisation is not enabled. Skip it.
                            continue;
                        }

                    }
                }

                // Find title
                if(isset($org['clientname']) && !empty($org['clientname'])) {
                    $client_name = $org['clientname'];
                } elseif(isset($org['name']) && !empty($org['name'])) {
                    $client_name = $org['name'];
                } else {
                    // No client name. Ignore this record
                    continue;
                }

                // Find body text
                if(isset($options['orgtype_'.$OrgType.'_field_bodytext']) && isset($org[$options['orgtype_'.$OrgType.'_field_bodytext']]) && !is_array($org[$options['orgtype_'.$OrgType.'_field_bodytext']])) {
                    $body_text = $org[$options['orgtype_'.$OrgType.'_field_bodytext']];
                } else {
                    $body_text = '';
                }

                // Find existing Post in WP
                $existing_posts_query = new WP_Query(array(
                    'post_type' => $options['orgtype_'.$OrgType.'_cpt'],
                    'meta_key' => 'profiler_client_id',
                    'meta_value' => $client_id,
                    'posts_per_page' => 1,
                    'orderby' => 'ID',
                    'order' => 'ASC',
                    'post_status' => array('publish', 'draft', 'pending'),
                ));
                $existing_posts = $existing_posts_query->get_posts();

                if(is_array($existing_posts) && count($existing_posts) > 0) {
                    // Update existing Post
                    $post_id = $existing_posts[0]->ID;

                } else {
                    // Create new Post
                    $post_id = 0;

                }

                // Do the create/update
                $post_id = wp_insert_post(array(
                    'ID' => $post_id,
                    'post_content' => $body_text,
                    'post_title' => $client_name,
                    'post_status' => 'publish',
                    'post_type' => $options['orgtype_'.$OrgType.'_cpt'],
                ), false);

                if(!is_numeric($post_id) || $post_id == 0) {
                    // Error adding/updating post - skip this record
                    $this->log('Error adding/updating Client ID #' . $client_id);
                    continue;
                }

                // Store the Client ID with the Post
                update_post_meta($post_id, 'profiler_client_id', $client_id);

                // Add/update meta fields on the Post
                if(isset($options['orgtype_'.$OrgType.'_metamapping'])) {
                    foreach(explode("\n", $options['orgtype_'.$OrgType.'_metamapping']) as $line) {

                        $parts = explode(":", $line);
                        $apifield = trim($parts[0]);
                        $metakey = trim($parts[1]);

                        if(isset($org[$apifield])) {
                            if(is_array($org[$apifield])) {
                                $org[$apifield] = '';
                            }
                            update_post_meta($post_id, $metakey, $org[$apifield]);
                        }
                    }
                }

                // Download and attach the logo if one is set
                if(isset($org['logo']) && !empty($org['logo'])) {
                    $logo_url = $org['logo'];
                } elseif(isset($org['clientlogo']) && !empty($org['clientlogo'])) {
                    $logo_url = 'https://'.$options['pf_domain'].'/Profiler/Content/'.$options['pf_database'].'/Attachments/' . $org['clientlogo'];
                } else {
                    $logo_url = '';
                }

                if(!empty($logo_url)) {
                    $existing_thumbnail_id = get_post_thumbnail_id($post_id);
                    $attachment_id = $this->image_ingest($logo_url, $post_id, $existing_thumbnail_id);
                    set_post_thumbnail($post_id, $attachment_id);
                }

                $count++;
            }

            // Set Posts to 'Draft' if they wern't found in the API
            $purge_posts_query = new WP_Query(array(
                'post_type' => $options['orgtype_'.$OrgType.'_cpt'],
                'posts_per_page' => -1,
                'orderby' => 'ID',
                'order' => 'ASC',
                'post_status' => 'publish',
                'meta_query' => array(array(
                    'key' => 'profiler_client_id',
                    'value' => $found_client_ids,
                    'compare' => 'NOT IN',
                ))
            ));
            $purge_posts = $purge_posts_query->get_posts();
            $count_purged = 0;
            foreach($purge_posts as $this_post) {
                $draft_post = wp_update_post(array(
                    'ID' => $this_post->ID,
                    'post_status' => 'draft',
                ), false);

                if(!is_numeric($draft_post) || $draft_post == 0) {
                    $this->log('Could not set draft - Post ID #' . $this_post->ID);
                } else {
                    $count_purged++;
                }
                
            }

            if($count_purged > 0) {
                $this->log('Set ' . $count_purged . ' posts to draft.');
            }
        }

        if($count > 0) {
            $this->log('Created and updated '.$count.' posts.');
            $runs = get_option($this->settings_prefix . 'history', array());
            $runs[time()] = $count;
            update_option($this->settings_prefix . 'history', $runs);
        }

        update_option($this->settings_prefix . 'last_attempt', time());
    }

    private function api_list_organisations($orgtype) {
        // Returns a list of organisation from Profiler

        $options = get_option($this->settings_prefix . 'settings');

        if(empty($options['pf_domain'])) {
            $this->log("Profiler Organisations not configured correctly");
            return;
        }

        if($options['orgtype_'.$orgtype.'_apitype'] == "sales") {
            // Sales Directory module
            return $this->sendDataToProfiler('https://'.$options['pf_domain'].'/ProfilerAPI/sales/clients/', array());

        } else {
            // OrgType API (works for any Org Type)
            $fields = array(
                'OrgTypeID' => $orgtype,
            );

            return $this->sendDataToProfiler('https://'.$options['pf_domain'].'/ProfilerAPI/orgtype/', $fields);
        }
    }

    protected function sendDataToProfiler($url, $profiler_query, $ssl_mode = "normal") {
        // Sends the donation and client data to Profiler via POST

        // Remove whitespace
        foreach($profiler_query as $key => $val) {
            $profiler_query[$key] = trim($val);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query(array("OrgTypeID" => $profiler_query['OrgTypeID'])));
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

    protected function image_ingest($url, $parent_post_id = null, $replace_existing_id = null) {
        // Download a file from a URL, and add it to the media library

        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Get the source file
        $image_data = file_get_contents($url);
        $filename = basename($url);

        if(strpos($filename, '?') !== false) {
            $filename = substr($filename, 0, strpos($filename, '?'));
        }

        if($replace_existing_id !== null && !empty($replace_existing_id) && $replace_existing_id !== false) {
            // Possibly replace an existing image file, if the filename matches exactly
            $existing_url = wp_get_attachment_url($replace_existing_id);
            $filename_existing = basename($existing_url);
            if(strpos($filename_existing, '?') !== false) {
                $filename_existing = substr($filename_existing, 0, strpos($filename_existing, '?'));
            }
            if($filename === $filename_existing) {
                // Trigger a replacement, rather than a new upload
                $filenamefull_existing = get_attached_file($replace_existing_id, true);
                file_put_contents($filenamefull_existing, $image_data);

                wp_generate_attachment_metadata($replace_existing_id, $filenamefull_existing);
                return $replace_existing_id;
            }
        }

        $upload_dir = wp_upload_dir();

        if(wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }

        file_put_contents($file, $image_data);

        $wp_filetype = wp_check_filetype($filename, null);

        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $file, $parent_post_id);

        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $attach_id;
    }

    public function sc_directory($atts) {
        // Simple HTML output of the directory

        global $post;
        $page_url = get_permalink($post);
        $page_url_query_string = parse_url($page_url, PHP_URL_QUERY);

        $a = shortcode_atts( array(
            'orgtype' => '',
        ), $atts);

        $options = get_option($this->settings_prefix . 'settings');

        $html = '';

        if(!isset($options['orgtype_' . $a['orgtype'] . '_cpt'])) {
            $html .= '<p><strong>OrgType not found!</strong></p>';
            return $html;
        }

        $cpt = $options['orgtype_' . $a['orgtype'] . '_cpt'];

        $posts_query = new WP_Query(array(
            'post_type' => $cpt,
            'posts_per_page' => -1,
            'orderby' => 'post_title',
            'order' => 'ASC',
            'post_status' => array('publish'),
        ));
        $posts = $posts_query->get_posts();

        if(!is_array($posts) || count($posts) == 0) {
            $html .= '<p><strong>Sorry, we could not find any entries in the directory.</strong></p>';
            return $html;
        }

        $html .= '<div class="profiler-directory">';

        foreach($posts as $this_post) {

            if(isset($_GET['orgid']) && $_GET['orgid'] != $this_post->ID) {
                continue;
            }

            $html .= '<div class="profiler-directory-entry directory-'.$a['orgtype'].' directory-entry-'.$this_post->ID.' '.(isset($_GET['orgid']) ? 'directory-individual' : '').'">';

            // Work out the unique URL
            if ($page_url_query_string) {
                $individual_url = $page_url_query_string . '&orgid=' . $this_post->ID;
            } else {
                $individual_url = $page_url_query_string . '?orgid=' . $this_post->ID;
            }

            // Meta fields
            $meta_fields = array();
            foreach(array('address', 'suburb', 'state', 'postcode', 'website', 'phone') as $meta_field_name) {
                $meta_value = get_metadata('post', $this_post->ID, $meta_field_name, true);
                if(empty($meta_value)) {
                    continue;
                }
                if($meta_field_name == 'website' && substr(trim($meta_value), 0, 4) !== 'http') {
                    $meta_value = 'http://' . trim($meta_value);
                }
                $meta_fields[$meta_field_name] = trim($meta_value);
            }

            // Image (and link if there is a website meta-data)
            $logo = get_the_post_thumbnail($this_post, 'full');

            if(!empty($logo)) {
				// if we are on the directory master page vs directory-individual
				if(isset($_GET['orgid']) && isset($meta_fields['website']) && !empty($meta_fields['website']))  {
                    // If individual page, link to website
                    $url_link_logo = $meta_fields['website'];
                    $url_link_logo_attrs = 'target="_blank" rel="nofollow"';
                } else {
                    // If index page, link to listing
                    $url_link_logo = $individual_url;
                    $url_link_logo_attrs = '';
                }

                $html .= '<a href="'.$url_link_logo.'" '.$url_link_logo_attrs.'>';
                $html .= $logo;
                $html .= '</a>';
            }

            // Item title
            $html .= '<h3><a href="'.$individual_url.'">'.$this_post->post_title.'</a></h3>';

            if(isset($meta_fields['address']) || isset($meta_fields['suburb'])) {
                $html .=  '<p>';

                if(isset($meta_fields['address']))
                    $html .= $meta_fields['address'];
                if(isset($meta_fields['suburb'])) 
                    $html .= (isset($meta_fields['address']) ? '<br />' : '') . $meta_fields['suburb'];
                if(isset($meta_fields['state']))
                    $html .= '<br />' . $meta_fields['state'];
                if(isset($meta_fields['postcode']))
                    $html .= ' ' . $meta_fields['postcode'];

                $html .= '</p>';
            }

			// Show the Website Link
            if(isset($meta_fields['website'])) {
                if(substr($meta_fields['website'], 0, 4) !== 'http') {
                    $meta_fields['website'] = 'http://' . $meta_fields['website'];
                }
                $html .= '<p><a href="'.$meta_fields['website'].'" target="_blank" rel="nofollow">'.$meta_fields['website'].'</a></p>';
            }

            if(isset($meta_fields['phone'])) {
                $html .= '<p>'.$meta_fields['phone'].'</p>';
            }

            if(isset($_GET['orgid'])) {
                $html .= apply_filters('the_content', $this_post->post_content);
            }


            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;

    }

}

$ProfilerOrgTypeObj = New ProfilerOrgType();
register_activation_hook(__FILE__, array($ProfilerOrgTypeObj, 'activate'));
