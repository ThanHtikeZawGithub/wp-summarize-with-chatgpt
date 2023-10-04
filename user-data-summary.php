<?php
/*
Plugin Name: Summarize user information
Description: Summary of the user information ,activity and posts using OpenAI.
Version: 1.0.0
Author: Than Htike Zaw
*/

require_once(__DIR__ . '/vendor/autoload.php');

//create database on activation
register_activation_hook(__FILE__, 'custom_plugin_create_summary_table');
register_activation_hook(__FILE__, 'custom_plugin_create_user_intro_table');

//=================================================================================
// Create a custom database table
function custom_plugin_create_summary_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_summaries';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        time_stamp date NOT NULL,
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        content_hash varchar(32) NOT NULL,
        summary text NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY content_hash (content_hash)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
      // Check if there was an error during table creation
      if (empty($wpdb->last_error)) {
        // Table created successfully
        return true;
    } else {
        // Table creation failed, display the error
        $error_message = $wpdb->last_error;
        error_log('Table creation error: ' . $error_message); // Log the error
        return false;
    }
}

// Function to create a custom database table for user introductions
function custom_plugin_create_user_intro_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_intro';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE $table_name (
        user_id bigint(20) NOT NULL,
        introduction text NOT NULL,
        PRIMARY KEY  (user_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Check if there was an error during table creation
    if (empty($wpdb->last_error)) {
        // Table created successfully
        return true;
    } else {
        // Table creation failed, display the error
        $error_message = $wpdb->last_error;
        error_log('User introductions table creation error: ' . $error_message); // Log the error
        return false;
    }
}
// Register the deactivation hook
register_deactivation_hook(__FILE__, 'drop_summary_table_on_deactivate');
register_deactivation_hook(__FILE__, 'drop_user_intro_table_on_deactivate');

// Function to run on plugin deactivation
function drop_summary_table_on_deactivate() {
    // Delete the custom database table
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_summaries';
    // Check if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        // Table exists, so we can delete it
        $wpdb->query("DROP TABLE $table_name");
    }
}

// Function to run on plugin deactivation
function drop_user_intro_table_on_deactivate() {
    // Delete the custom database table
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_intro';
    // Check if the table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        // Table exists, so we can delete it
        $wpdb->query("DROP TABLE $table_name");
    }
}

//Creating button in buddyboss profile
function custom_plugin_add_button_to_profile() {
    if (is_user_logged_in()) {
        $user_id = bp_displayed_user_id(); // Get the displayed user's ID
        $intro_summary = custom_plugin_get_user_intro_summary($user_id);

        if ($intro_summary) {
            echo '<div class="intro-summary">';
            echo '<h3>自己紹介</h3>';
            echo '<p>' . esc_html($intro_summary) . '</p>';
            echo '</div>';
        } else {
            return;
        }

        echo '<button id="retrieve-data-button" data-user-id="' . esc_attr($user_id) . '">Summary</button>';
    }
}
add_action('bp_after_member_header', 'custom_plugin_add_button_to_profile');

//Function to generate user intro
function custom_plugin_get_user_intro_summary($user_id) {

    $user_intro = custom_plugin_get_user_intro($user_id);

    if ($user_intro) {
        return $user_intro;
    }

    $post_summaries = implode("\n", custom_plugin_get_user_summaries($user_id));

    $profile_username = custom_plugin_get_user_info($user_id)['username'];

    $input_intro_chunks = split_text_into_chunks($post_summaries);
    $output_intro_chunks = [];
    //get api key from option database
    $api_key = get_option('custom_plugin_api_key'); // 
    //create an instance of openai-php/client
    $client = OpenAI::client($api_key);

    foreach ( $input_intro_chunks as $chunk) {
        $intro_prompt = "以下の文章の重要なポイントに基づいて短いユーザー紹介を作成します。\n\n名前:$profile_username\n\n彼が投稿したもの:$chunk";
        $result = $client->completions()->create([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => $intro_prompt,
            'temperature' => 0.2,
            'max_tokens' => 1024,
        ]);
        $intro_summary = $result['choices'][0]['text'];
        $output_intro_chunks[] = $intro_summary;
    }
    $output_intro_summary = implode(' ', $output_intro_chunks);
    custom_plugin_save_user_intro($user_id, $output_intro_summary);
    return $output_intro_summary;
}

