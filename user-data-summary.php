<?php
/*
Plugin Name: Summarize user information
Description: Summary of the user information ,activity and posts using OpenAI.
Version: 1.0.0
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

//Creating button in buddyboss profile
function custom_plugin_add_button_to_profile() {
    if (is_user_logged_in()) {
        $user_id = bp_displayed_user_id(); // Get the displayed user's ID
        echo '<button id="retrieve-data-button" data-user-id="' . esc_attr($user_id) . '">Summary</button>';
    }
}
add_action('bp_after_member_header', 'custom_plugin_add_button_to_profile');


//=================================================================================
//enqueue the button trigger

function custom_plugin_enqueue_scripts() {
    wp_enqueue_script('custom-plugin-script', plugin_dir_url(__FILE__) . 'js/custom-scripts.js', array('jquery'), '1.0', true);

}
add_action('wp_enqueue_scripts', 'custom_plugin_enqueue_scripts');

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

function split_text_into_chunks($text, $max_chunk_size = 2048) {
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
            $content_chunks = split_text_into_chunks($content_to_summarize);
            $chunk_summaries = array(); // Initialize as an empty array

            foreach ($content_chunks as $chunk) {
                // Summarize each chunk and store the summary
                $summary = custom_plugin_generate_summary($chunk);
                $chunk_summaries[] = $summary;
            }
            
            // Combine the chunk summaries into a single summary
            $post_data['summary'] = implode(' ', $chunk_summaries);

            $user_post_data[] = $post_data;
        }
        wp_reset_postdata();
    }
    return $user_post_data;
}

//=================================================================================
//Function to summarize with chatgpt

function custom_plugin_generate_summary($content) {
    // Make an API call to OpenAI to generate a summary
    $api_key = get_option('custom_plugin_api_key');
    $engine = 'text-davinci-002'; // Choose an appropriate engine

    $response = wp_safe_remote_post(
        'https://api.openai.com/v1/engines/' . $engine . '/completions',
        array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'prompt' => "Please summarize the following text:\n$content\n\nSummary:",
                'temperature' => 0.5,
                'max_tokens' => 1024, // Adjust the number of tokens as needed
            )),
        )
    );

    if (is_wp_error($response)) {
        return 'Error: Unable to generate summary.';
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['choices'][0]['text'])) {
        return $data['choices'][0]['text'];
    } else {
        return 'Summary not available. API response: ' . json_encode($data);
    }
}


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
    $user_post_data = custom_plugin_get_user_post_data($user_id);


    // Construct an array with user data
    $user_data = array(
        'user_info' => $user_info,
        'user_post_data' => $user_post_data,
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

    // Access the user_id attribute
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


//==================================================================================================
//Function to create admin menu

function custom_plugin_menu() {
    add_menu_page(
        'ChatGPT API Settings',
        'ChatGPT API',
        'manage_options',
        'custom-plugin-api-settings',
        'custom_plugin_api_settings_page'
    );
}
add_action('admin_menu', 'custom_plugin_menu');


//============================================================================================
//Function to add in admin setting page

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
        <h1>ChatGPT API Settings</h1>
        <form method="post" action="">
            <label for="custom_plugin_api_key">ChatGPT API Key:</label>
            <input type="text" id="custom_plugin_api_key" name="custom_plugin_api_key" value="<?php echo esc_attr($current_api_key); ?>" />
            <p>Enter your ChatGPT API key here.</p>
            <p><input type="submit" class="button button-primary" value="Save API Key" /></p>
        </form>
    </div>
    <?php
}


//Function to enqueue javascript

function summary_admin_enqueue_scripts() {
    wp_enqueue_style( 'jobplace-style', plugin_dir_url( __FILE__ ) . 'build/index.css' );
    wp_enqueue_script( 'jobplace-script', plugin_dir_url( __FILE__ ) . 'build/index.js', array( 'wp-element' ), '1.0.0', true );
}
add_action('wp_enqueue_scripts', 'summary_admin_enqueue_scripts');

