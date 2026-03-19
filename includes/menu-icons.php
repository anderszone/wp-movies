<?php
if (!defined('ABSPATH')) exit;

/**
 * Add data-icon attribute to menu items
 */
function wp_movie_menu_data_attributes($atts, $item, $args, $depth) {
    $target_menus = array('menu-1');

    if (!empty($args->theme_location) && in_array($args->theme_location, $target_menus, true)) {
        if (!empty($item->title) && is_string($item->title)) {
            $title = trim($item->title);

            // Use WordPress native sanitizer (fixes your bug)
            $data_icon = sanitize_title($title);

            $atts['data-icon'] = esc_attr($data_icon);
        }
    }

    return $atts;
}
add_filter('nav_menu_link_attributes', 'wp_movie_menu_data_attributes', 10, 4);
