<?php
/*
Plugin Name: DigiByte DigiFacts
Description: Display random DigiFacts about DigiByte in multiple languages.
Version: 1.0
Author: Olly Stedall (DigiByte.Help)
*/

add_action('admin_menu', 'digibyte_digifacts_admin_menu');

function digibyte_digifacts_admin_menu() {
    add_options_page('DigiByte DigiFacts Settings', 'DigiFacts', 'manage_options', 'digibyte-digifacts', 'digibyte_digifacts_settings_page');
}

function digibyte_digifacts_settings_page() {
    // Automatically reload languages whenever the settings page is loaded
    digibyte_digifacts_reload_languages();

    // Show any settings errors that were added
    settings_errors('digibyte_digifacts_languages');

    ?>
    <div class="wrap">
        <h2>DigiByte DigiFacts Settings</h2>
        <form action="options.php" method="post">
            <?php
            settings_fields('digibyte_digifacts_options');
            do_settings_sections('digibyte-digifacts');
            submit_button();
            ?>
        </form>
        <div>
            <h3>Shortcode Usage</h3>
            <p>To display DigiFacts on your site, use the shortcode <code>[digifacts]</code> in your posts or pages.</p>
            <h3>Styling the DigiFacts</h3>
            <p>You can style the DigiFacts title and content with CSS. Add custom styles to your theme's stylesheet or in the Customizer under Additional CSS.</p>
            <pre>
.digibyte-digifact .digifact-title {
    font-size: 24px;
    color: #333;
}
.digibyte-digifact .digifact-content {
    font-size: 16px;
    color: #666;
}
            </pre>
            <p>Replace the font-size and color values with your own preferences.</p>
        </div>
    </div>
    <?php
}


add_action('updated_option', 'digibyte_digifacts_updated_option', 10, 3);
function digibyte_digifacts_updated_option($option_name, $old_value, $value) {
    if ('digibyte_digifacts_language' === $option_name) {
        // Fetch and cache new facts when the language option is updated.
        digibyte_digifacts_fetch_facts($value);
    }
}

function digibyte_digifacts_settings_section_cb() {
    echo '<p>Set your preferences for the DigiByte DigiFacts plugin here.</p>';
}

function digibyte_digifacts_display_field_cb() {
    $display = get_option('digibyte_digifacts_display', 'box'); // Default to 'box' if not set

    ?>
    <input type="radio" id="display_box" name="digibyte_digifacts_display" value="box" <?php checked($display, 'box'); ?> />Box<br />
    <input type="radio" id="display_text" name="digibyte_digifacts_display" value="text" <?php checked($display, 'text'); ?> />Text Only
    <?php
}

add_action('admin_init', 'digibyte_digifacts_settings_init');

function digibyte_digifacts_settings_init() {
    // Register settings for your options page
    register_setting('digibyte_digifacts_options', 'digibyte_digifacts_language', 'digibyte_digifacts_sanitize_language');
    register_setting('digibyte_digifacts_options', 'digibyte_digifacts_display');
    
    // Add a settings section on your options page
    add_settings_section(
        'digibyte_digifacts_settings_section',
        'DigiFacts Display Settings',
        'digibyte_digifacts_settings_section_cb',
        'digibyte-digifacts'
    );

    // Add a settings field for language selection
    add_settings_field(
        'digibyte_digifacts_language_field',
        'Language',
        'digibyte_digifacts_language_field_cb',
        'digibyte-digifacts',
        'digibyte_digifacts_settings_section'
    );

    // Add a settings field for display selection
    add_settings_field(
        'digibyte_digifacts_display_field',
        'Display',
        'digibyte_digifacts_display_field_cb',
        'digibyte-digifacts',
        'digibyte_digifacts_settings_section'
    );

    // Check if the "Reload Languages" button was clicked and the nonce field is valid
    if (isset($_POST['reload_languages_nonce'], $_POST['reload_languages']) &&
        wp_verify_nonce($_POST['reload_languages_nonce'], 'reload_languages_action')) {
        // Call the function to re-fetch the languages
        digibyte_digifacts_reload_languages();
    }
}

function digibyte_digifacts_sanitize_language($language_code) {
    // Ensure the language_code is in the list of languages
    $languages = get_option('digibyte_digifacts_languages', array());

    if (array_key_exists($language_code, $languages)) {
        return $language_code;
    } else {
        // Return the default 'en' if the provided code isn't valid
        return 'en';
    }
}


function digibyte_digifacts_reload_languages() {
    $response = wp_remote_get('https://digifacts.digibyte.help/?get_langs');
    
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
        $language_codes = json_decode(wp_remote_retrieve_body($response), true);
        if (is_array($language_codes)) {
            // Convert numeric array to associative array
            $languages_assoc = array_combine($language_codes, $language_codes);
            // Save the associative array in the database option
            update_option('digibyte_digifacts_languages', $languages_assoc);
        } else {
            // Handle the case where the languages array is not valid
            add_settings_error(
                'digibyte_digifacts_languages',
                'digibyte_digifacts_invalid_array',
                'Invalid languages array received from the API',
                'error'
            );
        }
    } else {
        // Handle the case where the response is a WP error or the response code is not 200
        $error_message = is_wp_error($response) ? $response->get_error_message() : 'Unexpected response code: ' . wp_remote_retrieve_response_code($response);
        add_settings_error(
            'digibyte_digifacts_languages',
            'digibyte_digifacts_fetch_error',
            'Unable to fetch languages from the API: ' . $error_message,
            'error'
        );
    }
    
}

