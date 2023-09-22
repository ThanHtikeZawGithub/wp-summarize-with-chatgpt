<?php
/*
Plugin Name: Custom User Blog Posts
Description: Summary of the user information ,activity and posts.
Version: 1.0
*/

function enqueue_custom_scripts() {
    wp_enqueue_script('custom-scripts', plugin_dir_url(__FILE__) . 'js/custom-scripts.js', array('jquery'), '1.0', true);
    wp_localize_script('custom-scripts', 'custom_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
    wp_enqueue_style('custom-style', plugin_dir_url(__FILE__) . 'css/custom-style.css');
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');


function custom_plugin_add_button_to_profile() {
    if (is_user_logged_in()) {
        echo '<button id="retrieve-data-button">User Summary</button>';
    }
}
add_action('bp_after_member_header', 'custom_plugin_add_button_to_profile');

// Function to retrieve user information
function custom_plugin_get_user_info($user_id) {
    $user_data = get_userdata($user_id);
    return array(
        'username' => $user_data->user_login,
        'email' => $user_data->user_email,
    );
}

// Function to retrieve user activity
function custom_plugin_get_user_activity($user_id) {
    // Check if BuddyPress is active
    if (function_exists('bp_get_activity_user_id')) {
        $args = array(
            'user_id' => $user_id,
            'per_page' => 10,
        );

        // Retrieve user-specific activities
        $activities = bp_activity_get($args);
        $activity_content = array();

        if (!empty($activities['activities'])) {
            foreach ($activities['activities'] as $activity) {
                $activity_content[] = $activity->content;
            }
        }

        return $activity_content;
    }

    return array(); // Return an empty array if BuddyPress is not active or no activities found.
}

// Function to retrieve user post titles
function custom_plugin_get_user_post_data($user_id) {
    $args = array(
        'author' => $user_id,
        'post_type' => 'post', // Replace with your custom post type if needed
        'posts_per_page' => -1, // Retrieve all posts
        'post-status' => 'publish',
    );
    $user_posts = new WP_Query($args);
    $user_post_data = array();
    if ($user_posts->have_posts()) {
        while ($user_posts->have_posts()) {
            $user_posts->the_post();
            $post_data = array(
                'title' => get_the_title(),
                'content' => get_the_content(),
                'tags' => wp_get_post_tags(get_the_ID(), array('fields' => 'names')),
            );
            $user_post_data[] = $post_data;
        }
        wp_reset_postdata();
    }
    return $user_post_data;
}


// Function to handle the AJAX request
function custom_plugin_ajax_handler() {
    ob_start();

    // Retrieve user information, activity, and post titles
    $displayed_user_id = bp_displayed_user_id();
    $user_info = custom_plugin_get_user_info($displayed_user_id);
    $user_activity = custom_plugin_get_user_activity($displayed_user_id);
    $user_post_data = custom_plugin_get_user_post_data($displayed_user_id);
    // $user_post_contents = get_user_posts($displayed_user_id);

    // // Generate and send a downloadable file

    // Display user data in a pop-up modal
    echo '<p><strong>Username:</strong> ' . esc_html($user_info['username']) . '</p>';
    echo '<p><strong>Email:</strong> ' . esc_html($user_info['email']) . '</p>';
    echo '<h3>Activity</h3>';
    foreach ($user_activity as $activity) {
        echo '<p>' . $activity . '</p>';
    }
    echo '<h3>Posted Articles</h3>';
    // foreach ($user_post_titles as $post_title) {
    //     echo '<p>' . esc_html($post_title) . '</p>';
    // }
    echo '<h3>Post Content</h3>';
    foreach ($user_post_data as $post_data) {
        echo '<p><strong>Title:</strong> ' . esc_html($post_data['title']) . '</p>';
        echo '<p><strong>Tags:</strong> ' . esc_html(implode(', ', $post_data['tags'])) . '</p>';
        echo '<p><strong>Content:</strong> ' . $post_data['content'] . '</p>';
    }

    $output = ob_get_clean();

    echo $output;
    die();
}
add_action('wp_ajax_custom_action', 'custom_plugin_ajax_handler');

// function get_user_posts($user_id) {
//     $args = array(
//         'author' => $user_id,
//         'post_type' => 'post',
//         'post_per_page' => -1,
//         'post-status' => 'publish',
//     );

//     $user_posts = new WP_Query($args);

//     if ($user_posts->have_posts()) {
//         while ($user_posts->have_posts()) {
//             $user_posts->the_post();

//             //Display post content or perform other actions.
//             the_content(); // Display post content.
//         }
//         wp_reset_postdata();
//     } else {
//         echo "No posts found";
//     }
// }