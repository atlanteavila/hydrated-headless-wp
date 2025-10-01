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


// ─────────────────────────────────────────────────────────────
// DigitalOcean Spaces uploads
// ─────────────────────────────────────────────────────────────
if (!function_exists('hydrated_do_spaces_config')) {
    /**
     * Retrieve configuration for DigitalOcean Spaces uploads.
     */
    function hydrated_do_spaces_config(): array
    {
        return [
            'access_key'   => getenv('DO_ACCESS_KEY') ?: '',
            'access_secret'=> getenv('DO_ACCESS_SECRET') ?: '',
            'region'       => getenv('DO_SPACES_REGION') ?: 'nyc3',
            'space'        => getenv('DO_SPACES_BUCKET') ?: '',
            'endpoint'     => getenv('DO_SPACES_ENDPOINT') ?: '',
            'cdn_host'     => getenv('DO_SPACES_CDN_HOST') ?: '',
            'prefix'       => trim((string) getenv('DO_SPACES_UPLOAD_PREFIX'), " \/"),
        ];
    }
}

if (!function_exists('hydrated_do_spaces_fields_for_post_type')) {
    /**
     * Get the upload fields for a given post type.
     *
     * @param string $post_type
     * @return array<string,array<string,string>>
     */
    function hydrated_do_spaces_fields_for_post_type(string $post_type): array
    {
        $default_fields = [
            'hydrated_do_space_video' => [
                'label'       => __('DigitalOcean Video', 'hydrated'),
                'description' => __('Upload a video that will be stored in DigitalOcean Spaces.', 'hydrated'),
                'accept'      => 'video/*',
            ],
            'hydrated_do_space_image' => [
                'label'       => __('DigitalOcean Image', 'hydrated'),
                'description' => __('Upload an image that will be stored in DigitalOcean Spaces.', 'hydrated'),
                'accept'      => 'image/*',
            ],
        ];

        /**
         * Filter the fields used for DigitalOcean uploads.
         *
         * @param array $default_fields
         * @param string $post_type
         */
        return apply_filters('hydrated_do_spaces_fields', $default_fields, $post_type);
    }
}

if (!function_exists('hydrated_register_do_spaces_meta_boxes')) {
    /**
     * Register the DigitalOcean upload meta box for every UI-enabled post type.
     */
    function hydrated_register_do_spaces_meta_boxes(): void
    {
        $post_types = get_post_types(['show_ui' => true], 'names');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'hydrated_do_spaces_uploads',
                __('DigitalOcean Uploads', 'hydrated'),
                'hydrated_render_do_spaces_meta_box',
                $post_type,
                'normal',
                'default',
                ['post_type' => $post_type]
            );
        }
    }
    add_action('add_meta_boxes', 'hydrated_register_do_spaces_meta_boxes');
}

if (!function_exists('hydrated_render_do_spaces_meta_box')) {
    /**
     * Render the upload meta box fields.
     */
    function hydrated_render_do_spaces_meta_box(WP_Post $post, array $callback_args): void
    {
        $post_type = $callback_args['args']['post_type'] ?? $post->post_type;
        $fields = hydrated_do_spaces_fields_for_post_type($post_type);

        wp_nonce_field('hydrated_do_spaces_meta', 'hydrated_do_spaces_meta_nonce');

        $config = hydrated_do_spaces_config();
        $configured = $config['access_key'] && $config['access_secret'] && $config['space'];

        echo '<p>';
        if ($configured) {
            esc_html_e('Upload media directly to your configured DigitalOcean Space. New files will overwrite previous uploads for the same field.', 'hydrated');
        } else {
            esc_html_e('DigitalOcean credentials are not fully configured. Uploads will fail until the required environment variables are provided.', 'hydrated');
        }
        echo '</p>';

        foreach ($fields as $field_key => $field) {
            $stored = get_post_meta($post->ID, $field_key, true);
            $label = $field['label'] ?? $field_key;
            $description = $field['description'] ?? '';
            $accept = $field['accept'] ?? '';

            $has_file = is_array($stored) && !empty($stored['url']);

            echo '<div class="hydrated-do-space-field" data-field-key="' . esc_attr($field_key) . '" data-field-label="' . esc_attr($label) . '">';
            echo '<div class="hydrated-do-space-field__header">';
            echo '<span class="hydrated-do-space-field__label">' . esc_html($label) . '</span>';
            if ($description) {
                echo '<p class="description">' . esc_html($description) . '</p>';
            }
            echo '</div>';

            echo '<div class="hydrated-do-space-current">';
            echo hydrated_do_spaces_current_file_markup($stored, $label);
            echo '</div>';

            echo '<div class="hydrated-do-space-actions">';
            echo '<button type="button" class="button button-primary hydrated-do-space-upload" data-field-key="' . esc_attr($field_key) . '" data-accept="' . esc_attr($accept) . '">';
            esc_html_e('Upload to DigitalOcean', 'hydrated');
            echo '</button>';

            $remove_classes = 'button hydrated-do-space-remove';
            if (!$has_file) {
                $remove_classes .= ' is-hidden';
            }

            echo '<button type="button" class="' . esc_attr($remove_classes) . '" data-field-key="' . esc_attr($field_key) . '">';
            esc_html_e('Remove file', 'hydrated');
            echo '</button>';

            echo '<span class="hydrated-do-space-status" aria-live="polite"></span>';
            echo '</div>';

            echo '</div>';
        }
    }
}

