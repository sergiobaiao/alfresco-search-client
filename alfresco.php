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
    $options = alfresco_search_get_options();
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
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => alfresco_search_generate_nonce(),
        'debug'    => alfresco_search_is_debug_enabled($options),
        'genericError' => __('Unable to load search results. Please try again.', 'alfresco-search')
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
   Security & Query Helper Functions
--------------------------------------------------------------------------- */
function alfresco_search_get_nonce_action() {
    return 'alfresco_search_request';
}

function alfresco_search_generate_nonce() {
    return wp_create_nonce(alfresco_search_get_nonce_action());
}

function alfresco_search_verify_nonce($nonce) {
    return (bool) wp_verify_nonce($nonce, alfresco_search_get_nonce_action());
}

function alfresco_search_current_user_can_access() {
    $can_access = is_user_logged_in() ? current_user_can('read') : true;
    return (bool) apply_filters('alfresco_search_user_can_access', $can_access);
}

function alfresco_search_escape_cmis_value($value) {
    $value = sanitize_text_field(wp_unslash($value));
    return str_replace(array('\\', '"'), array('\\\\', '\\"'), $value);
}

function alfresco_search_build_field_condition($field, $value, $mode = 'exact') {
    if ($value === '') {
        return '';
    }

    if ($mode === 'contains') {
        $value = alfresco_search_normalize_contains_value($value);
        if ($value === '') {
            return '';
        }
    }

    return sprintf('%s:"%s"', $field, alfresco_search_escape_cmis_value($value));
}

function alfresco_search_normalize_contains_value($value) {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $parts = preg_split('/\s+/', $value);
    if (!is_array($parts)) {
        $parts = array($value);
    }

    $normalized = array();
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $normalized[] = trim($part, '*');
    }

    if (empty($normalized)) {
        return '';
    }

    $joined = implode('*', $normalized);
    $joined = trim($joined, '*');

    if ($joined === '') {
        return '';
    }

    return '*' . $joined . '*';
}

function alfresco_search_build_path_condition($site, $relative_path = '') {
    if ($site === '') {
        return '';
    }

    $site_segment = alfresco_search_escape_cmis_value($site);
    $path = '/app:company_home/st:sites/cm:' . $site_segment . '/cm:documentLibrary';

    if ($relative_path) {
        $parts = array_filter(array_map('trim', explode('/', $relative_path)));
        $cm_parts = array();
        foreach ($parts as $part) {
            $escaped_part = alfresco_search_escape_cmis_value($part);
            if (strpos($part, ' ') !== false) {
                $cm_parts[] = 'cm:"' . $escaped_part . '"';
            } else {
                $cm_parts[] = 'cm:' . $escaped_part;
            }
        }
        if (!empty($cm_parts)) {
            $path .= '/' . implode('/', $cm_parts);
        }
    }

    return 'PATH:"' . $path . '//*"';
}

function alfresco_search_build_query_preview_url($search_url, $query_string) {
    if (!$search_url || !$query_string) {
        return '';
    }
    return add_query_arg(array('query' => $query_string), $search_url);
}