function digibyte_digifacts_language_field_cb() {
    // Fetch the languages every time the settings page is loaded
    digibyte_digifacts_reload_languages();
    
    // Try to get the stored languages from the options table.
    $languages = get_option('digibyte_digifacts_languages', array());

    // Display the language selection dropdown
    $current_language = get_option('digibyte_digifacts_language', 'en');
    echo '<select id="digibyte_digifacts_language" name="digibyte_digifacts_language">';
    
    foreach ($languages as $code => $name) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($code),
            selected($current_language, $code, false),
            esc_html($name)
        );
    }
    echo '</select>';
}

// Fetch and cache remote DigiFacts
function digibyte_digifacts_fetch_facts($language) {
    $transient_key = 'digibyte_digifacts_' . $language;
    $facts = get_transient($transient_key);

    if (false === $facts) {
        $url = "https://digifacts.digibyte.help/?format=html&lang=" . $language;
        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log('Unable to retrieve DigiFacts at this time.');
            return false;
        }

        if (wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Unexpected response code received from the API.');
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        json_decode($body);
        if (json_last_error() === JSON_ERROR_NONE) {
            $facts = json_decode($body, true);
            set_transient($transient_key, $facts, 5 * MINUTE_IN_SECONDS);
        } else {
            error_log('Error decoding DigiFacts: ' . json_last_error_msg());
            return false;
        }
    }

    return $facts;
}

// Shortcode to insert random DigiFact
function digibyte_digifacts_display_shortcode($atts) {
    $language = get_option('digibyte_digifacts_language', 'en');
    if (!$language || $language == '0') {
        error_log('Invalid language code retrieved from settings: ' . $language);
        return 'Error: Invalid language code. Please check the DigiByte DigiFacts settings.';
    }

    $facts = digibyte_digifacts_fetch_facts($language);
    if (!$facts) {
        return 'No DigiFacts available at the moment.';
    }

    // Get the selected display option from the settings
    $display = get_option('digibyte_digifacts_display', 'box');

    $random_key = array_rand($facts);
    $fact = $facts[$random_key];

    ob_start();
    ?>
    <div class="digibyte-digifact <?php echo ($display === 'box') ? 'with-box' : ''; ?>">
        <h5 class="digifact-title"><?php echo esc_html($fact['title']); ?></h5>
        <div class="digifact-content"><?php echo wp_kses_post($fact['content']); ?></div>
    </div>
    <?php
    $content = ob_get_clean();
    return $content;
}
add_shortcode('digifacts', 'digibyte_digifacts_display_shortcode');

function digibyte_digifacts_enqueue_styles() {
    // Enqueue the custom CSS file for DigiFacts
    wp_enqueue_style('digifacts-styles', plugins_url('digifacts-styles.css', __FILE__), array(), '1.0');

    // Check the display option from the plugin settings.
    $display = get_option('digibyte_digifacts_display', 'box');

    // Conditionally enqueue the additional styles based on the display option.
    if ($display === 'box') {
        wp_enqueue_style('digifacts-styles-box', false, array(), '1.0');
        wp_add_inline_style('digifacts-styles-box', '
            /* Additional styles for digifacts with the box border */
            .digibyte-digifact.with-box {
                /* Add any other styling you want for the box here */
            }
            
            /* Additional styling for the DigiFact content inside the box */
            .digibyte-digifact.with-box .digifact-content {
                /* Add styling for content inside the box here */
            }
        ');
    } else {
        wp_enqueue_style('digifacts-styles-no-box', false, array(), '1.0');
        wp_add_inline_style('digifacts-styles-no-box', '
            /* Styles for digifacts without the box border */
            .digibyte-digifact {
                /* Add styling for digifacts without the box here */
            }
            
            /* Additional styling for the DigiFact content without the box */
            .digibyte-digifact .digifact-content {
                /* Add styling for content without the box here */
            }
        ');
    }
}
add_action('wp_enqueue_scripts', 'digibyte_digifacts_enqueue_styles');


function digibyte_digifacts_enqueue_scripts() {
    // Correct the handle to match your enqueued script handle
    wp_enqueue_script('digifact-refresh', plugin_dir_url(__FILE__) . 'js/digifact-refresh.js', array('jquery'), '1.0', true);
    
    // Localize the script with new data
    wp_localize_script('digifact-refresh', 'digibyte_digifacts_ajax_params', array(
        'ajaxurl' => admin_url('admin-ajax.php'), // This will make `ajaxurl` available in the script
        'nonce' => wp_create_nonce('digibyte_digifacts_nonce') // Create a nonce and pass it to the script
    ));
}
add_action('wp_enqueue_scripts', 'digibyte_digifacts_enqueue_scripts');


// handle the AJAX request and return the updated DigiFact content
function digibyte_digifacts_ajax_refresh() {
    check_ajax_referer('digibyte_digifacts_nonce', 'nonce');
    // Fetch a new DigiFact
    $language = get_option('digibyte_digifacts_language', 'en');
    $facts = digibyte_digifacts_fetch_facts($language);

    if ($facts) {
        $random_key = array_rand($facts);
        $fact = $facts[$random_key];

        $response = array(
            'title' => esc_html($fact['title']),
            'content' => wp_kses_post($fact['content']),
        );

        wp_send_json_success($response);
    } else {
        wp_send_json_error('No DigiFacts available at the moment.');
    }
}

add_action('wp_ajax_digibyte_digifacts_ajax_refresh', 'digibyte_digifacts_ajax_refresh');
add_action('wp_ajax_nopriv_digibyte_digifacts_ajax_refresh', 'digibyte_digifacts_ajax_refresh');