if (!function_exists('hydrated_do_spaces_current_file_markup')) {
    /**
     * Render the markup for the current uploaded file section.
     *
     * @param mixed $stored
     * @param string $label
     */
    function hydrated_do_spaces_current_file_markup($stored, string $label = ''): string
    {
        if (!is_array($stored) || empty($stored['url'])) {
            return '<p class="description">' . esc_html__('No file uploaded yet.', 'hydrated') . '</p>';
        }

        $url = esc_url($stored['url']);
        $file_name = !empty($stored['key']) ? basename((string) $stored['key']) : ($label ?: __('Uploaded file', 'hydrated'));
        $file_name = esc_html($file_name);
        $attr_url = esc_attr($stored['url']);

        $link = sprintf(
            '<p class="hydrated-do-space-current__link"><a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a></p>',
            $url,
            $file_name
        );

        $copy = sprintf(
            '<div class="hydrated-do-space-copy"><input type="text" class="hydrated-do-space-copy__input" readonly value="%1$s" /><button type="button" class="button hydrated-do-space-copy__button" data-url="%1$s">%2$s</button></div>',
            $attr_url,
            esc_html__('Copy URL', 'hydrated')
        );

        return $link . $copy;
    }
}

if (!function_exists('hydrated_do_spaces_admin_form_enctype')) {
    /**
     * Ensure the post editor form supports file uploads.
     */
    function hydrated_do_spaces_admin_form_enctype(): void
    {
        echo ' enctype="multipart/form-data"';
    }
    add_action('post_edit_form_tag', 'hydrated_do_spaces_admin_form_enctype');
}

if (!function_exists('hydrated_handle_do_spaces_meta_save')) {
    /**
     * Handle saving uploads for the custom fields.
     */
    function hydrated_handle_do_spaces_meta_save(int $post_id): void
    {
        if (!isset($_POST['hydrated_do_spaces_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hydrated_do_spaces_meta_nonce'])), 'hydrated_do_spaces_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $post_type = get_post_type($post_id);
        $fields = hydrated_do_spaces_fields_for_post_type($post_type ?: 'post');

        $errors = [];
        $successes = [];

        foreach ($fields as $field_key => $field) {
            $remove = isset($_POST[$field_key . '_remove']);

            if ($remove) {
                delete_post_meta($post_id, $field_key);
                $successes[] = sprintf(__('Removed file for “%s”.', 'hydrated'), $field['label'] ?? $field_key);
                continue;
            }

            if (empty($_FILES[$field_key]) || !is_array($_FILES[$field_key])) {
                continue;
            }

            $file = $_FILES[$field_key];
            if (!isset($file['error']) || UPLOAD_ERR_NO_FILE === (int) $file['error']) {
                continue;
            }

            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = sprintf(__('Error uploading file for “%s”.', 'hydrated'), $field['label'] ?? $field_key);
                continue;
            }

            $result = hydrated_do_spaces_upload_field($file, $field_key, $field);
            if (is_wp_error($result)) {
                $errors[] = $result->get_error_message();
                continue;
            }

            update_post_meta($post_id, $field_key, $result);
            $successes[] = sprintf(__('Uploaded new file for “%s”.', 'hydrated'), $field['label'] ?? $field_key);
        }

        foreach ($errors as $message) {
            hydrated_do_spaces_add_admin_notice('error', $message);
        }
        foreach ($successes as $message) {
            hydrated_do_spaces_add_admin_notice('success', $message);
        }
    }
    add_action('save_post', 'hydrated_handle_do_spaces_meta_save');
}