//Function to retrieve user intro from database
function custom_plugin_get_user_intro($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_intro';

    // Query the database for the user introduction
    $sql = $wpdb->prepare("SELECT introduction FROM $table_name WHERE user_id = %d", $user_id);
    $user_intro = $wpdb->get_var($sql);

    return $user_intro;
}

//Function to save user intro in database
function custom_plugin_save_user_intro($user_id, $intro) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_intro';

    // Check if an introduction already exists for the user
    $existing_intro = custom_plugin_get_user_intro($user_id);

    if ($existing_intro) {
        // If an introduction exists, update it
        $wpdb->update(
            $table_name,
            array('introduction' => $intro),
            array('user_id' => $user_id)
        );
    } else {
        // If no introduction exists, insert a new record
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'introduction' => $intro,
            ),
            array('%d', '%s')
        );
    }
}


//Function to retrieve all the summaries
function custom_plugin_get_user_summaries($user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_summaries';
    $sql = $wpdb->prepare("SELECT summary FROM $table_name WHERE user_id = %d", $user_id);
    $results = $wpdb->get_results($sql);
    $summaries = array();

    if($results) {
        foreach($results as $result) {
            $summaries[] = $result->summary; 
        }
    }
    return $summaries;
}

//=================================================================================
//enqueue the button trigger
function custom_plugin_enqueue_scripts() {
    wp_enqueue_script('custom-plugin-script', plugin_dir_url(__FILE__) . 'js/custom-scripts.js', array('jquery'), '1.0', true);
}
add_action('wp_enqueue_scripts', 'custom_plugin_enqueue_scripts');

// Function to delete post and associated summary
function custom_plugin_delete_post_and_summary($post_id) {
    // Check if the post being deleted has a valid ID
    if ($post_id && get_post_type($post_id) == 'post') {
        // Get the content of the post being deleted
        $post_content = get_post_field('post_content', $post_id);

        // Call the function to delete the summary based on the post's content
        custom_plugin_delete_summary_by_content($post_content);

        // Delete the post
        wp_delete_post($post_id, true); // Use true to force delete
    }
}
// Schedule the clean-up task on plugin activation
register_activation_hook(__FILE__, 'custom_plugin_schedule_summary_cleanup');

// Schedule clean-up task
function custom_plugin_schedule_summary_cleanup() {
    if (!wp_next_scheduled('custom_plugin_summary_cleanup')) {
        wp_schedule_event(time(), 'daily', 'custom_plugin_summary_cleanup');
    }
}

// Function to clean up orphaned summaries
function custom_plugin_cleanup_summaries() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_summaries';
    // Query and delete orphaned summaries
    $wpdb->query(
        "DELETE FROM $table_name WHERE id NOT IN (SELECT DISTINCT content_hash FROM $wpdb->posts)"
    );
}
// Hook the clean-up task
add_action('custom_plugin_summary_cleanup', 'custom_plugin_cleanup_summaries');

//================================================================================
// Function to retrieve user information
function custom_plugin_get_user_info($user_id) {
    $user_data = get_userdata($user_id);
    return array(
        'username' => $user_data->user_login,
        'email' => $user_data->user_email,
    );
}

//=============================================================================
//preprocess text for gpt summarize
function split_text_into_chunks($text, $max_chunk_size = 2049) {
    $chunks = [];
    $current_chunk = "";
    $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

    foreach ($sentences as $sentence) {
        $sentence_length = strlen($sentence);

        if (strlen($current_chunk) + $sentence_length < $max_chunk_size) {
            $current_chunk .= $sentence . ' ';
        } else {
            $chunks[] = rtrim($current_chunk);
            $current_chunk = $sentence . ' ';
        }
    }
    if (!empty($current_chunk)) {
        $chunks[] = rtrim($current_chunk);
    }
    return $chunks;
}

