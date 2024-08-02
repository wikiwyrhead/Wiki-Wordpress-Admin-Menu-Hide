<?php
/*
Plugin Name: Wiki Wordpress Admin Menu Hide
Description: Hides or shows specific admin menu items and hides admin notices for chosen user roles. Includes a settings page to configure the plugin.
Version: 1.5
Author: Arnel Go
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Hook to add admin menu
add_action( 'admin_menu', 'camh_add_admin_menu' );

function camh_add_admin_menu() {
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
function camh_settings_page() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1>Admin Menu Hide Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields( 'camh_settings_group' );
            do_settings_sections( 'camh-settings' );
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Hook to initialize plugin settings
add_action( 'admin_init', 'camh_settings_init' );

function camh_settings_init() {
    register_setting( 'camh_settings_group', 'camh_hidden_menus', 'camh_sanitize_hidden_menus' );
    register_setting( 'camh_settings_group', 'camh_selected_role', 'sanitize_text_field' );
    register_setting( 'camh_settings_group', 'camh_show_mode', 'sanitize_text_field' );
    register_setting( 'camh_settings_group', 'camh_hide_admin_notices', 'camh_sanitize_checkbox' );

    add_settings_section(
        'camh_settings_section',
        'Menu Items to Hide/Show',
        'camh_settings_section_callback',
        'camh-settings'
    );

    add_settings_field(
        'camh_hidden_menus_field',
        'Menu Items',
        'camh_hidden_menus_field_callback',
        'camh-settings',
        'camh_settings_section'
    );

    add_settings_field(
        'camh_selected_role_field',
        'User Role',
        'camh_selected_role_field_callback',
        'camh-settings',
        'camh_settings_section'
    );

    add_settings_field(
        'camh_show_mode_field',
        'Show Mode',
        'camh_show_mode_field_callback',
        'camh-settings',
        'camh_settings_section'
    );

    add_settings_field(
        'camh_hide_admin_notices_field',
        'Hide Admin Notices',
        'camh_hide_admin_notices_field_callback',
        'camh-settings',
        'camh_settings_section'
    );
}

function camh_settings_section_callback() {
    echo 'Select the user role and the menu items you want to hide or show for that role.';
}

function camh_hidden_menus_field_callback() {
    global $menu;
    $hidden_menus = get_option( 'camh_hidden_menus', array() );
    $hidden_menus = is_array( $hidden_menus ) ? $hidden_menus : array();

    echo '<input type="text" id="camh-menu-search" placeholder="Search menu items" style="margin-bottom: 10px; width: 100%; padding: 5px;"/>';

    echo '<ul id="camh-menu-list" style="list-style: none; padding-left: 0;">';
    foreach ( $menu as $item ) {
        if ( ! empty( $item[0] ) ) {
            $label = strip_tags($item[0]);
            $checked = in_array( $item[2], $hidden_menus ) ? 'checked' : '';
            echo '<li><label><input type="checkbox" name="camh_hidden_menus[]" value="' . esc_attr( $item[2] ) . '" ' . $checked . '> ' . esc_html( $label ) . '</label></li>';
        }
    }
    echo '</ul>';

    echo '<script>
    document.getElementById("camh-menu-search").addEventListener("keyup", function() {
        var searchValue = this.value.toLowerCase();
        var items = document.querySelectorAll("#camh-menu-list li");
        items.forEach(function(item) {
            var text = item.textContent.toLowerCase();
            item.style.display = text.includes(searchValue) ? "" : "none";
        });
    });
    </script>';
}

function camh_selected_role_field_callback() {
    $selected_role = get_option( 'camh_selected_role', '' );
    $roles = get_editable_roles();

    echo '<select name="camh_selected_role" style="margin-bottom: 10px;">';
    foreach ( $roles as $role_key => $role ) {
        $selected = ( $selected_role === $role_key ) ? 'selected' : '';
        echo '<option value="' . esc_attr( $role_key ) . '" ' . $selected . '>' . esc_html( $role['name'] ) . '</option>';
    }
    echo '</select>';
}

function camh_show_mode_field_callback() {
    $show_mode = get_option( 'camh_show_mode', 'hide' );
    ?>
    <label><input type="radio" name="camh_show_mode" value="hide" <?php checked( $show_mode, 'hide' ); ?>> Hide selected menu items</label><br>
    <label><input type="radio" name="camh_show_mode" value="show" <?php checked( $show_mode, 'show' ); ?>> Show only selected menu items</label>
    <?php
}

function camh_hide_admin_notices_field_callback() {
    $hide_admin_notices = get_option( 'camh_hide_admin_notices', '' );
    ?>
    <label><input type="checkbox" name="camh_hide_admin_notices" value="1" <?php checked( $hide_admin_notices, 1 ); ?>> Hide admin notices for selected role</label>
    <?php
}

function camh_sanitize_hidden_menus( $input ) {
    if ( is_array( $input ) ) {
        return array_map( 'sanitize_text_field', $input );
    }
    return array();
}

function camh_sanitize_checkbox( $input ) {
    return $input == '1' ? '1' : '';
}

// Hook to hide admin menu items and notices
add_action( 'admin_menu', 'camh_hide_admin_menus', 999 );
add_action( 'admin_notices', 'camh_hide_admin_notices', 1 );
add_action( 'network_admin_notices', 'camh_hide_admin_notices', 1 );

function camh_hide_admin_menus() {
    $selected_role = get_option( 'camh_selected_role', '' );
    $show_mode = get_option( 'camh_show_mode', 'hide' );
    $hidden_menus = get_option( 'camh_hidden_menus', array() );
    $hidden_menus = is_array( $hidden_menus ) ? $hidden_menus : array();

    if ( current_user_can( $selected_role ) ) {
        global $menu;
        if ( $show_mode === 'hide' ) {
            foreach ( $hidden_menus as $slug ) {
                remove_menu_page( $slug );
            }
        } elseif ( $show_mode === 'show' ) {
            foreach ( $menu as $item ) {
                if ( ! in_array( $item[2], $hidden_menus ) ) {
                    remove_menu_page( $item[2] );
                }
            }
        }
    }
}

function camh_hide_admin_notices() {
    $selected_role = get_option( 'camh_selected_role', '' );
    $hide_admin_notices = get_option( 'camh_hide_admin_notices', '' );

    if ( $hide_admin_notices && current_user_can( $selected_role ) ) {
        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'network_admin_notices' );
    }
}
?>