if (!function_exists('hydrated_do_spaces_enqueue_admin_assets')) {
    /**
     * Enqueue admin assets for the DigitalOcean upload modal.
     */
    function hydrated_do_spaces_enqueue_admin_assets(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $style_path = get_template_directory() . '/assets/css/hydrated-do-spaces-admin.css';
        $script_path = get_template_directory() . '/assets/js/hydrated-do-spaces-admin.js';
        $version = wp_get_theme()->get('Version');

        $style_version = file_exists($style_path) ? (string) filemtime($style_path) : $version;
        $script_version = file_exists($script_path) ? (string) filemtime($script_path) : $version;

        wp_enqueue_style(
            'hydrated-do-spaces-admin',
            get_template_directory_uri() . '/assets/css/hydrated-do-spaces-admin.css',
            [],
            $style_version
        );

        wp_enqueue_script(
            'hydrated-do-spaces-admin',
            get_template_directory_uri() . '/assets/js/hydrated-do-spaces-admin.js',
            ['jquery'],
            $script_version,
            true
        );

        wp_localize_script('hydrated-do-spaces-admin', 'HydratedDOSpaces', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('hydrated_do_spaces_ajax'),
            'strings' => [
                'modalTitle'   => __('Upload to DigitalOcean Spaces', 'hydrated'),
                'selectFile'   => __('Choose a file to upload. Upload begins immediately after selection.', 'hydrated'),
                'selectButton' => __('Select file', 'hydrated'),
                'uploading'    => __('Uploading…', 'hydrated'),
                'success'      => __('Upload complete.', 'hydrated'),
                'error'        => __('Upload failed. Please try again.', 'hydrated'),
                'close'        => __('Close', 'hydrated'),
                'copy'         => __('Copy URL', 'hydrated'),
                'copied'       => __('URL copied to your clipboard.', 'hydrated'),
                'copyFailed'   => __('Copy failed. Please copy the URL manually.', 'hydrated'),
                'removeConfirm'=> __('Remove the uploaded file?', 'hydrated'),
                'removing'     => __('Removing…', 'hydrated'),
                'removed'      => __('File removed.', 'hydrated'),
                'saveFirst'    => __('Please save the post before uploading.', 'hydrated'),
                'noFile'       => __('No file uploaded yet.', 'hydrated'),
            ],
        ]);
    }
    add_action('admin_enqueue_scripts', 'hydrated_do_spaces_enqueue_admin_assets');
}

if (!function_exists('hydrated_do_spaces_ajax_upload')) {
    /**
     * Handle AJAX uploads to DigitalOcean Spaces.
     */
    function hydrated_do_spaces_ajax_upload(): void
    {
        check_ajax_referer('hydrated_do_spaces_ajax', 'nonce');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $field_key = isset($_POST['field_key']) ? sanitize_key(wp_unslash((string) $_POST['field_key'])) : '';

        if (!$post_id || !$field_key) {
            wp_send_json_error(['message' => __('Invalid request.', 'hydrated')]);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('You are not allowed to upload files for this post.', 'hydrated')]);
        }

        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            wp_send_json_error(['message' => __('No file was provided.', 'hydrated')]);
        }

        $post_type = get_post_type($post_id) ?: 'post';
        $fields = hydrated_do_spaces_fields_for_post_type($post_type);

        if (!isset($fields[$field_key])) {
            wp_send_json_error(['message' => __('Invalid field.', 'hydrated')]);
        }

        $result = hydrated_do_spaces_upload_field($_FILES['file'], $field_key, $fields[$field_key]);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        update_post_meta($post_id, $field_key, $result);

        $file_name = !empty($result['key']) ? basename((string) $result['key']) : ($fields[$field_key]['label'] ?? $field_key);

        wp_send_json_success([
            'url'      => $result['url'],
            'key'      => $result['key'],
            'fileName' => $file_name,
            'message'  => sprintf(__('Uploaded new file for “%s”.', 'hydrated'), $fields[$field_key]['label'] ?? $field_key),
        ]);
    }
    add_action('wp_ajax_hydrated_do_spaces_upload', 'hydrated_do_spaces_ajax_upload');
}

if (!function_exists('hydrated_do_spaces_ajax_remove')) {
    /**
     * Handle AJAX removal of uploaded files from post meta.
     */
    function hydrated_do_spaces_ajax_remove(): void
    {
        check_ajax_referer('hydrated_do_spaces_ajax', 'nonce');

        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $field_key = isset($_POST['field_key']) ? sanitize_key(wp_unslash((string) $_POST['field_key'])) : '';

        if (!$post_id || !$field_key) {
            wp_send_json_error(['message' => __('Invalid request.', 'hydrated')]);
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => __('You are not allowed to modify files for this post.', 'hydrated')]);
        }

        delete_post_meta($post_id, $field_key);

        wp_send_json_success([
            'message' => __('The file has been removed.', 'hydrated'),
        ]);
    }
    add_action('wp_ajax_hydrated_do_spaces_remove', 'hydrated_do_spaces_ajax_remove');
}

