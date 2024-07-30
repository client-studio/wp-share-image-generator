<?php
/**
 * Plugin Name: Auto Share Image
 * Description: Automatically generates share images for posts and pages using their thumbnails and titles.
 * Version: 1.0
 * Author: Client.Studio
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Register settings
function asi_register_settings() {
    register_setting('asi_settings_group', 'asi_logo');
    add_settings_section('asi_main_section', 'Main Settings', 'asi_main_section_callback', 'asi_settings_page');
    add_settings_field('asi_logo', 'Upload Logo', 'asi_logo_callback', 'asi_settings_page', 'asi_main_section');
}
add_action('admin_init', 'asi_register_settings');

// Settings section callback
function asi_main_section_callback() {
    echo '<p>Main settings for Auto Share Image plugin.</p>';
}

// Logo upload callback
function asi_logo_callback() {
    $logo = get_option('asi_logo');
    echo '<input type="text" id="asi_logo" name="asi_logo" value="' . esc_attr($logo) . '" />';
    echo '<input type="button" id="upload_logo_button" class="button" value="Upload Logo" />';
    echo '<p class="description">Upload a logo to be used on the share images.</p>';
}

// Add settings page
function asi_settings_page() {
    ?>
    <div class="wrap">
        <h1>Auto Share Image Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('asi_settings_group');
            do_settings_sections('asi_settings_page');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
function asi_add_settings_page() {
    add_options_page('Auto Share Image Settings', 'Auto Share Image', 'manage_options', 'asi-settings', 'asi_settings_page');
}
add_action('admin_menu', 'asi_add_settings_page');

// Enqueue media uploader script
function asi_enqueue_media_uploader($hook) {
    if ('settings_page_asi-settings' !== $hook) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_script('asi-media-uploader', plugin_dir_url(__FILE__) . 'media-uploader.js', array('jquery'), null, false);
}
add_action('admin_enqueue_scripts', 'asi_enqueue_media_uploader');

// Function to create an image resource from a URL
function asi_create_image_from_url($url) {
    $image_info = getimagesize($url);
    $mime_type = $image_info['mime'];

    switch ($mime_type) {
        case 'image/jpeg':
            return imagecreatefromjpeg($url);
        case 'image/png':
            return imagecreatefrompng($url);
        case 'image/webp':
            return imagecreatefromwebp($url);
        case 'image/avif':
            return imagecreatefromavif($url);        
        default:
            return false;
    }
}

// Function to generate the share image
function asi_generate_share_image($post_id) {
    $post = get_post($post_id);
    if (!$post || !$post->post_title) {
        return false;
    }

    $title = $post->post_title;
    $thumbnail_id = get_post_thumbnail_id($post_id);
    if (!$thumbnail_id) {
        return false;
    }
    $thumbnail_url = wp_get_attachment_image_src($thumbnail_id, 'full')[0];
    if (!$thumbnail_url) {
        return false;
    }

    $logo_url = get_option('asi_logo');

    // Create the image
    $image = imagecreatetruecolor(1200, 630);

    // Set background color
    $background_color = imagecolorallocate($image, 255, 255, 255); // white
    imagefilledrectangle($image, 0, 0, 1200, 630, $background_color);

    // Load thumbnail
    $thumbnail = asi_create_image_from_url($thumbnail_url);
    if (!$thumbnail) {
        return false;
    }
    imagecopyresampled($image, $thumbnail, 0, 0, 0, 0, 1200, 630, imagesx($thumbnail), imagesy($thumbnail));

     // Load logo if set and resize to 100x100
     if ($logo_url) {
        $logo = asi_create_image_from_url($logo_url);
        if ($logo) {
            $logo_resized = imagecreatetruecolor(100, 100);
            imagecopyresampled($logo_resized, $logo, 0, 0, 0, 0, 100, 100, imagesx($logo), imagesy($logo));
            imagecopy($image, $logo_resized, 20, 20, 0, 0, 100, 100); // Top left
            imagedestroy($logo);
            imagedestroy($logo_resized);
        }
    }

    // Set title text
    $text_color = imagecolorallocate($image, 255, 255, 255); // white text
    $font_path = __DIR__ . '/fonts/Inter-Medium.ttf'; // Path to the Inter font file
    imagettftext($image, 48, 0, 50, 600, $text_color, $font_path, $title); // Bottom left

    // Save the image
    $upload_dir = wp_upload_dir();
    $output_path = $upload_dir['path'] . '/share_image_' . $post_id . '.jpg';
    imagejpeg($image, $output_path);

    // Clean up
    imagedestroy($image);
    imagedestroy($thumbnail);

    return $upload_dir['url'] . '/share_image_' . $post_id . '.jpg';
}

// Function to set Open Graph image
function asi_set_opengraph_image($image) {
    if (is_singular('post') || is_page()) {
        global $post;
        $share_image = asi_generate_share_image($post->ID);
        if ($share_image) {
            $image = $share_image;
        }
    }
    return $image;
}
add_filter('wpseo_opengraph_image', 'asi_set_opengraph_image');
add_filter('wpseo_twitter_image', 'asi_set_opengraph_image');

// Function to generate image on save post
function asi_generate_image_on_save($post_id) {
    if (get_post_type($post_id) == 'post' || get_post_type($post_id) == 'page') {
        asi_generate_share_image($post_id);
    }
}
add_action('save_post', 'asi_generate_image_on_save');
?>