function alfresco_search_build_conditions($site, $selected_relative, $filters, $file_type_mode = 'mimetype') {
    $conditions = array();

    if ($site) {
        $conditions[] = $selected_relative
            ? alfresco_search_build_path_condition($site, $selected_relative)
            : alfresco_search_build_path_condition($site);
    }

    $search_term = '';
    if (!empty($filters['search_term'])) {
        $search_term = $filters['search_term'];
    } else {
        if (!empty($filters['alfresco_name'])) {
            $conditions[] = alfresco_search_build_field_condition('cm:name', $filters['alfresco_name'], 'contains');
        }

        if (!empty($filters['title'])) {
            $conditions[] = alfresco_search_build_field_condition('cm:title', $filters['title'], 'contains');
        }

        if (!empty($filters['description'])) {
            $conditions[] = alfresco_search_build_field_condition('cm:description', $filters['description'], 'contains');
        }
    }

    if ($search_term !== '') {
        $fields = array('cm:name', 'cm:title', 'cm:description');
        $field_conditions = array();
        foreach ($fields as $field) {
            $field_condition = alfresco_search_build_field_condition($field, $search_term, 'contains');
            if ($field_condition !== '') {
                $field_conditions[] = $field_condition;
            }
        }
        if (!empty($field_conditions)) {
            $conditions[] = '(' . implode(' OR ', $field_conditions) . ')';
        }
    }

    $file_type = isset($filters['file_type']) ? $filters['file_type'] : 'both';
    switch ($file_type) {
        case 'pdf':
            $conditions[] = ($file_type_mode === 'name_wildcard')
                ? 'cm:name:"*.pdf"'
                : 'cm:content.mimetype:"application/pdf"';
            break;
        case 'doc':
            if ($file_type_mode === 'name_wildcard') {
                $conditions[] = '(cm:name:"*.doc" OR cm:name:"*.docx")';
            } else {
                $conditions[] = '(cm:content.mimetype:"application/msword" OR cm:content.mimetype:"application/vnd.openxmlformats-officedocument.wordprocessingml.document")';
            }
            break;
        default:
            if ($file_type_mode === 'name_wildcard') {
                $conditions[] = '(cm:name:"*.pdf" OR cm:name:"*.doc" OR cm:name:"*.docx")';
            } else {
                $conditions[] = '(cm:content.mimetype:"application/pdf" OR cm:content.mimetype:"application/msword" OR cm:content.mimetype:"application/vnd.openxmlformats-officedocument.wordprocessingml.document")';
            }
            break;
    }

    $conditions[] = 'TYPE:"cm:content"';

    return array_filter(array_map('trim', $conditions));
}

function alfresco_search_is_debug_enabled($options = null) {
    if ($options === null) {
        $options = alfresco_search_get_options();
    }

    $debug_option = !empty($options['alfresco_debug']);
    $request_debug = isset($_GET['alfresco_debug']) ? sanitize_text_field(wp_unslash($_GET['alfresco_debug'])) : '';
    if ($request_debug !== '') {
        $debug_option = $debug_option || in_array(strtolower($request_debug), array('1', 'true', 'yes'), true);
    }

    return $debug_option;
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
    $args = array();
    foreach ($_GET as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        $args[$key] = sanitize_text_field(wp_unslash($value));
    }
    $args['page'] = $new_page;
    $args['submitted'] = 1;
    return add_query_arg($args, get_permalink());
}
function alfresco_search_ajax_url($new_page) {
    $args = array();
    foreach ($_GET as $key => $value) {
        if (is_array($value)) {
            continue;
        }
        $args[$key] = sanitize_text_field(wp_unslash($value));
    }
    $args['page'] = $new_page;
    $args['submitted'] = 1;
    $args['action'] = 'alfresco_search_results';
    return add_query_arg($args, admin_url('admin-ajax.php'));
}