if (!function_exists('hydrated_do_spaces_add_admin_notice')) {
    /**
     * Queue an admin notice for the current user.
     */
    function hydrated_do_spaces_add_admin_notice(string $type, string $message): void
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $key = 'hydrated_do_spaces_notices_' . $user_id;
        $notices = get_transient($key);
        if (!is_array($notices)) {
            $notices = [];
        }
        $notices[] = [
            'type'    => $type,
            'message' => $message,
        ];
        set_transient($key, $notices, 60);
    }
}

if (!function_exists('hydrated_do_spaces_render_admin_notices')) {
    /**
     * Display queued admin notices.
     */
    function hydrated_do_spaces_render_admin_notices(): void
    {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $key = 'hydrated_do_spaces_notices_' . $user_id;
        $notices = get_transient($key);
        if (!is_array($notices) || empty($notices)) {
            return;
        }

        delete_transient($key);

        foreach ($notices as $notice) {
            $type = $notice['type'] ?? 'info';
            $message = $notice['message'] ?? '';
            if (!$message) {
                continue;
            }
            printf('<div class="notice notice-%1$s"><p>%2$s</p></div>', esc_attr($type), esc_html($message));
        }
    }
    add_action('admin_notices', 'hydrated_do_spaces_render_admin_notices');
}

if (!function_exists('hydrated_do_spaces_upload_field')) {
    /**
     * Upload a field file to DigitalOcean Spaces.
     *
     * @param array $file
     * @param string $field_key
     * @param array $field
     * @return array|WP_Error
     */
    function hydrated_do_spaces_upload_field(array $file, string $field_key, array $field)
    {
        $config = hydrated_do_spaces_config();

        if (!$config['access_key'] || !$config['access_secret'] || !$config['space']) {
            return new WP_Error('missing_credentials', __('DigitalOcean Spaces credentials are not fully configured.', 'hydrated'));
        }

        $tmp_name = $file['tmp_name'] ?? '';
        if (!$tmp_name || !file_exists($tmp_name)) {
            return new WP_Error('invalid_upload', __('The uploaded file could not be found on the server.', 'hydrated'));
        }

        $original_name = isset($file['name']) ? sanitize_file_name((string) $file['name']) : $field_key;
        $extension = pathinfo($original_name, PATHINFO_EXTENSION);
        $safe_field = sanitize_key($field_key);
        $unique_name = $safe_field . '-' . uniqid('', true);
        if ($extension) {
            $unique_name .= '.' . strtolower($extension);
        }

        $prefix = $config['prefix'] ? $config['prefix'] . '/' : '';
        $key = $prefix . ltrim($unique_name, '/');

        $content_type = !empty($file['type']) ? $file['type'] : hydrated_do_spaces_detect_mime_type($tmp_name, $extension);

        $upload = hydrated_do_spaces_upload_file($tmp_name, $key, $content_type, (int) ($file['size'] ?? 0));
        if (is_wp_error($upload)) {
            return $upload;
        }

        return [
            'url'          => $upload['url'],
            'key'          => $key,
            'bucket'       => $config['space'],
            'content_type' => $content_type,
            'size'         => (int) ($file['size'] ?? 0),
            'uploaded_at'  => current_time('mysql'),
        ];
    }
}

if (!function_exists('hydrated_do_spaces_detect_mime_type')) {
    /**
     * Attempt to detect a file's MIME type.
     */
    function hydrated_do_spaces_detect_mime_type(string $path, string $extension = ''): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if ($mime) {
                return $mime;
            }
        }

        if ($extension) {
            $mime = wp_check_filetype('file.' . $extension);
            if (!empty($mime['type'])) {
                return $mime['type'];
            }
        }

        return 'application/octet-stream';
    }
}

