<?php

// Enable featured images and menus
add_theme_support('post-thumbnails');
add_theme_support('menus');

// Register a sample menu
function hydrated_register_menus()
{
    register_nav_menu('main-header', __('Main Header Menu'));
}
add_action('init', 'hydrated_register_menus');

// Allow the REST API to work with all necessary data
add_filter('rest_endpoints', function ($endpoints) {
    return $endpoints;
});

// Register required plugin dependencies for the theme.
add_action('after_setup_theme', function () {
    if (function_exists('wp_register_plugin_theme_dependencies')) {
        wp_register_plugin_theme_dependencies([
            'advanced-custom-fields',
            'wp-rest-api-v2-menus',
            'wordpress-seo',
        ]);
    }
});

/**
 * Retrieve the metadata for the plugins this theme depends on.
 */
function hydrated_required_plugins()
{
    return [
        [
            'name' => 'Advanced Custom Fields',
            'slug' => 'advanced-custom-fields',
            'file' => 'advanced-custom-fields/acf.php',
        ],
        [
            'name' => 'WP-REST-API V2 Menus',
            'slug' => 'wp-rest-api-v2-menus',
            'file' => 'wp-rest-api-v2-menus/wp-rest-api-v2-menus.php',
        ],
        [
            'name' => 'Yoast SEO',
            'slug' => 'wordpress-seo',
            'file' => 'wordpress-seo/wp-seo.php',
        ],
    ];
}

/**
 * Determine the installation and activation status of the required plugins.
 */
function hydrated_required_plugin_statuses()
{
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $statuses = [];

    foreach (hydrated_required_plugins() as $plugin) {
        $plugin_file = $plugin['file'];
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_file;

        if (is_plugin_active($plugin_file)) {
            $status = 'active';
        } elseif (file_exists($plugin_path)) {
            $status = 'inactive';
        } else {
            $status = 'not-installed';
        }

        $statuses[] = [
            'name' => $plugin['name'],
            'slug' => $plugin['slug'],
            'file' => $plugin_file,
            'status' => $status,
        ];
    }

    return $statuses;
}

/**
 * Display a notice that guides administrators to install and activate required plugins.
 */
function hydrated_required_plugins_admin_notice()
{
    if (!is_admin() || !current_user_can('install_plugins')) {
        return;
    }

    $plugins = hydrated_required_plugin_statuses();

    $needs_action = array_filter($plugins, function ($plugin) {
        return 'active' !== $plugin['status'];
    });

    if (empty($needs_action)) {
        return;
    }

    echo '<div class="notice notice-warning hydrated-required-plugins-notice">';
    echo '<p><strong>' . esc_html__('Hydrated Headless Theme requires additional plugins.', 'hydrated-headless') . '</strong></p>';
    echo '<ul>';

    foreach ($needs_action as $plugin) {
        $action_label = '';
        $action_url = '';

        if ('not-installed' === $plugin['status']) {
            $action_label = esc_html__('Install', 'hydrated-headless');
            $action_url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=' . $plugin['slug']),
                'install-plugin_' . $plugin['slug']
            );
        } elseif ('inactive' === $plugin['status']) {
            $action_label = esc_html__('Activate', 'hydrated-headless');
            $action_url = wp_nonce_url(
                self_admin_url('plugins.php?action=activate&plugin=' . urlencode($plugin['file'])),
                'activate-plugin_' . $plugin['file']
            );
        }

        $status_label = 'inactive' === $plugin['status']
            ? esc_html__('Installed but inactive', 'hydrated-headless')
            : esc_html__('Not installed', 'hydrated-headless');

        echo '<li>';
        echo '<strong>' . esc_html($plugin['name']) . '</strong>';
        echo ' — ' . esc_html($status_label);

        if ($action_label && $action_url) {
            echo ' <a class="button button-primary" href="' . esc_url($action_url) . '">';
            echo esc_html(sprintf(esc_html__('%s now', 'hydrated-headless'), $action_label));
            echo '</a>';
        }

        echo '</li>';
    }

    echo '</ul>';
    echo '</div>';
}
add_action('admin_notices', 'hydrated_required_plugins_admin_notice');

// ─────────────────────────────────────────────────────────────
// Block editor styles + patterns
// ─────────────────────────────────────────────────────────────
// Register a Pattern Category for our layouts
add_action('init', function () {
    register_block_pattern_category(
        'hydrated-layouts',
        ['label' => __('Hydrated Layouts', 'hydrated')]
    );
});

// Optional: add a handy “Bento Grid” style to Group blocks (so editors
// can turn any Group into a bento grid if they want)
add_action('init', function () {
    register_block_style('core/group', [
        'name' => 'bento-grid',
        'label' => __('Bento Grid', 'hydrated'),
    ]);
});