/* ---------------------------------------------------------------------------
   Folder Retrieval for Search Interface
--------------------------------------------------------------------------- */
function alfresco_search_get_folders($site) {
    $site = sanitize_text_field($site);
    if (!$site) {
        return array();
    }

    $options = alfresco_search_get_options();
    $cache_key = 'alfresco_search_folders_' . md5($options['alfresco_url'] . '|' . $site);
    $cached = get_transient($cache_key);
    if (false !== $cached) {
        return $cached;
    }

    $alfresco_url = rtrim($options['alfresco_url'], '/');
    $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);

    $url_dl = $alfresco_url . "/api/-default-/public/alfresco/versions/1/sites/{$site}/containers/documentLibrary";
    $response = wp_remote_get($url_dl, array(
        'timeout'   => 20,
        'sslverify' => false,
        'headers'   => array('Authorization' => $auth_header)
    ));
    if (is_wp_error($response)) {
        return array();
    }

    $dl_data = json_decode(wp_remote_retrieve_body($response), true);
    if (!isset($dl_data['entry']['id'])) {
        return array();
    }
    $dl_id = $dl_data['entry']['id'];

    $path_condition = alfresco_search_build_path_condition($site);
    if (!$path_condition) {
        return array();
    }

    $query = $path_condition . ' AND TYPE:"cm:folder"';
    $payload = array(
        'query'  => array('query' => $query),
        'paging' => array('maxItems' => 1000, 'skipCount' => 0)
    );
    $url_search = $alfresco_url . "/api/-default-/public/search/versions/1/search";
    $response = wp_remote_post($url_search, array(
        'body'      => wp_json_encode($payload),
        'headers'   => array(
            'Content-Type'  => 'application/json',
            'Authorization' => $auth_header
        ),
        'timeout'   => 20,
        'sslverify' => false
    ));
    if (is_wp_error($response)) {
        return array();
    }

    $search_data = json_decode(wp_remote_retrieve_body($response), true);
    $entries = isset($search_data['list']['entries']) ? $search_data['list']['entries'] : array();

    $folders = array();
    $special_chars = str_split("_*&%$#@!");
    foreach ($entries as $entry) {
        if (!isset($entry['entry'])) {
            continue;
        }
        $node = $entry['entry'];
        $name = isset($node['name']) ? $node['name'] : '';
        if ($name && in_array(substr($name, 0, 1), $special_chars, true)) {
            continue;
        }
        $folder_id = isset($node['id']) ? $node['id'] : '';
        if (!$folder_id) {
            continue;
        }
        $parent_id = isset($node['parentId']) ? $node['parentId'] : '';
        $folders[$folder_id] = array(
            'id'       => $folder_id,
            'name'     => $name,
            'parentId' => $parent_id,
            'children' => array()
        );
    }

    $tree = array();
    foreach ($folders as $folder) {
        $pid = $folder['parentId'];
        if ($pid === $dl_id || !isset($folders[$pid])) {
            $tree[] = $folder;
        } else {
            $folders[$pid]['children'][] = $folder;
        }
    }

    $flatten = function ($folder_list, $parent_relative = '', $depth = 0) use (&$flatten) {
        $options = array();
        usort($folder_list, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        foreach ($folder_list as $folder) {
            $full_relative = $parent_relative ? $parent_relative . '/' . $folder['name'] : $folder['name'];
            $display = str_repeat('- ', $depth) . $folder['name'];
            $options[] = array('node_id' => $folder['id'], 'value' => $full_relative, 'display' => $display);
            if (!empty($folder['children'])) {
                $options = array_merge($options, $flatten($folder['children'], $full_relative, $depth + 1));
            }
        }
        return $options;
    };

    $flat_tree = $flatten($tree);
    set_transient($cache_key, $flat_tree, HOUR_IN_SECONDS);

    return $flat_tree;
}

