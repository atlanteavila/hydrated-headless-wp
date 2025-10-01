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

