<?php
/*
Plugin Name: Alfresco Search Plugin
Description: Search Alfresco repository via the Alfresco API with a left‑sidebar search form, download/view proxy, and admin options for custom link text or icon‑only view. Only nodes of type cm:content are shown. AJAX pagination and asynchronous node details (title and description) loading are implemented.
Version: 1.4.6
Author: Sergio Baião <sergio@plugada.net>
Text Domain: alfresco-search
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* ---------------------------------------------------------------------------
   Load Plugin Text Domain
--------------------------------------------------------------------------- */
function alfresco_search_load_textdomain() {
    load_plugin_textdomain( 'alfresco-search', false, dirname( plugin_basename(__FILE__) ) . '/languages' );
}
add_action( 'plugins_loaded', 'alfresco_search_load_textdomain' );

/* ---------------------------------------------------------------------------
   Enqueue Assets (Tailwind, CSS & JS)
--------------------------------------------------------------------------- */
function alfresco_search_enqueue_assets() {
    wp_enqueue_style(
        'tailwindcss',
        'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
        array(),
        '2.2.19'
    );
    wp_enqueue_style(
        'alfresco-search-style',
        plugin_dir_url(__FILE__) . 'alfresco-search.css',
        array(),
        '1.0'
    );
    wp_enqueue_script(
        'alfresco-search-js',
        plugin_dir_url(__FILE__) . 'alfresco-search.js',
        array('jquery'),
        '1.0',
        true
    );
    wp_localize_script('alfresco-search-js', 'alfrescoSearch', array(
        'ajax_url' => admin_url('admin-ajax.php')
    ));
}
add_action('wp_enqueue_scripts', 'alfresco_search_enqueue_assets');

/* ---------------------------------------------------------------------------
   Get Plugin Options
--------------------------------------------------------------------------- */
function alfresco_search_get_options() {
    return array(
        'alfresco_url'            => get_option('alfresco_url', 'https://alfresco.pge.pi.gov.br/alfresco'),
        'alfresco_username'       => get_option('alfresco_username', 'admin'),
        'alfresco_password'       => get_option('alfresco_password', 'admin'),
        'alfresco_default_site'   => get_option('alfresco_default_site', ''),
        'alfresco_max_results'    => get_option('alfresco_max_results', 10000),
        'alfresco_debug'          => get_option('alfresco_debug', 0),
        'alfresco_download_text'  => get_option('alfresco_download_text', __('Download', 'alfresco-search')),
        'alfresco_view_text'      => get_option('alfresco_view_text', __('View', 'alfresco-search')),
        'alfresco_icons_only'     => get_option('alfresco_icons_only', 0),
        'alfresco_download_icon'  => get_option('alfresco_download_icon', '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-box-arrow-down" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M3.5 10a.5.5 0 0 1-.5-.5v-8a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 .5.5v8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 0 0 1h2A1.5 1.5 0 0 0 14 9.5v-8A1.5 1.5 0 0 0 12.5 0h-9A1.5 1.5 0 0 0 2 1.5v8A1.5 1.5 0 0 0 3.5 11h2a.5.5 0 0 0 0-1h-2z"></path><path fill-rule="evenodd" d="M7.646 15.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 14.293V5.5a.5.5 0 0 0-1 0v8.793l-2.146-2.147a.5.5 0 0 0-.708.708l3 3z"></path></svg>'),
        'alfresco_view_icon'      => get_option('alfresco_view_icon', '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16"><path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8zM1.173 8a13.133 13.133 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5c2.12 0 3.879 1.168 5.168 2.457A13.133 13.133 0 0 1 14.828 8c-.058.087-.122.183-.195.288-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5c-2.12 0-3.879-1.168-5.168-2.457A13.134 13.134 0 0 1 1.172 8z"></path><path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0z"></path></svg>')
    );
}

/* ---------------------------------------------------------------------------
   Register Plugin Settings
--------------------------------------------------------------------------- */
function alfresco_search_register_settings(){
    register_setting('alfresco_search_options_group', 'alfresco_url');
    register_setting('alfresco_search_options_group', 'alfresco_username');
    register_setting('alfresco_search_options_group', 'alfresco_password');
    register_setting('alfresco_search_options_group', 'alfresco_default_site');
    register_setting('alfresco_search_options_group', 'alfresco_max_results');
    register_setting('alfresco_search_options_group', 'alfresco_debug');
    register_setting('alfresco_search_options_group', 'alfresco_download_text');
    register_setting('alfresco_search_options_group', 'alfresco_view_text');
    register_setting('alfresco_search_options_group', 'alfresco_icons_only');
    register_setting('alfresco_search_options_group', 'alfresco_download_icon');
    register_setting('alfresco_search_options_group', 'alfresco_view_icon');
}
add_action('admin_init', 'alfresco_search_register_settings');