if (!function_exists('hydrated_do_spaces_upload_file')) {
    /**
     * Upload a file to DigitalOcean Spaces using the S3-compatible API.
     *
     * @return array{url:string,key:string}|WP_Error
     */
    function hydrated_do_spaces_upload_file(string $path, string $key, string $content_type, int $size)
    {
        $config = hydrated_do_spaces_config();
        $region = $config['region'] ?: 'nyc3';
        $space = $config['space'];
        $endpoint = $config['endpoint'] ?: $region . '.digitaloceanspaces.com';
        $endpoint = trim($endpoint);

        if ('' === $endpoint) {
            $endpoint = $region . '.digitaloceanspaces.com';
        }

        if (false !== stripos($endpoint, '://')) {
            $parsed = wp_parse_url($endpoint);
            if ($parsed && !empty($parsed['host'])) {
                $endpoint = $parsed['host'];
                if (!empty($parsed['port'])) {
                    $endpoint .= ':' . $parsed['port'];
                }
            } else {
                $endpoint = preg_replace('#^[a-z0-9.+-]+://#i', '', $endpoint);
            }
        }

        $endpoint = preg_replace('#/+$#', '', $endpoint);

        if (false !== strpos($endpoint, '/')) {
            $parts = explode('/', $endpoint, 2);
            $endpoint = $parts[0];
        }

        $endpoint = ltrim($endpoint, '.');

        if ('' === $endpoint) {
            $endpoint = $region . '.digitaloceanspaces.com';
        }

        if ($space && 0 === strpos($endpoint, $space . '.')) {
            $host = $endpoint;
        } else {
            $host = $space . '.' . $endpoint;
        }

        $url = 'https://' . $host . '/' . ltrim($key, '/');

        $body = file_get_contents($path);
        if (false === $body) {
            return new WP_Error('file_read_error', __('Could not read the uploaded file from disk.', 'hydrated'));
        }

        $payload_hash = hash('sha256', $body);
        $amzdate = gmdate('Ymd\THis\Z');
        $date_stamp = gmdate('Ymd');
        $canonical_uri = hydrated_do_spaces_canonical_uri($key);
        $canonical_headers = sprintf(
            "content-type:%s\nhost:%s\nx-amz-acl:public-read\nx-amz-content-sha256:%s\nx-amz-date:%s\n",
            $content_type,
            $host,
            $payload_hash,
            $amzdate
        );
        $signed_headers = 'content-type;host;x-amz-acl;x-amz-content-sha256;x-amz-date';
        $canonical_request = sprintf(
            "PUT\n%s\n\n%s\n%s\n%s",
            $canonical_uri,
            $canonical_headers,
            $signed_headers,
            $payload_hash
        );

        $credential_scope = sprintf('%s/%s/%s/%s', $date_stamp, $region, 's3', 'aws4_request');
        $string_to_sign = sprintf(
            "AWS4-HMAC-SHA256\n%s\n%s\n%s",
            $amzdate,
            $credential_scope,
            hash('sha256', $canonical_request)
        );

        $signing_key = hydrated_do_spaces_signing_key($config['access_secret'], $date_stamp, $region, 's3');
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);

        $authorization = sprintf(
            'AWS4-HMAC-SHA256 Credential=%1$s/%2$s, SignedHeaders=%3$s, Signature=%4$s',
            $config['access_key'],
            $credential_scope,
            $signed_headers,
            $signature
        );

        $headers = [
            'Authorization'         => $authorization,
            'Content-Type'          => $content_type,
            'Content-Length'        => (string) $size,
            'x-amz-acl'             => 'public-read',
            'x-amz-content-sha256'  => $payload_hash,
            'x-amz-date'            => $amzdate,
        ];

        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $error_body = wp_remote_retrieve_body($response);
            return new WP_Error(
                'spaces_upload_failed',
                sprintf(
                    __('DigitalOcean Spaces upload failed with status %1$d. %2$s', 'hydrated'),
                    $status,
                    $error_body ? wp_strip_all_tags($error_body) : ''
                )
            );
        }

        $public_base = $config['cdn_host'] ? rtrim($config['cdn_host'], '\\/') : 'https://' . $host;
        $public_url = $public_base . '/' . ltrim($key, '/');

        return [
            'url' => $public_url,
            'key' => $key,
        ];
    }
}

if (!function_exists('hydrated_do_spaces_canonical_uri')) {
    /**
     * Build the canonical URI for signing requests.
     */
    function hydrated_do_spaces_canonical_uri(string $key): string
    {
        $segments = explode('/', ltrim($key, '/'));
        $encoded = array_map(static function ($segment) {
            return str_replace('%2F', '/', rawurlencode($segment));
        }, $segments);

        return '/' . implode('/', $encoded);
    }
}

if (!function_exists('hydrated_do_spaces_signing_key')) {
    /**
     * Create the AWS v4 signing key for Spaces requests.
     */
    function hydrated_do_spaces_signing_key(string $secret, string $date, string $region, string $service)
    {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}