//=============================================================================
//Function to retrieve the posts data
function custom_plugin_get_user_post_data($user_id) {
    $args = array(
        'author' => $user_id,
        'post_type' => 'post',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );
    $user_posts = new WP_Query($args);
    $user_post_data = array();

    if ($user_posts->have_posts()) {
        while ($user_posts->have_posts()) {
            $user_posts->the_post();
            $post_id = get_the_ID();
            $post_data = array(
                'title' => get_the_title(),
                'tags' => wp_get_post_tags($post_id, array('fields' => 'names')),
            );
            // Summarize the post content using OpenAI
            $content_to_summarize = wp_strip_all_tags( get_the_content());
            //First check in the database if it already summarized
            $custom_summary = custom_plugin_get_summary_from_database($content_to_summarize);

            if ($custom_summary) {
                // If a summary exists in the database, use it
                $post_data['summary'] = $custom_summary;
            } else {
                // If no summary exists in the database, generate and store a new summary
                $content_summary = generate_summary($user_id, $content_to_summarize);
                $post_data['summary'] = $content_summary;
            }
            $user_post_data[] = $post_data;
        }
        wp_reset_postdata();
    }
    return $user_post_data;
}

//=================================================================================
//Function to summarize with chatgpt
function generate_summary($user_id, $content) {
    //pre-process the input text to chatgpt 
    $input_chunks = split_text_into_chunks($content);
    $output_chunks = [];
    //get api key from option database
    $api_key = get_option('custom_plugin_api_key'); // 
    //create an instance of openai-php/client
    $client = OpenAI::client($api_key);

    foreach ($input_chunks as $chunk) {
        $prompt = "文章を最も重要な点の箇条書きリストとして要約します。\n\n$chunk\n\n文章:";
        //call the completion endpoint
        $result = $client->completions()->create([
            'model' => 'gpt-3.5-turbo-instruct',
            'prompt' => $prompt,
            'temperature' => 0.2,
            'max_tokens' => 1024,
        ]);
        $summary = $result['choices'][0]['text'];
        $output_chunks[] = $summary;
    }
    $output_summary = implode(' ', $output_chunks);
    // Store the summary in the custom database table
    custom_plugin_store_summary_in_database($user_id, $content, $output_summary);
    //return the summarized content
    return $output_summary;
}

//=================================================================================
// Function to retrieve a summary from the custom database table
function custom_plugin_get_summary_from_database($content) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_summaries';
    $content_hash = md5($content);
    $sql = $wpdb->prepare("SELECT summary FROM $table_name WHERE content_hash = %s", $content_hash);
    $result = $wpdb->get_var($sql);
    return $result;
}

//=================================================================================
// Function to store a summary in the custom database table
function custom_plugin_store_summary_in_database($user_id, $content, $summary) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'user_summaries';
    $content_hash = md5($content);
    $data = array(
        'user_id' => $user_id,
        'content_hash' => $content_hash,
        'summary' => $summary,
    );
    $format = array(
        '%s',
        '%s',
        '%s',
    );

    $wpdb->insert($table_name, $data, $format);
}

// Disable REST API for non-logged-in users
function restrict_rest_api_for_non_logged_in_users($access) {
    if (!is_user_logged_in()) {
        // Disable REST API for non-logged-in users
        return new WP_Error('rest_api_disabled', 'REST API is disabled for non-logged-in users', array('status' => 401));
    }
    return $access;
}
add_filter('rest_authentication_errors', 'restrict_rest_api_for_non_logged_in_users');

//===================================================================================
// Register a custom REST API endpoint
function custom_plugin_register_rest_route() {
    register_rest_route('user-summary/v1', '/user-data/(?P<user_id>\d+)', array(
        'methods' => 'GET',
        'callback' => 'custom_plugin_get_user_data',
    ));
}
add_action('rest_api_init', 'custom_plugin_register_rest_route');

// Callback function to retrieve user data
function custom_plugin_get_user_data($request) {
    $user_id = $request['user_id'];
    // Verify that the user_id is a valid integer
    if (!is_numeric($user_id)) {
        return new WP_Error('invalid_user_id', 'Invalid user ID', array('status' => 400));
    }
    // Retrieve user information, activity, and post titles here
    $user_info = custom_plugin_get_user_info($user_id);
    //call instance for post data
    $user_post_data = custom_plugin_get_user_post_data($user_id);
    //call the number of posts of that user
    $total_items = count_user_posts($user_id, 'post');
    // Construct an array with user data
    $user_data = array(
        'user_info' => $user_info,
        'user_post_data' => $user_post_data,
        'total_items' => $total_items, // Total number of items
    );
    // Return user data as a JSON response
    return rest_ensure_response($user_data);
}