function alfresco_search_flush_folder_cache() {
    global $wpdb;
    $like = $wpdb->esc_like('_transient_alfresco_search_folders_') . '%';
    $timeout_like = $wpdb->esc_like('_transient_timeout_alfresco_search_folders_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like));
}

add_action('update_option_alfresco_url', 'alfresco_search_flush_folder_cache');
add_action('update_option_alfresco_username', 'alfresco_search_flush_folder_cache');
add_action('update_option_alfresco_password', 'alfresco_search_flush_folder_cache');
add_action('update_option_alfresco_default_site', 'alfresco_search_flush_folder_cache');

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
        if (!alfresco_search_current_user_can_access()) {
            wp_die(__('You do not have permission to download this file.', 'alfresco-search'), 403);
        }
        $nonce = isset($_GET['_alfresco_token']) ? sanitize_text_field(wp_unslash($_GET['_alfresco_token'])) : '';
        if (!$nonce || !alfresco_search_verify_nonce($nonce)) {
            wp_die(__('Invalid download request. Please refresh the page and try again.', 'alfresco-search'), 403);
        }

        $node_id = sanitize_text_field(wp_unslash($_GET['alfresco_download']));
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
        if (!alfresco_search_current_user_can_access()) {
            wp_die(__('You do not have permission to view this file.', 'alfresco-search'), 403);
        }
        $nonce = isset($_GET['_alfresco_token']) ? sanitize_text_field(wp_unslash($_GET['_alfresco_token'])) : '';
        if (!$nonce || !alfresco_search_verify_nonce($nonce)) {
            wp_die(__('Invalid view request. Please refresh the page and try again.', 'alfresco-search'), 403);
        }

        $node_id = sanitize_text_field(wp_unslash($_GET['alfresco_view']));
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
    $nonce = alfresco_search_generate_nonce();
    if($endpoint == 'download_file'){
        return add_query_arg(
            array(
                'alfresco_download' => $params['node_id'],
                '_alfresco_token'   => $nonce
            ),
            home_url()
        );
    } elseif($endpoint == 'view_file'){
        return add_query_arg(
            array(
                'alfresco_view' => $params['node_id'],
                '_alfresco_token' => $nonce
            ),
            home_url()
        );
    }
    return home_url();
}

