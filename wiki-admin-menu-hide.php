<?php
/*
 * Plugin Name: Wiki WordPress Admin Menu Hide
 * Plugin URI: https://github.com/wikiwyrhead/Wiki-Wordpress-Admin-Menu-Hide
 * Description: Hides or shows specific admin menu items and hides admin notices for chosen user roles. Includes a settings page to configure the plugin.
 * Version: 2.0
 * Author: Arnel Go
 * Author URI: https://arnelgo.info/
 * License: GPLv2 or later
 * Text Domain: wp-admin-menu-hide
 */


if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Enqueue the admin script
add_action('admin_enqueue_scripts', 'camh_enqueue_admin_scripts');
function camh_enqueue_admin_scripts()
{
    wp_enqueue_script('camh-admin-js', plugin_dir_url(__FILE__) . 'js/camh-admin.js', array('jquery'), '1.0', true);
    wp_localize_script('camh-admin-js', 'camh_ajax', array('ajaxurl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('camh-nonce')));
}

// Hook to add admin menu
add_action('admin_menu', 'camh_add_admin_menu');

function camh_add_admin_menu()
{
    add_menu_page(
        'Admin Menu Hide Settings',
        'Menu Hide Settings',
        'manage_options',
        'camh-settings',
        'camh_settings_page',
        'dashicons-hidden',
        100
    );
}

// Function to display settings page
function camh_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $roles = get_editable_roles();
?>
    <div class="wrap">
        <h1>Admin Menu Hide Settings</h1>
        <form id="camh-settings-form">
            <h2>User Roles</h2>
            <select id="camh-selected-role" name="camh_selected_role" style="margin-bottom: 10px;">
                <?php
                foreach ($roles as $role_key => $role) {
                    echo '<option value="' . esc_attr($role_key) . '">' . esc_html($role['name']) . '</option>';
                }
                ?>
            </select>

            <h2>Menu Items to Hide/Show</h2>
            <div id="camh-hidden-menus-container">
                <?php camh_hidden_menus_field_callback(); ?>
            </div>

            <h2>Show Mode</h2>
            <div id="camh-show-mode-container">
                <label><input type="radio" name="camh_show_mode" value="hide"> Hide selected menu items</label><br>
                <label><input type="radio" name="camh_show_mode" value="show"> Show only selected menu items</label>
            </div>

            <h2>Hide Admin Notices</h2>
            <div id="camh-hide-admin-notices-container">
                <label><input type="checkbox" name="camh_hide_admin_notices" value="1"> Hide admin notices for selected role</label>
            </div>

            <input type="hidden" name="action" value="camh_save_settings">
            <input type="hidden" name="security" value="<?php echo wp_create_nonce('camh-nonce'); ?>">
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

// Handle AJAX request to get settings for selected role
add_action('wp_ajax_camh_get_role_settings', 'camh_get_role_settings');
function camh_get_role_settings()
{
    check_ajax_referer('camh-nonce', 'security');

    $role = sanitize_text_field($_POST['role']);
    $settings = get_option('camh_settings_' . $role, array(
        'hidden_menus' => array(),
        'show_mode' => 'hide',
        'hide_admin_notices' => ''
    ));

    wp_send_json_success($settings);
}

// Handle AJAX request to save settings
add_action('wp_ajax_camh_save_settings', 'camh_save_settings');
function camh_save_settings()
{
    check_ajax_referer('camh-nonce', 'security');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $selected_role = sanitize_text_field($_POST['camh_selected_role']);
    $hidden_menus = isset($_POST['camh_hidden_menus']) ? array_map('sanitize_text_field', (array)$_POST['camh_hidden_menus']) : array();
    $show_mode = sanitize_text_field($_POST['camh_show_mode']);
    $hide_admin_notices = isset($_POST['camh_hide_admin_notices']) ? '1' : '';

    $settings = array(
        'hidden_menus' => $hidden_menus,
        'show_mode' => $show_mode,
        'hide_admin_notices' => $hide_admin_notices
    );

    update_option('camh_settings_' . $selected_role, $settings);

    wp_send_json_success();
}

// Hook to hide admin menu items and notices
add_action('admin_menu', 'camh_hide_admin_menus', 999);
add_action('admin_notices', 'camh_hide_admin_notices', 1);
add_action('network_admin_notices', 'camh_hide_admin_notices', 1);

function camh_hide_admin_menus()
{
    global $current_user;
    wp_get_current_user();
    $user_roles = $current_user->roles;

    foreach ($user_roles as $role) {
        $settings = get_option('camh_settings_' . $role, array());
        if (!empty($settings)) {
            $show_mode = $settings['show_mode'] ?? 'hide';
            $hidden_menus = $settings['hidden_menus'] ?? array();

            global $menu;
            if ($show_mode === 'hide') {
                foreach ($hidden_menus as $slug) {
                    remove_menu_page($slug);
                }
            } elseif ($show_mode === 'show') {
                foreach ($menu as $item) {
                    if (!in_array($item[2], $hidden_menus)) {
                        remove_menu_page($item[2]);
                    }
                }
            }
        }
    }
}

function camh_hide_admin_notices()
{
    global $current_user;
    wp_get_current_user();
    $user_roles = $current_user->roles;

    foreach ($user_roles as $role) {
        $hide_admin_notices = get_option('camh_settings_' . $role, array())['hide_admin_notices'] ?? '';

        if ($hide_admin_notices) {
            remove_all_actions('admin_notices');
            remove_all_actions('network_admin_notices');
        }
    }
}

function camh_hidden_menus_field_callback()
{
    global $menu;
    $hidden_menus = get_option('camh_settings_' . get_option('camh_selected_role', ''), array())['hidden_menus'] ?? array();
    $hidden_menus = is_array($hidden_menus) ? $hidden_menus : array();

    echo '<input type="text" id="camh-menu-search" placeholder="Search menu items" style="margin-bottom: 10px; width: 100%; padding: 5px;"/>';

    echo '<ul id="camh-menu-items" style="list-style: none; padding-left: 0;">';
    foreach ($menu as $item) {
        if (!empty($item[0])) {
            $menu_slug = esc_attr($item[2]);
            $menu_title = wp_strip_all_tags($item[0]);
            $checked = in_array($menu_slug, $hidden_menus) ? 'checked' : '';
            echo '<li><label><input type="checkbox" name="camh_hidden_menus[]" value="' . $menu_slug . '" ' . $checked . '> ' . $menu_title . '</label></li>';
        }
    }
    echo '</ul>';
}

?>