//=========================================================================================
//Function to add shortcode to render react component
function custom_plugin_user_data_shortcode($atts) {
    // Extract attributes (if any) - you can customize this based on your needs
    $atts = shortcode_atts(array(
        'user_id' => null, // Default to null
    ), $atts);
    // access the user_id attribute
    $user_id = $atts['user_id'];
    // Check if a user ID is provided and if it's numeric
    if (isset($user_id) && is_numeric($user_id)) {
        // Output the React component container with user ID passed as a prop
        return '<div id="user-summary-app" data-user-id="' . esc_attr($user_id) . '"></div>';
    } else {
        // If no valid user ID is provided, you can display an error message or handle it as needed
        return 'User ID not specified';
    }
}
add_shortcode('custom_user_data', 'custom_plugin_user_data_shortcode');

//=================================================================================
// Function to create an admin menu
function custom_plugin_menu() {
    add_menu_page(
        'Activities Summary Settings',
        'Activites Summary',
        'manage_options',
        'custom-plugin-api-settings',
        'custom_plugin_api_settings_page'
    );
    // Add a submenu item for resetting/deleting data
    add_submenu_page(
        'custom-plugin-api-settings',
        'Reset/ Delete Data',
        'Reset/ Delete Data',
        'manage_options',
        'custom-plugin-reset-data',
        'custom_plugin_reset_data_page'
    );
}
add_action('admin_menu', 'custom_plugin_menu');

//=================================================================================
// Function to create an admin settings page
function custom_plugin_api_settings_page() {
    // Check if the user is allowed to access the settings page
    if (!current_user_can('manage_options')) {
        return;
    }
    // Check for form submission and update the API key
    if (isset($_POST['custom_plugin_api_key'])) {
        $api_key = sanitize_text_field($_POST['custom_plugin_api_key']);
        // Save the API key securely (more on this below)
        update_option('custom_plugin_api_key', $api_key);
        echo '<div class="updated"><p>API key updated successfully!</p></div>';
    }
    // Retrieve the currently saved API key
    $current_api_key = get_option('custom_plugin_api_key');
    // Output the settings page HTML
    ?>
    <div class="wrap">
        <h1>Activities Summary Settings</h1>
        <form method="post" action="">
            <label for="custom_plugin_api_key">ChatGPT API Key:</label>
            <input type="text" id="custom_plugin_api_key" name="custom_plugin_api_key" value="<?php echo esc_attr($current_api_key); ?>" />
            <p>Enter your ChatGPT API key here.</p>
            <p><input type="submit" class="button button-primary" value="Save API Key" /></p>
        </form>
    </div>
    <?php
}

//=================================================================================
// Function to create a reset/delete data page in the admin backend
function custom_plugin_reset_data_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    if (isset($_POST['reset_data'])) {
        // Handle the reset action here (e.g., delete all data from the custom database table)
        custom_plugin_delete_summary_table();
        echo '<div class="updated"><p>Data reset successfully!</p></div>';
    }
    if (isset($_POST['delete_data'])) {
        // Handle the delete action here (e.g., delete selected data from the custom database table)
        // Implement your logic here to delete specific data as needed
        echo '<div class="updated"><p>Data deleted successfully!</p></div>';
    }
    // Display the reset/delete data options form
    ?>
    <div class="wrap">
        <h1>Reset/ Delete Data</h1>
        <form method="post" action="">
            <p><strong>Reset All Summarizes:</strong> This will delete all summarized content from the database...</p>
            <input type="submit" class="button button-primary" name="reset_data" value="Reset All Data" onclick="return confirm('Are you sure you want to reset all data? This action cannot be undone.');">
            <br><br>
            <p><strong>Delete Specific User Summary:</strong> Feature Coming Soon...</p>
            <input type="submit" class="button button-primary" name="delete_data" value="Delete Specific Data">
        </form>
    </div>
    <?php
}

//=================================================================================
// Function to delete the custom database table
function custom_plugin_delete_summary_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'custom_plugin_summaries';

    // Drop the custom database table if it exists
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
}

//Function to enqueue javascript
function react_enqueue_scripts() {
    wp_enqueue_style( 'react-style', plugin_dir_url( __FILE__ ) . 'build/index.css' );
    wp_enqueue_script( 'react-script', plugin_dir_url( __FILE__ ) . 'build/index.js', array( 'wp-element' ), '1.0.0', true );
}
add_action('wp_enqueue_scripts', 'react_enqueue_scripts');