/* ---------------------------------------------------------------------------
   AJAX Handler for Node Details (Title & Description)
--------------------------------------------------------------------------- */
function alfresco_search_node_details() {
    if (!alfresco_search_current_user_can_access()) {
        wp_send_json_error(__('You do not have permission to access node details.', 'alfresco-search'), 403);
    }
    $nonce = isset($_GET['nonce']) ? sanitize_text_field(wp_unslash($_GET['nonce'])) : '';
    if (!$nonce || !alfresco_search_verify_nonce($nonce)) {
        wp_send_json_error(__('Invalid request. Please refresh and try again.', 'alfresco-search'), 403);
    }
    if ( empty($_GET['node_id']) ) {
        wp_send_json_error(__('No node_id provided', 'alfresco-search'));
    }
    $node_id = sanitize_text_field(wp_unslash($_GET['node_id']));
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
    if (!alfresco_search_current_user_can_access()) {
        echo alfresco_search_get_results_markup(array(), 0, 0, 1, __('You do not have permission to perform searches.', 'alfresco-search'));
        wp_die();
    }

    $nonce = isset($_GET['_alfresco_search_nonce']) ? sanitize_text_field(wp_unslash($_GET['_alfresco_search_nonce'])) : '';
    if (!$nonce || !alfresco_search_verify_nonce($nonce)) {
        echo alfresco_search_get_results_markup(array(), 0, 0, 1, __('Security check failed. Please refresh and try again.', 'alfresco-search'));
        wp_die();
    }

    $options = alfresco_search_get_options();
    $max_results = intval($options['alfresco_max_results']);
    $site = isset($_GET['site']) ? sanitize_text_field(wp_unslash($_GET['site'])) : $options['alfresco_default_site'];
    $folder = isset($_GET['folder']) ? sanitize_text_field(wp_unslash($_GET['folder'])) : '';
    $search_term = isset($_GET['search_term']) ? sanitize_text_field(wp_unslash($_GET['search_term'])) : '';
    $file_type = isset($_GET['file_type']) ? sanitize_text_field(wp_unslash($_GET['file_type'])) : 'both';
    $page = isset($_GET['page']) ? max(1, intval(wp_unslash($_GET['page']))) : 1;
    $page_size = isset($_GET['page_size']) ? max(1, intval(wp_unslash($_GET['page_size'])) ) : 50;
    $page_size = min($page_size, $max_results);

    $selected_relative = '';
    if ($site && $folder) {
        $folder_options = alfresco_search_get_folders($site);
        foreach ($folder_options as $opt) {
            if ($opt['node_id'] === $folder) {
                $selected_relative = $opt['value'];
                break;
            }
        }
    }

    $debug_enabled = !empty($options['alfresco_debug']);

    $filters = array(
        'search_term'   => $search_term,
        'file_type'     => $file_type
    );
    $conditions = alfresco_search_build_conditions($site, $selected_relative, $filters, 'name_wildcard');
    $query_string = implode(' AND ', $conditions);

    $skipCount = ($page - 1) * $page_size;
    $payload = array(
        'query'  => array(
            'query'    => $query_string,
            'language' => 'afts'
        ),
        'paging' => array('maxItems' => $page_size, 'skipCount' => $skipCount)
    );

    $results = array();
    $total_items = 0;
    $limit_reached = false;
    $error_message = '';
    $alfresco_url_trimmed = rtrim($options['alfresco_url'], '/');
    $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
    $search_url = $alfresco_url_trimmed . "/api/-default-/public/search/versions/1/search";
    $query_preview_url = alfresco_search_build_query_preview_url($search_url, $query_string);

    $response = wp_remote_post($search_url, array(
        'body'      => wp_json_encode($payload),
        'headers'   => array(
            'Content-Type'  => 'application/json',
            'Authorization' => $auth_header
        ),
        'timeout'   => 20,
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
    } else {
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            $status_message = wp_remote_retrieve_response_message($response);
            $error_message = sprintf(
                __('Alfresco search request failed (%1$s %2$s).', 'alfresco-search'),
                $status_code,
                $status_message
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $error_message = __('Invalid response from Alfresco', 'alfresco-search');
            } elseif (isset($data['list'])) {
                foreach ($data['list']['entries'] as $entry) {
                    if (!isset($entry['entry'])) {
                        continue;
                    }
                    $node = $entry['entry'];
                    if (isset($node['nodeType']) && $node['nodeType'] === 'cm:folder') {
                        continue;
                    }
                    $results[] = $entry;
                }
                $raw_total_items = isset($data['list']['pagination']['totalItems']) ? intval($data['list']['pagination']['totalItems']) : 0;
                $limit_reached = ($raw_total_items > $max_results);
                $total_items = $limit_reached ? $max_results : $raw_total_items;
            } elseif (isset($data['error']['errorKey'])) {
                $error_message = sprintf(__('Alfresco error: %s', 'alfresco-search'), $data['error']['errorKey']);
            } else {
                $error_message = __('Invalid response from Alfresco', 'alfresco-search');
            }
        }
    }

    $total_pages = ($page_size && $total_items > 0) ? ceil($total_items / $page_size) : 0;
    echo alfresco_search_get_results_markup(
        $results,
        $total_items,
        $total_pages,
        $page,
        $error_message,
        array(
            'query_url'     => $query_preview_url,
            'query_string'  => $query_string,
            'limit_reached' => $limit_reached,
            'max_results'   => $max_results,
            'show_total'    => true
        )
    );
    wp_die();
}
add_action('wp_ajax_alfresco_search_results', 'alfresco_search_ajax_handler');
add_action('wp_ajax_nopriv_alfresco_search_results', 'alfresco_search_ajax_handler');

