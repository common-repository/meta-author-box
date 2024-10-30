<?php

/*
Plugin Name: Meta Author Box
Description: Customize the author box with a custom name, bio, and image.
Version: 1.0
Author: Shaugat
License: GPL-2.0+
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


// Add image size for custom author image
function meta_add_image_sizes() {
    add_image_size('custom_author_image', 160, 160, true);
}
add_action('init', 'meta_add_image_sizes');

// Add meta box in the post editor screen
function meta_author_box_meta_box() {
    add_meta_box(
        'meta_author_box',
        'Meta Author Box',
        'meta_author_box_html',
        'post',
        'side'
    );
}
add_action('add_meta_boxes', 'meta_author_box_meta_box');

// Display the meta box content
function meta_author_box_html($post) {
    $custom_author_name = get_post_meta($post->ID, '_custom_author_name', true);
    $custom_author_bio = get_post_meta($post->ID, '_custom_author_bio', true);
    $custom_author_image = get_post_meta($post->ID, '_custom_author_image', true);

    // Security field
    wp_nonce_field('meta_author_box_nonce', 'meta_author_box_nonce');

    echo '<label for="custom_author_name">Custom Author Name:</label>';
    echo '<input type="text" id="custom_author_name" name="custom_author_name" value="' . esc_attr($custom_author_name) . '" style="width: 100%;" />';

    echo '<label for="custom_author_bio">Custom Author Bio:</label>';
    echo '<textarea id="custom_author_bio" name="custom_author_bio" style="width: 100%;" rows="4">' . esc_textarea($custom_author_bio) . '</textarea>';

    echo '<label for="custom_author_image">Custom Author Image:</label>';
    echo '<input type="text" id="custom_author_image" name="custom_author_image" value="' . esc_attr($custom_author_image) . '" style="width: 100%;" />';
    echo '<button type="button" class="button button-primary" onclick="uploadAuthorImage()">Upload Image</button>';
    echo '<script>
        function uploadAuthorImage() {
            var mediaUploader;
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            mediaUploader = wp.media.frames.file_frame = wp.media({
                title: "Choose Image",
                button: {
                    text: "Choose Image"
                },
                multiple: false
            });
            mediaUploader.on("select", function () {
                var attachment = mediaUploader.state().get("selection").first().toJSON();
                document.getElementById("custom_author_image").value = attachment.url;
            });
            mediaUploader.open();
        }
    </script>';
}

// Save the meta box content
function meta_author_box_save($post_id) {
    // Check if nonce is set
    if (!isset($_POST['meta_author_box_nonce'])) {
        return;
    }

    // Verify nonce
    if ( ! isset( $_POST['meta_author_box_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['meta_author_box_nonce'] ) ), 'meta_author_box_nonce' ) ) {
        return;
    }

    // Check if the user has permissions to save data
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save custom fields
    update_post_meta($post_id, '_custom_author_name', sanitize_text_field($_POST['custom_author_name']));
    update_post_meta($post_id, '_custom_author_bio', sanitize_textarea_field($_POST['custom_author_bio']));
    update_post_meta($post_id, '_custom_author_image', esc_url_raw($_POST['custom_author_image']));
}
add_action('save_post', 'meta_author_box_save');

// Display custom author box after post content
function meta_display_author_box_after_content($content) {
    global $post;

    if (is_single() && isset($post->post_author)) {
        $custom_author_name = get_post_meta($post->ID, '_custom_author_name', true);
        $custom_author_bio = get_post_meta($post->ID, '_custom_author_bio', true);
        $custom_author_image = get_post_meta($post->ID, '_custom_author_image', true);

        // Check if all custom fields are empty
        if (empty($custom_author_name) && empty($custom_author_bio) && empty($custom_author_image)) {
            return $content; // If all fields are empty, return the original content
        }

        $image_url = $custom_author_image;
        $image = wp_get_attachment_image_src($custom_author_image, 'custom_author_image');
        $image_url = $image ? $image[0] : $custom_author_image;


        $author_box = '<div class="entry-author entry-author-style-normal" style="border: solid 1px black; border-radius: 7px; background-color: #FFFEF4; color: #0A0D02; font-family: inherit; box-sizing: inherit; padding: 25px; width: 100%;">';
        $author_box .= '<div class="entry-author-profile author-profile vcard">';
        
        if (!empty($custom_author_image)) {
            $author_box .= '<div class="entry-author-avatar" style="overflow: hidden; max-width: 100%;">'; // Add max-width and overflow styles
            $author_box .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($custom_author_name) . '" style="width: 80px; height: 80px; border-radius: 5px;">';
            $author_box .= '</div>';
        }

        if (!empty($custom_author_name)) {
            $author_box .= '<b class="entry-author-name author-name fn">' . esc_html($custom_author_name) . '</b>';
        }

        if (!empty($custom_author_bio)) {
            $author_box .= '<div class="entry-author-description author-bio" style="font-size: 18px; display: block; margin-top: 10px;">';
            $author_box .= '<p>' . esc_html($custom_author_bio) . '</p>';
            $author_box .= '</div>';
        }

        $author_box .= '<div class="entry-author-follow author-follow">';
        // Add your social links or other follow buttons here if needed
        $author_box .= '</div>';
        $author_box .= '</div>';
        $author_box .= '</div>';

        $content .= $author_box;
    }

    return $content;
}
add_filter('the_content', 'meta_display_author_box_after_content');


// Filter to handle image upload and resize
function meta_handle_uploaded_image($file) {
    // Check if it's an image file
    $image_file_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (in_array($file['type'], $image_file_types)) {
        // Get the path to the uploaded file
        $uploaded_file = $file['file'];

        // Resize the image
        $editor = wp_get_image_editor($uploaded_file);
        if (!is_wp_error($editor)) {
            $editor->resize(160, 160, true);
            $resized_file = $editor->save();

            // Update the file path in the $file array
            $file['file'] = $resized_file['path'];
        }
    }

    return $file;
}
add_filter('wp_handle_upload', 'meta_handle_uploaded_image');
add_filter('wp_generate_attachment_metadata', 'meta_handle_uploaded_image', 10, 2);
add_filter('wp_handle_upload_prefilter', 'meta_handle_uploaded_image');