/* ---------------------------------------------------------------------------
   Add Admin Menu and Options Page (Single Form)
--------------------------------------------------------------------------- */
function alfresco_search_add_admin_menu(){
    add_menu_page(
        __('Alfresco Settings', 'alfresco-search'),
        __('Alfresco Settings', 'alfresco-search'),
        'manage_options',
        'alfresco_search',
        'alfresco_search_options_page'
    );
}
add_action('admin_menu', 'alfresco_search_add_admin_menu');

function alfresco_search_options_page(){
    $options = alfresco_search_get_options();
    $list_sites = ( isset($_GET['action']) && $_GET['action'] == 'list_sites' );
    ?>
    <div class="wrap">
        <h1 class="text-2xl font-bold mb-4"><?php _e('Alfresco Settings', 'alfresco-search'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('alfresco_search_options_group'); ?>
            <?php do_settings_sections('alfresco_search_options_group'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Alfresco URL', 'alfresco-search'); ?></th>
                    <td><input type="text" name="alfresco_url" value="<?php echo esc_attr($options['alfresco_url']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Alfresco Username', 'alfresco-search'); ?></th>
                    <td><input type="text" name="alfresco_username" value="<?php echo esc_attr($options['alfresco_username']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Alfresco Password', 'alfresco-search'); ?></th>
                    <td><input type="password" name="alfresco_password" value="<?php echo esc_attr($options['alfresco_password']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Maximum Results', 'alfresco-search'); ?></th>
                    <td><input type="number" name="alfresco_max_results" value="<?php echo esc_attr($options['alfresco_max_results']); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Debugging', 'alfresco-search'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="alfresco_debug" value="1" <?php checked($options['alfresco_debug'], 1); ?>>
                            <?php _e('Output debug information to the browser console.', 'alfresco-search'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Download Link Text', 'alfresco-search'); ?></th>
                    <td><input type="text" name="alfresco_download_text" value="<?php echo esc_attr($options['alfresco_download_text']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('View Link Text', 'alfresco-search'); ?></th>
                    <td><input type="text" name="alfresco_view_text" value="<?php echo esc_attr($options['alfresco_view_text']); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Show Only Icons', 'alfresco-search'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="alfresco_icons_only" value="1" <?php checked($options['alfresco_icons_only'], 1); ?>>
                            <?php _e('Replace link texts with icons.', 'alfresco-search'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Download Icon SVG', 'alfresco-search'); ?></th>
                    <td><textarea name="alfresco_download_icon" rows="3" class="large-text code"><?php echo esc_textarea($options['alfresco_download_icon']); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('View Icon SVG', 'alfresco-search'); ?></th>
                    <td><textarea name="alfresco_view_icon" rows="3" class="large-text code"><?php echo esc_textarea($options['alfresco_view_icon']); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Default Site', 'alfresco-search'); ?></th>
                    <td>
                        <?php
                        if($list_sites){
                            $alfresco_url_trimmed = rtrim($options['alfresco_url'], '/');
                            $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
                            $sites_url = $alfresco_url_trimmed . "/api/-default-/public/alfresco/versions/1/sites";
                            $response = wp_remote_get($sites_url, array(
                                'timeout'   => 20,
                                'sslverify' => false,
                                'headers'   => array('Authorization' => $auth_header)
                            ));
                            $site_options = array();
                            if( ! is_wp_error($response) ) {
                                $data = json_decode(wp_remote_retrieve_body($response), true);
                                if(isset($data['list']['entries'])){
                                    foreach ($data['list']['entries'] as $entry) {
                                        $entry_data = $entry['entry'];
                                        $site_options[] = isset($entry_data['shortName']) ? $entry_data['shortName'] : (isset($entry_data['id']) ? $entry_data['id'] : __('Unknown Site', 'alfresco-search'));
                                    }
                                }
                            }
                            if(empty($site_options)){
                                echo '<p>' . __('No Alfresco sites found.', 'alfresco-search') . '</p>';
                            } else {
                                echo '<select name="alfresco_default_site">';
                                echo '<option value="">' . __('-- None --', 'alfresco-search') . '</option>';
                                foreach($site_options as $s){
                                    echo '<option value="' . esc_attr($s) . '" ' . selected($options['alfresco_default_site'], $s, false) . '>' . esc_html($s) . '</option>';
                                }
                                echo '</select>';
                            }
                            echo '<p><a href="' . esc_url(add_query_arg('action', 'list_sites')) . '">' . __('Refresh Site List', 'alfresco-search') . '</a></p>';
                        } else {
                            echo '<input type="text" readonly name="alfresco_default_site" value="' . esc_attr($options['alfresco_default_site']) . '" class="regular-text">';
                            echo '<p><a href="' . esc_url(add_query_arg('action', 'list_sites')) . '">' . __('List Alfresco Sites', 'alfresco-search') . '</a></p>';
                        }
                        ?>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/* ---------------------------------------------------------------------------
   Helper Functions for Pagination & AJAX URLs
--------------------------------------------------------------------------- */
function alfresco_search_page_url($new_page) {
    $args = $_GET;
    $args['page'] = $new_page;
    $args['submitted'] = 1;
    return add_query_arg($args, get_permalink());
}
function alfresco_search_ajax_url($new_page) {
    $args = $_GET;
    $args['page'] = $new_page;
    $args['submitted'] = 1;
    $args['action'] = 'alfresco_search_results';
    return add_query_arg($args, admin_url('admin-ajax.php'));
}

/* ---------------------------------------------------------------------------
   Folder Retrieval for Search Interface
--------------------------------------------------------------------------- */
function alfresco_search_get_folders($site) {
    $options = alfresco_search_get_options();
    $alfresco_url = rtrim($options['alfresco_url'], '/');
    $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
    
    $url_dl = $alfresco_url . "/api/-default-/public/alfresco/versions/1/sites/{$site}/containers/documentLibrary";
    $response = wp_remote_get($url_dl, array(
        'timeout'   => 20,
        'sslverify' => false,
        'headers'   => array('Authorization' => $auth_header)
    ));
    if (is_wp_error($response)) return array();
    $dl_data = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($dl_data['entry']['id'])) return array();
    $dl_id = $dl_data['entry']['id'];
    
    $query = 'PATH:"/app:company_home/st:sites/cm:' . $site . '/cm:documentLibrary//*" AND TYPE:"cm:folder"';
    $payload = array(
        "query" => array("query" => $query),
        "paging" => array("maxItems" => 1000, "skipCount" => 0)
    );
    $url_search = $alfresco_url . "/api/-default-/public/search/versions/1/search";
    $response = wp_remote_post($url_search, array(
        'body'      => json_encode($payload),
        'headers'   => array(
            'Content-Type'  => 'application/json',
            'Authorization' => $auth_header
        ),
        'timeout'   => 20,
        'sslverify' => false
    ));
    if (is_wp_error($response)) return array();
    $search_data = json_decode(wp_remote_retrieve_body($response), true);
    $entries = isset($search_data['list']['entries']) ? $search_data['list']['entries'] : array();
    
    $folders = array();
    $special_chars = str_split("_*&%$#@!");
    foreach ($entries as $entry) {
        $node = $entry['entry'];
        $name = isset($node['name']) ? $node['name'] : '';
        if ($name && in_array(substr($name, 0, 1), $special_chars)) continue;
        $folder_id = $node['id'];
        $parent_id = isset($node['parentId']) ? $node['parentId'] : '';
        $folders[$folder_id] = array(
            "id" => $folder_id,
            "name" => $name,
            "parentId" => $parent_id,
            "children" => array()
        );
    }
    
    $tree = array();
    foreach ($folders as $folder) {
        $pid = $folder['parentId'];
        if ($pid == $dl_id || !isset($folders[$pid])) {
            $tree[] = $folder;
        } else {
            $folders[$pid]['children'][] = $folder;
        }
    }
    
    function flatten_tree($folder_list, $parent_relative = "", $depth = 0) {
        $options = array();
        usort($folder_list, function($a, $b){ return strcmp($a['name'], $b['name']); });
        foreach ($folder_list as $folder) {
            $full_relative = $parent_relative ? $parent_relative . "/" . $folder['name'] : $folder['name'];
            $display = str_repeat("- ", $depth) . $folder['name'];
            $options[] = array("node_id" => $folder['id'], "value" => $full_relative, "display" => $display);
            if (!empty($folder['children'])) {
                $options = array_merge($options, flatten_tree($folder['children'], $full_relative, $depth+1));
            }
        }
        return $options;
    }
    return flatten_tree($tree);
}

/* ---------------------------------------------------------------------------
   Pagination Helper
--------------------------------------------------------------------------- */
function alfresco_search_pagination_range($current, $total, $delta = 5) {
    $start = max(1, $current - $delta);
    $end = min($total, $current + $delta);
    return range($start, $end);
}

/* ---------------------------------------------------------------------------
   Download Proxy Endpoint
--------------------------------------------------------------------------- */
function alfresco_search_handle_download(){
    if(isset($_GET['alfresco_download'])){
        $node_id = sanitize_text_field($_GET['alfresco_download']);
        $options = alfresco_search_get_options();
        $alfresco_url = rtrim($options['alfresco_url'], '/');
        $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
        $download_url = $alfresco_url . "/api/-default-/public/alfresco/versions/1/nodes/{$node_id}/content?attachment=true";
        $response = wp_remote_get($download_url, array(
            'timeout'   => 20,
            'sslverify' => false,
            'headers'   => array('Authorization' => $auth_header)
        ));
        if(is_wp_error($response)){
            wp_die($response->get_error_message());
        } else {
            header("Content-Disposition: " . wp_remote_retrieve_header($response, 'Content-Disposition'));
            header("Content-Type: " . wp_remote_retrieve_header($response, 'Content-Type'));
            echo wp_remote_retrieve_body($response);
            exit;
        }
    }
}
add_action('init', 'alfresco_search_handle_download');

/* ---------------------------------------------------------------------------
   View File Endpoint (opens inline in new tab)
--------------------------------------------------------------------------- */
function alfresco_search_handle_view() {
    if(isset($_GET['alfresco_view'])){
        $node_id = sanitize_text_field($_GET['alfresco_view']);
        $options = alfresco_search_get_options();
        $alfresco_url = rtrim($options['alfresco_url'], '/');
        $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
        $view_url = $alfresco_url . "/api/-default-/public/alfresco/versions/1/nodes/{$node_id}/content";
        $response = wp_remote_get($view_url, array(
            'timeout'   => 20,
            'sslverify' => false,
            'headers'   => array('Authorization' => $auth_header)
        ));
        if(is_wp_error($response)){
            wp_die($response->get_error_message());
        } else {
            header("Content-Disposition: inline");
            header("Content-Type: " . wp_remote_retrieve_header($response, 'Content-Type'));
            echo wp_remote_retrieve_body($response);
            exit;
        }
    }
}
add_action('init', 'alfresco_search_handle_view');

/* ---------------------------------------------------------------------------
   Helper: URL builder for Download and View
--------------------------------------------------------------------------- */
function alfresco_search_url_for($endpoint, $params = array()){
    if($endpoint == 'download_file'){
        return add_query_arg('alfresco_download', $params['node_id'], home_url());
    } elseif($endpoint == 'view_file'){
        return add_query_arg('alfresco_view', $params['node_id'], home_url());
    }
    return home_url();
}

/* ---------------------------------------------------------------------------
   AJAX Handler for Node Details (Title & Description)
--------------------------------------------------------------------------- */
function alfresco_search_node_details() {
    if ( empty($_GET['node_id']) ) {
        wp_send_json_error(__('No node_id provided', 'alfresco-search'));
    }
    $node_id = sanitize_text_field($_GET['node_id']);
    $options = alfresco_search_get_options();
    $alfresco_url = rtrim($options['alfresco_url'], '/');
    $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
    $node_url = $alfresco_url . "/api/-default-/public/alfresco/versions/1/nodes/" . $node_id;
    $response = wp_remote_get($node_url, array(
        'timeout' => 20,
        'sslverify' => false,
        'headers' => array('Authorization' => $auth_header)
    ));
    if(is_wp_error($response)){
        wp_send_json_error($response->get_error_message());
    } else {
        $node_data = json_decode(wp_remote_retrieve_body($response), true);
        wp_send_json_success($node_data['entry']);
    }
}
add_action('wp_ajax_alfresco_node_details', 'alfresco_search_node_details');
add_action('wp_ajax_nopriv_alfresco_node_details', 'alfresco_search_node_details');

/* ---------------------------------------------------------------------------
   AJAX Handler for Search Results
--------------------------------------------------------------------------- */
function alfresco_search_ajax_handler() {
    $options = alfresco_search_get_options();
    $site = isset($_GET['site']) ? sanitize_text_field($_GET['site']) : $options['alfresco_default_site'];
    $folder = isset($_GET['folder']) ? sanitize_text_field($_GET['folder']) : '';
    // Use "alfresco_name" instead of "name" to avoid conflict.
    $alfresco_name = isset($_GET['alfresco_name']) ? sanitize_text_field($_GET['alfresco_name']) : '';
    $title = isset($_GET['title']) ? sanitize_text_field($_GET['title']) : '';
    $description = isset($_GET['description']) ? sanitize_text_field($_GET['description']) : '';
    $file_type = isset($_GET['file_type']) ? sanitize_text_field($_GET['file_type']) : 'both';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 50;
    
    $folder_options = array();
    $selected_relative = '';
    if($site){
        $folder_options = alfresco_search_get_folders($site);
        if($folder){
            foreach($folder_options as $opt){
                if($opt['node_id'] == $folder){
                    $selected_relative = $opt['value'];
                    break;
                }
            }
        }
    }
    
    $conditions = array();
    if($site){
        if($folder && $selected_relative){
            $parts = explode('/', $selected_relative);
            $cm_parts = array();
            foreach($parts as $part){
                if (strpos($part, ' ') !== false) {
                    $cm_parts[] = 'cm:"' . $part . '"';
                } else {
                    $cm_parts[] = 'cm:' . $part;
                }
            }
            $cm_path = implode('/', $cm_parts);
            $conditions[] = 'PATH:"/app:company_home/st:sites/cm:' . $site . '/cm:documentLibrary/' . $cm_path . '//*"';
        } else {
            $conditions[] = 'PATH:"/app:company_home/st:sites/cm:' . $site . '/cm:documentLibrary//*"';
        }
    }
    if($alfresco_name){
        $conditions[] = 'cm:name:"' . $alfresco_name . '"';
    }
    if($title){
        $conditions[] = 'cm:title:"' . $title . '"';
    }
    if($description){
        $conditions[] = 'cm:description:"' . $description . '"';
    }
    /*if($file_type == 'pdf'){
        $conditions[] = 'cm:content.mimetype:"application/pdf"';
    } elseif($file_type == 'doc'){
        $conditions[] = '(cm:content.mimetype:"application/msword" OR cm:content.mimetype:"application/vnd.openxmlformats-officedocument.wordprocessingml.document")';
    } else {
        $conditions[] = '(cm:content.mimetype:"application/pdf" OR cm:content.mimetype:"application/msword" OR cm:content.mimetype:"application/vnd.openxmlformats-officedocument.wordprocessingml.document")';
    }*/
	if($file_type == 'pdf'){
        $conditions[] = 'cm:name:"*.pdf"';
    } elseif($file_type == 'doc'){
        $conditions[] = '(cm:name:"*.doc" OR cm:name:"*.docx")';
    } else {
        $conditions[] = '(cm:name:"*.pdf" OR cm:name:"*.doc" OR cm:name:"*.docx")';
    }
    $conditions[] = 'TYPE:"cm:content"';
    $query_string = implode(' AND ', $conditions);
    $skipCount = ($page - 1) * $page_size;
    $payload = array(
        "query" => array("query" => $query_string),
        "paging" => array("maxItems" => $page_size, "skipCount" => $skipCount)
    );
    
    $results = array();
    $total_items = 0;
    $error_message = '';
    
    $alfresco_url_trimmed = rtrim($options['alfresco_url'], '/');
    $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
    $search_url = $alfresco_url_trimmed . "/api/-default-/public/search/versions/1/search";
    $response = wp_remote_post($search_url, array(
        'body'      => json_encode($payload),
        'headers'   => array(
            'Content-Type'  => 'application/json',
            'Authorization' => $auth_header
        ),
        'timeout'   => 20,
        'sslverify' => false
    ));
    if(is_wp_error($response)){
        $error_message = $response->get_error_message();
    } else {
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if(isset($data['list'])){
            foreach ($data['list']['entries'] as $entry) {
                $node = $entry['entry'];
                if ( isset($node['nodeType']) && $node['nodeType'] === 'cm:folder' ) {
                    continue;
                }
                $results[] = $entry;
            }
            $total_items = isset($data['list']['pagination']['totalItems']) ? intval($data['list']['pagination']['totalItems']) : 0;
            if($total_items > $options['alfresco_max_results']){
                $total_items = $options['alfresco_max_results'];
            }
        } else {
            $error_message = __("Invalid response from Alfresco", "alfresco-search");
        }
    }
    
    $total_pages = ($page_size && $total_items > 0) ? ceil($total_items / $page_size) : 0;
    echo alfresco_search_get_results_markup($results, $total_items, $total_pages, $page);
    wp_die();
}
add_action('wp_ajax_alfresco_search_results', 'alfresco_search_ajax_handler');
add_action('wp_ajax_nopriv_alfresco_search_results', 'alfresco_search_ajax_handler');

/* ---------------------------------------------------------------------------
   Helper: Output Results Markup
--------------------------------------------------------------------------- */
function alfresco_search_get_results_markup($results, $total_items, $total_pages, $page) {
    ob_start();
    ?>
    <?php if($total_items > 0): ?>
        <h4 class="text-2xl font-bold mb-4"><?php printf( __('Search Results (%d total)', 'alfresco-search'), intval($total_items) ); ?></h4>
        <?php if($total_pages > 1): ?>
            <div class="pagination flex space-x-2 mb-4">
                <?php if($page > 1): ?>
                    <a href="<?php echo esc_url(alfresco_search_ajax_url($page-1)); ?>" class="px-4 py-2 border rounded">&#9664;</a>
                <?php endif; ?>
                <?php foreach(alfresco_search_pagination_range($page, $total_pages) as $p): ?>
                    <?php if($p == $page): ?>
                        <span class="px-4 py-2 border rounded bg-gray-300"><?php echo intval($p); ?></span>
                    <?php else: ?>
                        <a href="<?php echo esc_url(alfresco_search_ajax_url($p)); ?>" class="px-4 py-2 border rounded"><?php echo intval($p); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if($page < $total_pages): ?>
                    <a href="<?php echo esc_url(alfresco_search_ajax_url($page+1)); ?>" class="px-4 py-2 border rounded">&#9654;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="results-table overflow-x-auto">
            <table class="min-w-full border-collapse table-auto">
                <thead>
                    <tr>
                        <th class="border p-2"><?php _e('File Name', 'alfresco-search'); ?></th>
                        <th class="border p-2"><?php _e('Title &amp; Description', 'alfresco-search'); ?></th>
                        <th class="border p-2"><?php _e('Actions', 'alfresco-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $download_text = get_option('alfresco_download_text', __('Download', 'alfresco-search'));
                    $view_text = get_option('alfresco_view_text', __('View', 'alfresco-search'));
                    $icons_only = get_option('alfresco_icons_only', 0);
                    $download_icon = get_option('alfresco_download_icon', '');
                    $view_icon = get_option('alfresco_view_icon', '');
                    ?>
                    <?php foreach($results as $entry): ?>
                        <?php $node = $entry['entry']; ?>
                        <tr>
                            <td class="border p-2 text-small"><?php echo esc_html($node['name']); ?></td>
                            <td class="border p-2">
                                <div class="node-details" data-node-id="<?php echo esc_attr($node['id']); ?>">
                                    <div class="node-title text-[13px]"><?php _e('Loading title...', 'alfresco-search'); ?></div>
                                    <div class="node-description text-[12px]"><?php _e('Loading description...', 'alfresco-search'); ?></div>
                                </div>
                            </td>
                            <td class="border p-2">
                                <?php if($icons_only): ?>
                                    <a href="<?php echo esc_url(alfresco_search_url_for('download_file', array('node_id' => $node['id']))); ?>" class="block text-blue-500" title="<?php _e('Download', 'alfresco-search'); ?>">
                                        <?php echo $download_icon ? $download_icon : esc_html($download_text); ?>
                                    </a>	
                                    <a href="<?php echo esc_url(alfresco_search_url_for('view_file', array('node_id' => $node['id']))); ?>" class="block text-blue-500 mt-1" title="<?php _e('View', 'alfresco-search'); ?>" target="_blank">
                                        <?php echo $view_icon ? $view_icon : esc_html($view_text); ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?php echo esc_url(alfresco_search_url_for('download_file', array('node_id' => $node['id']))); ?>" class="block text-blue-500">
                                        <?php echo esc_html($download_text); ?>
                                    </a>
                                    <a href="<?php echo esc_url(alfresco_search_url_for('view_file', array('node_id' => $node['id']))); ?>" class="block text-blue-500 mt-1" target="_blank">
                                        <?php echo esc_html($view_text); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if($total_pages > 1): ?>
            <div class="pagination flex space-x-2 mt-4">
                <?php if($page > 1): ?>
                    <a href="<?php echo esc_url(alfresco_search_ajax_url($page-1)); ?>" class="px-4 py-2 border rounded">&#9664;</a>
                <?php endif; ?>
                <?php foreach(alfresco_search_pagination_range($page, $total_pages) as $p): ?>
                    <?php if($p == $page): ?>
                        <span class="px-4 py-2 border rounded bg-gray-300"><?php echo intval($p); ?></span>
                    <?php else: ?>
                        <a href="<?php echo esc_url(alfresco_search_ajax_url($p)); ?>" class="px-4 py-2 border rounded"><?php echo intval($p); ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php if($page < $total_pages): ?>
                    <a href="<?php echo esc_url(alfresco_search_ajax_url($page+1)); ?>" class="px-4 py-2 border rounded">&#9654;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <p><?php _e('No results found.', 'alfresco-search'); ?></p>
    <?php endif;
    return ob_get_clean();
}

/* ---------------------------------------------------------------------------
   Shortcode for Public Search Interface
--------------------------------------------------------------------------- */
function alfresco_search_shortcode($atts){
    $options = alfresco_search_get_options();
    $site = isset($_GET['site']) ? sanitize_text_field($_GET['site']) : $options['alfresco_default_site'];
    $folder = isset($_GET['folder']) ? sanitize_text_field($_GET['folder']) : '';
    $alfresco_name = isset($_GET['alfresco_name']) ? sanitize_text_field($_GET['alfresco_name']) : '';
    $title = isset($_GET['title']) ? sanitize_text_field($_GET['title']) : '';
    $description = isset($_GET['description']) ? sanitize_text_field($_GET['description']) : '';
    $file_type = isset($_GET['file_type']) ? sanitize_text_field($_GET['file_type']) : 'both';
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 50;
    $submitted = isset($_GET['submitted']);
    
    $folder_options = array();
    $selected_relative = '';
    if($site){
        $folder_options = alfresco_search_get_folders($site);
        if($folder){
            foreach($folder_options as $opt){
                if($opt['node_id'] == $folder){
                    $selected_relative = $opt['value'];
                    break;
                }
            }
        }
    }
    
    $conditions = array();
    if($site){
        if($folder && $selected_relative){
            $parts = explode('/', $selected_relative);
            $cm_parts = array();
            foreach($parts as $part){
                if (strpos($part, ' ') !== false) {
                    $cm_parts[] = 'cm:"' . $part . '"';
                } else {
                    $cm_parts[] = 'cm:' . $part;
                }
            }
            $cm_path = implode('/', $cm_parts);
            $conditions[] = 'PATH:"/app:company_home/st:sites/cm:' . $site . '/cm:documentLibrary/' . $cm_path . '//*"';
        } else {
            $conditions[] = 'PATH:"/app:company_home/st:sites/cm:' . $site . '/cm:documentLibrary//*"';
        }
    }
    if($alfresco_name){
        $conditions[] = 'cm:name:"' . $alfresco_name . '"';
    }
    if($title){
        $conditions[] = 'cm:title:"' . $title . '"';
    }
    if($description){
        $conditions[] = 'cm:description:"' . $description . '"';
    }
    if($file_type == 'pdf'){
        $conditions[] = 'cm:content.mimetype:"application/pdf"';
    } elseif($file_type == 'doc'){
        $conditions[] = '(cm:content.mimetype:"application/msword" OR cm:content.mimetype:"application/vnd.openxmlformats-officedocument.wordprocessingml.document")';
    } else {
        $conditions[] = '(cm:content.mimetype:"application/pdf" OR cm:content.mimetype:"application/msword" OR cm:content.mimetype:"application/vnd.openxmlformats-officedocument.wordprocessingml.document")';
    }
    $conditions[] = 'TYPE:"cm:content"';
    $query_string = implode(' AND ', $conditions);
    $skipCount = ($page - 1) * $page_size;
    $payload = array(
        "query" => array("query" => $query_string),
        "paging" => array("maxItems" => $page_size, "skipCount" => $skipCount)
    );
    
    $results = array();
    $total_items = 0;
    $error_message = '';
    if($submitted){
        $alfresco_url_trimmed = rtrim($options['alfresco_url'], '/');
        $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
        $search_url = $alfresco_url_trimmed . "/api/-default-/public/search/versions/1/search";
        $response = wp_remote_post($search_url, array(
            'body'      => json_encode($payload),
            'headers'   => array(
                'Content-Type'  => 'application/json',
                'Authorization' => $auth_header
            ),
            'timeout'   => 20,
            'sslverify' => false
        ));
        if(is_wp_error($response)){
            $error_message = $response->get_error_message();
        } else {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if(isset($data['list'])){
                foreach ($data['list']['entries'] as $entry) {
                    $node = $entry['entry'];
                    if ( isset($node['nodeType']) && $node['nodeType'] === 'cm:folder' ) {
                        continue;
                    }
                    $results[] = $entry;
                }
                $total_items = isset($data['list']['pagination']['totalItems']) ? intval($data['list']['pagination']['totalItems']) : 0;
                if($total_items > $options['alfresco_max_results']){
                    $total_items = $options['alfresco_max_results'];
                }
            } else {
                $error_message = __("Invalid response from Alfresco", "alfresco-search");
            }
        }
    }
    
    $total_pages = ($page_size && $total_items > 0) ? ceil($total_items / $page_size) : 0;
    
    $output = '';
    $output .= '<div class="alfresco-search-container">';
    $output .= '<div class="alfresco-search-form">';
    $output .= '<h4 class="text-2xl font-bold mb-4">' . __('Search Options', 'alfresco-search') . '</h4>';
    $output .= '<form method="get" action="' . esc_url( get_permalink() ) . '">';
    $output .= '<input type="hidden" name="page_id" value="' . get_the_ID() . '">';
    if(!$options['alfresco_default_site']){
        $output .= '<p><label for="site" class="block font-medium">' . __('Site', 'alfresco-search') . ':</label>';
        $output .= '<input type="text" name="site" id="site" value="' . esc_attr(isset($_GET['site']) ? $_GET['site'] : '') . '" class="mt-1 block w-full border-gray-300 rounded"></p>';
    } else {
        $output .= '<input type="hidden" name="site" value="' . esc_attr($options['alfresco_default_site']) . '">';
    }
    $output .= '<p><label for="folder" class="block font-medium">' . __('Folder', 'alfresco-search') . ':</label>';
    $output .= '<select name="folder" id="folder" class="mt-1 block w-full border-gray-300 rounded">';
    $output .= '<option value="">' . __('All Folders', 'alfresco-search') . '</option>';
    foreach($folder_options as $opt){
        $output .= '<option value="' . esc_attr($opt['node_id']) . '" ' . selected(isset($_GET['folder']) ? $_GET['folder'] : '', $opt['node_id'], false) . '>';
        $output .= esc_html($opt['display']) . '</option>';
    }
    $output .= '</select></p>';
    $output .= '<p><label for="file_type" class="block font-medium">' . __('File Type', 'alfresco-search') . ':</label>';
    $output .= '<select name="file_type" id="file_type" class="mt-1 block w-full border-gray-300 rounded">';
    $output .= '<option value="both" ' . selected(isset($_GET['file_type']) ? $_GET['file_type'] : 'both', 'both', false) . '>' . __('Both (PDF & DOC/DOCX)', 'alfresco-search') . '</option>';
    $output .= '<option value="pdf" ' . selected(isset($_GET['file_type']) ? $_GET['file_type'] : '', 'pdf', false) . '>' . __('PDF', 'alfresco-search') . '</option>';
    $output .= '<option value="doc" ' . selected(isset($_GET['file_type']) ? $_GET['file_type'] : '', 'doc', false) . '>' . __('DOC/DOCX', 'alfresco-search') . '</option>';
    $output .= '</select></p>';
    $output .= '<p><label for="alfresco_name" class="block font-medium">' . __('Name', 'alfresco-search') . ':</label>';
    $output .= '<input type="text" name="alfresco_name" id="alfresco_name" value="' . esc_attr(isset($_GET['alfresco_name']) ? $_GET['alfresco_name'] : '') . '" class="mt-1 block w-full border-gray-300 rounded"></p>';
    $output .= '<p><label for="title" class="block font-medium">' . __('Title', 'alfresco-search') . ':</label>';
    $output .= '<input type="text" name="title" id="title" value="' . esc_attr(isset($_GET['title']) ? $_GET['title'] : '') . '" class="mt-1 block w-full border-gray-300 rounded"></p>';
    $output .= '<p><label for="description" class="block font-medium">' . __('Description', 'alfresco-search') . ':</label>';
    $output .= '<input type="text" name="description" id="description" value="' . esc_attr(isset($_GET['description']) ? $_GET['description'] : '') . '" class="mt-1 block w-full border-gray-300 rounded"></p>';
    $output .= '<p><label for="page_size" class="block font-medium">' . __('Results per page', 'alfresco-search') . ':</label>';
    $output .= '<select name="page_size" id="page_size" class="mt-1 block w-full border-gray-300 rounded">';
    foreach(array(30,50,100,200,300) as $opt){
        $output .= '<option value="' . esc_attr($opt) . '" ' . selected(isset($_GET['page_size']) ? $_GET['page_size'] : 50, $opt, false) . '>';
        $output .= esc_html($opt) . '</option>';
    }
    $output .= '</select></p>';
    $output .= '<input type="hidden" name="submitted" value="1">';
    $output .= '<p><button type="submit" class="w-full bg-blue-500 text-white py-2 rounded">' . __('Search', 'alfresco-search') . '</button></p>';
    $output .= '</form></div>';
    
    $output .= '<div class="alfresco-search-results" id="alfresco-search-results">';
    $output .= alfresco_search_get_results_markup($results, $total_items, $total_pages, $page);
    $output .= '</div></div>';
    
    if(alfresco_search_get_options()['alfresco_debug']){
        $debug_data = array(
            'cmis_query' => $query_string,
            'first_3_results' => array_slice($results, 0, 3)
        );
        $output .= '<script>console.log("ALFRESCO DEBUG:", ' . json_encode($debug_data) . ');</script>';
    }
    
    return $output;
}
add_shortcode('alfresco_search', 'alfresco_search_shortcode');
?>