/* ---------------------------------------------------------------------------
   Helper: Output Results Markup
--------------------------------------------------------------------------- */
function alfresco_search_get_results_markup($results, $total_items, $total_pages, $page, $error_message = '', $error_details = array()) {
    ob_start();
    $options = alfresco_search_get_options();
    $debug_enabled = alfresco_search_is_debug_enabled($options);
    $limit_reached = !empty($error_details['limit_reached']);
    $max_results = isset($error_details['max_results']) ? intval($error_details['max_results']) : intval($options['alfresco_max_results']);
    $show_total = !empty($error_details['show_total']);
    ?>
    <?php if(!empty($error_message)): ?>
        <div class="mb-4 rounded border border-red-300 bg-red-50 p-4 text-red-700" role="alert">
            <p class="font-semibold"><?php echo esc_html($error_message); ?></p>
            <?php if($debug_enabled && !empty($error_details['query_url'])): ?>
                <p class="mt-2 text-xs break-all text-red-800"><strong><?php _e('Query URL:', 'alfresco-search'); ?></strong> <?php echo esc_html($error_details['query_url']); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if($debug_enabled && wp_doing_ajax()): ?>
        <div class="alfresco-debug-box" role="status">
            <h5 class="alfresco-debug-title"><?php _e('Debug Information', 'alfresco-search'); ?></h5>
            <p class="alfresco-debug-line">
                <strong><?php _e('CMIS Query:', 'alfresco-search'); ?></strong>
                <span class="alfresco-debug-query"><?php echo esc_html(!empty($error_details['query_string']) ? $error_details['query_string'] : __('(empty query)', 'alfresco-search')); ?></span>
            </p>
            <?php if(!empty($error_details['query_url'])): ?>
                <p class="alfresco-debug-line"><a class="alfresco-debug-link" href="<?php echo esc_url($error_details['query_url']); ?>" target="_blank" rel="noopener noreferrer"><?php _e('Open query in Alfresco', 'alfresco-search'); ?></a></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php $has_error = !empty($error_message); ?>
    <?php if($show_total && !$has_error): ?>
        <h4 class="text-2xl font-bold mb-2"><?php printf( __('Search Results (Total of %d)', 'alfresco-search'), intval($total_items) ); ?></h4>
        <?php if($limit_reached && $max_results > 0): ?>
            <p class="mb-4 text-sm text-gray-600"><?php printf( __('Limited to %d results', 'alfresco-search'), intval($max_results) ); ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <?php if($total_items > 0): ?>
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
    <?php elseif(!$has_error && $show_total): ?>
        <p><?php _e('No results found.', 'alfresco-search'); ?></p>
    <?php endif;
    return ob_get_clean();
}

/* ---------------------------------------------------------------------------
   Shortcode for Public Search Interface
--------------------------------------------------------------------------- */
function alfresco_search_shortcode($atts){
    $options = alfresco_search_get_options();
    $debug_enabled = alfresco_search_is_debug_enabled($options);
    $max_results = intval($options['alfresco_max_results']);
    $site = isset($_GET['site']) ? sanitize_text_field(wp_unslash($_GET['site'])) : $options['alfresco_default_site'];
    $folder = isset($_GET['folder']) ? sanitize_text_field(wp_unslash($_GET['folder'])) : '';
    $search_term = isset($_GET['search_term']) ? sanitize_text_field(wp_unslash($_GET['search_term'])) : '';
    $legacy_name = isset($_GET['alfresco_name']) ? sanitize_text_field(wp_unslash($_GET['alfresco_name'])) : '';
    $legacy_title = isset($_GET['title']) ? sanitize_text_field(wp_unslash($_GET['title'])) : '';
    $legacy_description = isset($_GET['description']) ? sanitize_text_field(wp_unslash($_GET['description'])) : '';
    if ($search_term === '') {
        $search_term = $legacy_name !== '' ? $legacy_name : ($legacy_title !== '' ? $legacy_title : $legacy_description);
    }
    $file_type = isset($_GET['file_type']) ? sanitize_text_field(wp_unslash($_GET['file_type'])) : 'both';
    $page = isset($_GET['page']) ? max(1, intval(wp_unslash($_GET['page']))) : 1;
    $page_size = isset($_GET['page_size']) ? max(1, intval(wp_unslash($_GET['page_size']))) : 50;
    $page_size = min($page_size, $max_results);
    $submitted = isset($_GET['submitted']);

    $request_nonce = isset($_GET['_alfresco_search_nonce']) ? sanitize_text_field(wp_unslash($_GET['_alfresco_search_nonce'])) : '';
    $form_nonce = $request_nonce ? $request_nonce : alfresco_search_generate_nonce();

    $folder_options = array();
    $selected_relative = '';
    if($site){
        $folder_options = alfresco_search_get_folders($site);
        if($folder){
            foreach($folder_options as $opt){
                if($opt['node_id'] === $folder){
                    $selected_relative = $opt['value'];
                    break;
                }
            }
        }
    }

    $filters = array(
        'search_term'   => $search_term,
        'alfresco_name' => $legacy_name,
        'title'         => $legacy_title,
        'description'   => $legacy_description,
        'file_type'     => $file_type
    );
    $conditions = alfresco_search_build_conditions($site, $selected_relative, $filters, 'mimetype');
    $query_string = implode(' AND ', $conditions);
    $skipCount = ($page - 1) * $page_size;
    $payload = array(
        'query'  => array(
            'query'    => $query_string,
            'language' => 'afts'
        ),
        'paging' => array('maxItems' => $page_size, 'skipCount' => $skipCount)
    );

    $results = array();
    $total_items = 0;
    $limit_reached = false;
    $error_message = '';
    $query_preview_url = '';
    if($submitted){
        if(!$request_nonce || !alfresco_search_verify_nonce($request_nonce)){
            $error_message = __('Security check failed. Please refresh and try again.', 'alfresco-search');
        } elseif (!alfresco_search_current_user_can_access()) {
            $error_message = __('You do not have permission to perform searches.', 'alfresco-search');
        } else {
            $alfresco_url_trimmed = rtrim($options['alfresco_url'], '/');
            $auth_header = 'Basic ' . base64_encode($options['alfresco_username'] . ':' . $options['alfresco_password']);
            $search_url = $alfresco_url_trimmed . "/api/-default-/public/search/versions/1/search";
            $query_preview_url = alfresco_search_build_query_preview_url($search_url, $query_string);
            $response = wp_remote_post($search_url, array(
                'body'      => wp_json_encode($payload),
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
                $status_code = wp_remote_retrieve_response_code($response);
                if ($status_code < 200 || $status_code >= 300) {
                    $status_message = wp_remote_retrieve_response_message($response);
                    $error_message = sprintf(
                        __('Alfresco search request failed (%1$s %2$s).', 'alfresco-search'),
                        $status_code,
                        $status_message
                    );
                } else {
                    $data = json_decode(wp_remote_retrieve_body($response), true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $error_message = __('Invalid response from Alfresco', 'alfresco-search');
                    } elseif(isset($data['list'])){
                        foreach ($data['list']['entries'] as $entry) {
                            if (!isset($entry['entry'])) {
                                continue;
                            }
                            $node = $entry['entry'];
                            if ( isset($node['nodeType']) && $node['nodeType'] === 'cm:folder' ) {
                                continue;
                            }
                            $results[] = $entry;
                        }
                        $raw_total_items = isset($data['list']['pagination']['totalItems']) ? intval($data['list']['pagination']['totalItems']) : 0;
                        $limit_reached = ($raw_total_items > $max_results);
                        $total_items = $limit_reached ? $max_results : $raw_total_items;
                    } elseif (isset($data['error']['errorKey'])) {
                        $error_message = sprintf(__('Alfresco error: %s', 'alfresco-search'), $data['error']['errorKey']);
                    } else {
                        $error_message = __("Invalid response from Alfresco", "alfresco-search");
                    }
                }
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
        $output .= '<input type="text" name="site" id="site" value="' . esc_attr($site) . '" class="mt-1 block w-full border-gray-300 rounded"></p>';
    } else {
        $output .= '<input type="hidden" name="site" value="' . esc_attr($options['alfresco_default_site']) . '">';
    }
    $output .= '<p><label for="folder" class="block font-medium">' . __('Folder', 'alfresco-search') . ':</label>';
    $output .= '<select name="folder" id="folder" class="mt-1 block w-full border-gray-300 rounded">';
    $output .= '<option value="">' . __('All Folders', 'alfresco-search') . '</option>';
    foreach($folder_options as $opt){
        $output .= '<option value="' . esc_attr($opt['node_id']) . '" ' . selected($folder, $opt['node_id'], false) . '>';
        $output .= esc_html($opt['display']) . '</option>';
    }
    $output .= '</select></p>';
    $output .= '<p><label for="file_type" class="block font-medium">' . __('File Type', 'alfresco-search') . ':</label>';
    $output .= '<select name="file_type" id="file_type" class="mt-1 block w-full border-gray-300 rounded">';
    $output .= '<option value="both" ' . selected($file_type, 'both', false) . '>' . __('Both (PDF & DOC/DOCX)', 'alfresco-search') . '</option>';
    $output .= '<option value="pdf" ' . selected($file_type, 'pdf', false) . '>' . __('PDF', 'alfresco-search') . '</option>';
    $output .= '<option value="doc" ' . selected($file_type, 'doc', false) . '>' . __('DOC/DOCX', 'alfresco-search') . '</option>';
    $output .= '</select></p>';
    $output .= '<p><label for="search_term" class="block font-medium">' . __('Search term', 'alfresco-search') . ':</label>';
    $output .= '<input type="text" name="search_term" id="search_term" value="' . esc_attr($search_term) . '" class="mt-1 block w-full border-gray-300 rounded" placeholder="' . esc_attr__('Search by name, title or description', 'alfresco-search') . '"></p>';
    $output .= '<p><label for="page_size" class="block font-medium">' . __('Results per page', 'alfresco-search') . ':</label>';
    $output .= '<select name="page_size" id="page_size" class="mt-1 block w-full border-gray-300 rounded">';
    foreach(array(30,50,100,200,300) as $opt){
        $output .= '<option value="' . esc_attr($opt) . '" ' . selected($page_size, $opt, false) . '>';
        $output .= esc_html($opt) . '</option>';
    }
    $output .= '</select></p>';
    $output .= '<input type="hidden" name="_alfresco_search_nonce" value="' . esc_attr($form_nonce) . '">';
    $output .= '<input type="hidden" name="submitted" value="1">';
    $output .= '<p><button type="submit" class="w-full bg-blue-500 text-white py-2 rounded">' . __('Search', 'alfresco-search') . '</button></p>';
    $output .= '</form>';

    if($debug_enabled){
        $output .= '<div class="alfresco-debug-box">';
        $output .= '<h5 class="alfresco-debug-title">' . __('Debug Information', 'alfresco-search') . '</h5>';
        $output .= '<p class="alfresco-debug-line"><strong>' . __('CMIS Query:', 'alfresco-search') . '</strong> <span class="alfresco-debug-query">' . esc_html($query_string ? $query_string : __('(empty query)', 'alfresco-search')) . '</span></p>';
        if($query_preview_url){
            $output .= '<p class="alfresco-debug-line"><a class="alfresco-debug-link" href="' . esc_url($query_preview_url) . '" target="_blank" rel="noopener noreferrer">' . __('Open query in Alfresco', 'alfresco-search') . '</a></p>';
        }
        $output .= '</div>';
    }

    $output .= '</div>';

    $output .= '<div class="alfresco-search-results" id="alfresco-search-results">';
    $output .= alfresco_search_get_results_markup(
        $results,
        $total_items,
        $total_pages,
        $page,
        $error_message,
        array(
            'query_url'     => $query_preview_url,
            'query_string'  => $query_string,
            'limit_reached' => $limit_reached,
            'max_results'   => $max_results,
            'show_total'    => $submitted
        )
    );
    $output .= '</div></div>';

    if($debug_enabled){
        $debug_data = array(
            'cmis_query' => $query_string,
            'payload' => $payload,
            'first_3_results' => array_slice($results, 0, 3)
        );
        $output .= '<script>console.log("ALFRESCO DEBUG:", ' . json_encode($debug_data) . ');</script>';
    }
    
    return $output;
}
add_shortcode('alfresco_search', 'alfresco_search_shortcode');
?>
