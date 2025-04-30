<?php
/**
 * Admin settings for thinkany WP AI Search
 */

class ThinkAny_WP_AI_Search_Admin {
    private static $instance = null;
    
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add settings link on the plugins page
        add_filter('plugin_action_links_thinkany-wp-ai-search/thinkany-wp-ai-search.php', array($this, 'add_settings_link'));
        
        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('thinkany WP AI Search Settings', 'thinkany-wp-ai-search'),
            __('thinkany WP AI Search', 'thinkany-wp-ai-search'),
            'manage_options',
            'thinkany-wp-ai-search',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting(
            'thinkany_wp_ai_search_settings_group',
            'thinkany_wp_ai_search_settings',
            array($this, 'sanitize_settings')
        );
        
        add_settings_section(
            'thinkany_wp_ai_search_main_section',
            __('OpenAI API Settings', 'thinkany-wp-ai-search'),
            array($this, 'render_section_info'),
            'thinkany-wp-ai-search'
        );

        add_settings_field(
            'enabled',
            __('Enable AI Search', 'thinkany-wp-ai-search'),
            array($this, 'render_enabled_field'),
            'thinkany-wp-ai-search',
            'thinkany_wp_ai_search_main_section'
        );
        
        add_settings_field(
            'api_key',
            __('API Key', 'thinkany-wp-ai-search'),
            array($this, 'render_api_key_field'),
            'thinkany-wp-ai-search',
            'thinkany_wp_ai_search_main_section'
        );
        
        add_settings_field(
            'model',
            __('OpenAI Model', 'thinkany-wp-ai-search'),
            array($this, 'render_model_field'),
            'thinkany-wp-ai-search',
            'thinkany_wp_ai_search_main_section'
        );
        
        add_settings_field(
            'cache_duration',
            __('Cache Duration (hours)', 'thinkany-wp-ai-search'),
            array($this, 'render_cache_duration_field'),
            'thinkany-wp-ai-search',
            'thinkany_wp_ai_search_main_section'
        );

        add_settings_field(
            'debug',
            __('Debug Mode', 'thinkany-wp-ai-search'),
            array($this, 'render_debug_field'),
            'thinkany-wp-ai-search',
            'thinkany_wp_ai_search_main_section'
        );
        
        add_settings_field(
            'token_info_logging',
            __('Token Info Logging', 'thinkany-wp-ai-search'),
            array($this, 'render_token_info_logging_field'),
            'thinkany-wp-ai-search',
            'thinkany_wp_ai_search_main_section'
        );
    }
    
    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        // Sanitize Enabled - check if API key is provided when enabling
        $sanitized_input['enabled'] = isset($input['enabled']) ? (bool)$input['enabled'] : false;
        
        // Sanitize API key
        if (isset($input['api_key']) && !empty($input['api_key'])) {
            // Encrypt API key before saving
            $sanitized_input['api_key'] = $this->encrypt_api_key($input['api_key']);
        } else {
            // Keep the old encrypted value if exists
            $options = get_option('thinkany_wp_ai_search_settings');
            $sanitized_input['api_key'] = isset($options['api_key']) ? $options['api_key'] : '';
            
            // If enabling AI search but no API key is set, show error and disable
            if ($sanitized_input['enabled'] && empty($sanitized_input['api_key'])) {
                add_settings_error(
                    'thinkany_wp_ai_search_settings',
                    'missing_api_key',
                    __('API key is required when AI search is enabled. Please enter your OpenAI API key.', 'thinkany-wp-ai-search'),
                    'error'
                );
                $sanitized_input['enabled'] = false;
            }
        }
        
        // Sanitize model
        if (isset($input['model'])) {
            $valid_models = array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo');
            $sanitized_input['model'] = in_array($input['model'], $valid_models) ? $input['model'] : 'gpt-3.5-turbo';
        } else {
            $sanitized_input['model'] = 'gpt-3.5-turbo';
        }
        
        // Sanitize cache duration
        if (isset($input['cache_duration'])) {
            $sanitized_input['cache_duration'] = intval($input['cache_duration']);
            if ($sanitized_input['cache_duration'] < 1) {
                $sanitized_input['cache_duration'] = 1;
            }
        } else {
            $sanitized_input['cache_duration'] = 6;
        }
        
        // Sanitize debug mode
        $sanitized_input['debug'] = isset($input['debug']) ? (bool)$input['debug'] : false;
        
        // Sanitize token info logging
        $sanitized_input['token_info_logging'] = isset($input['token_info_logging']) ? (bool)$input['token_info_logging'] : false;
        
        return $sanitized_input;
    }
    
    /**
     * Encrypt API key using WordPress salt keys
     */
    private function encrypt_api_key($api_key) {
        if (empty($api_key)) {
            return '';
        }
        
        // Use WordPress auth keys for encryption
        $encryption_key = defined('AUTH_KEY') ? AUTH_KEY : 'default-key';
        $encryption_salt = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'default-salt';
        
        // Create a random initialization vector
        $iv_size = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($iv_size);
        
        // Encrypt the API key
        $encrypted = openssl_encrypt(
            $api_key,
            'AES-256-CBC',
            $encryption_key,
            0,
            $iv
        );
        
        // Combine the IV and encrypted data
        $encrypted_with_iv = base64_encode($iv . $encrypted);
        
        return $encrypted_with_iv;
    }
    
    /**
     * Decrypt API key
     */
    public static function decrypt_api_key($encrypted_api_key) {
        if (empty($encrypted_api_key)) {
            return '';
        }
        
        // Use WordPress auth keys for decryption
        $encryption_key = defined('AUTH_KEY') ? AUTH_KEY : 'default-key';
        $encryption_salt = defined('SECURE_AUTH_SALT') ? SECURE_AUTH_SALT : 'default-salt';
        
        // Decode the combined string
        $decoded = base64_decode($encrypted_api_key);
        
        // Extract the IV and encrypted data
        $iv_size = openssl_cipher_iv_length('AES-256-CBC');
        $iv = substr($decoded, 0, $iv_size);
        $encrypted = substr($decoded, $iv_size);
        
        // Decrypt the API key
        $decrypted = openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $encryption_key,
            0,
            $iv
        );
        
        return $decrypted;
    }
    
    /**
     * Add settings link to plugin listing
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=thinkany-wp-ai-search')) . '">' . esc_html__('Settings', 'thinkany-wp-ai-search') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function render_section_info() {
        echo '<p>' . esc_html__('Configure your OpenAI API settings for AI-enhanced search functionality.', 'thinkany-wp-ai-search') . '</p>';
    }

    public function render_enabled_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        
        echo '<label class="thinkany-toggle-switch">
            <input type="checkbox" id="enabled" name="thinkany_wp_ai_search_settings[enabled]" value="1" ' . checked($enabled, true, false) . ' />
            <span class="thinkany-toggle-slider"></span>
        </label>';
        echo '<span class="thinkany-toggle-label">' . esc_html__('Enable AI-enhanced search functionality', 'thinkany-wp-ai-search') . '</span>';
        echo '<p class="description">' . esc_html__('Uncheck to use standard WordPress search.', 'thinkany-wp-ai-search') . '</p>';
    }
        
    public function render_api_key_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        // Check if we have a saved API key
        $has_api_key = !empty($api_key);
        
        echo '<input type="password" id="api_key" name="thinkany_wp_ai_search_settings[api_key]" value="" class="regular-text" placeholder="' . 
            ($has_api_key ? '••••••••••••••••••••••' : esc_attr__('Enter your OpenAI API key', 'thinkany-wp-ai-search')) . '" />';
        
        if ($has_api_key) {
            echo '<p class="description">' . esc_html__('API key is securely stored. Enter a new key to change it.', 'thinkany-wp-ai-search') . '</p>';
        } else {
            echo '<p class="description">' . esc_html__('Enter your OpenAI API key. This will be encrypted before storage.', 'thinkany-wp-ai-search') . '</p>';
        }
    }
    
    public function render_model_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $model = isset($options['model']) ? $options['model'] : 'gpt-3.5-turbo';
        
        // Models with their display names and pricing information
        $models = array(
            'gpt-3.5-turbo' => array(
                'name' => 'GPT-3.5 Turbo',
                'input_price' => 0.0005, // $ per 1K tokens
                'output_price' => 0.0015  // $ per 1K tokens
            ),
            'gpt-4' => array(
                'name' => 'GPT-4',
                'input_price' => 0.03,   // $ per 1K tokens
                'output_price' => 0.06   // $ per 1K tokens
            ),
            'gpt-4-turbo' => array(
                'name' => 'GPT-4 Turbo',
                'input_price' => 0.01,   // $ per 1K tokens
                'output_price' => 0.03   // $ per 1K tokens
            )
        );
        
        echo '<select id="model" name="thinkany_wp_ai_search_settings[model]">';
        foreach ($models as $model_id => $model_info) {
            $price_info = sprintf(
                '($%s input, $%s output per 1K tokens)',
                number_format($model_info['input_price'], 4),
                number_format($model_info['output_price'], 4)
            );
            
            echo '<option value="' . esc_attr($model_id) . '" ' . selected($model, $model_id, false) . '>' 
                . esc_html($model_info['name']) . ' ' . esc_html($price_info) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Select the OpenAI model to use for search enhancement. Pricing shown is per 1,000 tokens.', 'thinkany-wp-ai-search') . '</p>';
    }
    
    public function render_cache_duration_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 6;
        
        echo '<input type="number" id="cache_duration" name="thinkany_wp_ai_search_settings[cache_duration]" value="' . esc_attr($cache_duration) . '" min="1" max="72" />';
        echo '<p class="description">' . esc_html__('How long to cache AI-enhanced search results (in hours).', 'thinkany-wp-ai-search') . '</p>';
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('thinkany_wp_ai_search_settings_group');
                do_settings_sections('thinkany-wp-ai-search');
                submit_button();
                ?>
            </form>
            <?php $this->render_api_usage_info(); ?>
        </div>
        <?php
    }

    public function render_debug_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        
        echo '<label class="thinkany-toggle-switch">
            <input type="checkbox" id="thinkany_wp_ai_search_debug" name="thinkany_wp_ai_search_settings[debug]" value="1" ' . checked($debug, true, false) . ' />
            <span class="thinkany-toggle-slider"></span>
        </label>';
        echo '<span class="thinkany-toggle-label">' . esc_html__('Enable debug mode (logs detailed information to error log)', 'thinkany-wp-ai-search') . '</span>';
    }
    
    /**
     * Render the token info logging field
     */
    public function render_token_info_logging_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $token_info_logging = isset($options['token_info_logging']) ? (bool)$options['token_info_logging'] : false;
        
        echo '<label class="thinkany-toggle-switch">
            <input type="checkbox" id="thinkany_wp_ai_search_token_info_logging" name="thinkany_wp_ai_search_settings[token_info_logging]" value="1" ' . checked($token_info_logging, true, false) . ' />
            <span class="thinkany-toggle-slider"></span>
        </label>';
        echo '<span class="thinkany-toggle-label">' . esc_html__('Enable token info logging (logs API usage information to error log)', 'thinkany-wp-ai-search') . '</span>';
    }
    
    /**
     * Get OpenAI API usage information
     */
    private function get_api_usage_info() {
        // During testing phase, we'll skip the cache and always fetch fresh data
        $usage_data = false; // Always fetch fresh data during testing
        
        if (false === $usage_data) {
            $options = get_option('thinkany_wp_ai_search_settings');
            $encrypted_api_key = isset($options['api_key']) ? $options['api_key'] : '';
            $api_key = $this->decrypt_api_key($encrypted_api_key);
            $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
            
            if (empty($api_key)) {
                return false;
            }
            
            // First, check models to verify the API key is valid
            $models_response = wp_remote_get('https://api.openai.com/v1/models', array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json',
                ),
                'timeout' => 15,
            ));
            
            if (is_wp_error($models_response)) {
                return false;
            } else {
                $models_response_code = wp_remote_retrieve_response_code($models_response);
                
                if ($models_response_code !== 200) {
                    return false;
                }
            }
            
            // API key is valid, now get the models data
            $models_data = json_decode(wp_remote_retrieve_body($models_response), true);
            
            // Define the specific models we support
            $supported_models = array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo');
            
            // Extract only the models that we support
            $relevant_models = array();
            if (isset($models_data['data']) && is_array($models_data['data'])) {
                foreach ($models_data['data'] as $model) {
                    // Check if this model is one of our supported base models or a variant
                    foreach ($supported_models as $supported_model) {
                        if (strpos($model['id'], $supported_model) !== false) {
                            $relevant_models[] = $model['id'];
                            break;
                        }
                    }
                }
            }
            
            // Get the default model used by the plugin
            $default_model = isset($options['model']) ? $options['model'] : 'gpt-3.5-turbo';
            
            // Get API usage data from our custom tracking table
            global $wpdb;
            $table_name = $wpdb->prefix . 'thinkany_api_usage';
            
            // Get today's usage
            $today = current_time('Y-m-d');
            $today_data = $wpdb->get_row($wpdb->prepare(
                "SELECT call_count, prompt_tokens, completion_tokens, total_tokens, estimated_cost 
                FROM $table_name WHERE call_date = %s",
                $today
            ));
            
            $today_count = $today_data ? intval($today_data->call_count) : 0;
            $today_tokens = $today_data ? intval($today_data->total_tokens) : 0;
            $today_cost = $today_data ? floatval($today_data->estimated_cost) : 0;
            
            // Get current month's usage
            $first_day_of_month = date('Y-m-01', current_time('timestamp'));
            $monthly_data = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(call_count) as call_count, 
                SUM(prompt_tokens) as prompt_tokens, 
                SUM(completion_tokens) as completion_tokens, 
                SUM(total_tokens) as total_tokens, 
                SUM(estimated_cost) as estimated_cost 
                FROM $table_name WHERE call_date >= %s AND call_date <= %s",
                $first_day_of_month,
                $today
            ));
            
            $monthly_count = $monthly_data ? intval($monthly_data->call_count) : 0;
            $monthly_tokens = $monthly_data ? intval($monthly_data->total_tokens) : 0;
            $monthly_cost = $monthly_data ? floatval($monthly_data->estimated_cost) : 0;
            
            // Compile all the information
            $usage_data = array(
                'relevant_models' => $relevant_models,
                'default_model' => $default_model,
                'completions_count' => $monthly_count,
                'completions_today' => $today_count,
                'tokens_today' => $today_tokens,
                'tokens_month' => $monthly_tokens,
                'cost_today' => $today_cost,
                'cost_month' => $monthly_cost,
                'checked_time' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                'api_key_valid' => true
            );
            
            // Cache the data for 1 hour
            set_transient('thinkany_wp_ai_search_usage_data', $usage_data, HOUR_IN_SECONDS);
        }
        
        if (false === $usage_data) {
            $usage_data = array(
                'relevant_models' => array(),
                'default_model' => __('Not available', 'thinkany-wp-ai-search'),
                'completions_count' => 0,
                'completions_today' => 0,
                'tokens_today' => 0,
                'tokens_month' => 0,
                'cost_today' => 0,
                'cost_month' => 0,
                'checked_time' => date_i18n(get_option('date_format') . ' ' . get_option('time_format')),
                'api_key_valid' => false
            );
        }
        
        return $usage_data;
    }
    
    /**
     * Render API usage information
     */
    private function render_api_usage_info() {
        $usage_data = $this->get_api_usage_info();
        
        echo '<div class="thinkany-api-info">';
        echo '<h3>' . esc_html__('OpenAI API Information', 'thinkany-wp-ai-search') . '</h3>';
        
        // Only show API Key Valid message if we actually got data from the API
        if (isset($usage_data['api_key_valid']) && $usage_data['api_key_valid']) {
            // API Key Status
            echo '<div class="notice notice-success inline">';
            echo '<p><strong>' . esc_html__('API Key Status:', 'thinkany-wp-ai-search') . '</strong> ' . esc_html__('Valid', 'thinkany-wp-ai-search') . '</p>';
            echo '</div>';
        }
        
        // API Usage Limitation Notice
        echo '<div class="notice notice-info">';
        echo '<p><strong>' . esc_html__('API Usage Information:', 'thinkany-wp-ai-search') . '</strong> ' . esc_html__('OpenAI restricts access to billing and usage data via API keys. This information can only be accessed through the OpenAI dashboard in a browser.', 'thinkany-wp-ai-search') . '</p>';
        echo '<p>' . esc_html__('To view your usage statistics, remaining budget, and other billing information, please visit:', 'thinkany-wp-ai-search') . ' <a href="https://platform.openai.com/account/usage" target="_blank">OpenAI Dashboard</a></p>';
        echo '</div>';
        
        // Container for two-column layout
        echo '<div class="thinkany-api-info-container">';
        
        // Left column - API status and usage
        echo '<div class="thinkany-api-info-column">';
        echo '<h4>' . esc_html__('API Configuration', 'thinkany-wp-ai-search') . '</h4>';
        
        echo '<table class="form-table" role="presentation">';
        
        // Models information
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Available Models', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['relevant_models']) && is_array($usage_data['relevant_models']) ? count($usage_data['relevant_models']) : 0) . ' ' . esc_html__('compatible models found', 'thinkany-wp-ai-search') . '</td>';
        echo '</tr>';
        
        // Default model
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Default Model', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html($usage_data['default_model']) . '</td>';
        echo '</tr>';
        
        // Add Chat Completions Count
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('API Calls This Month', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['completions_count']) ? number_format($usage_data['completions_count']) : '0') . '</td>';
        echo '</tr>';
        
        // Add Chat Completions Today
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('API Calls Today', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['completions_today']) ? number_format($usage_data['completions_today']) : '0') . '</td>';
        echo '</tr>';
        
        // Add Tokens Today
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Tokens Used Today', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['tokens_today']) ? number_format($usage_data['tokens_today']) : '0') . '</td>';
        echo '</tr>';
        
        // Add Tokens This Month
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Tokens Used This Month', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['tokens_month']) ? number_format($usage_data['tokens_month']) : '0') . '</td>';
        echo '</tr>';
        
        // Add Cost Today
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Estimated Cost Today', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['cost_today']) ? number_format($usage_data['cost_today'], 2) : '0.00') . '</td>';
        echo '</tr>';
        
        // Add Cost This Month
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Estimated Cost This Month', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['cost_month']) ? number_format($usage_data['cost_month'], 2) : '0.00') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Last Checked', 'thinkany-wp-ai-search') . '</th>';
        echo '<td>' . esc_html(isset($usage_data['checked_time']) ? $usage_data['checked_time'] : date_i18n(get_option('date_format') . ' ' . get_option('time_format'))) . '</td>';
        echo '</tr>';
        
        // Add error message after the "Last Checked" row if API data couldn't be retrieved
        if (!isset($usage_data['api_key_valid']) || $usage_data['api_key_valid'] === false) {
            echo '<tr>';
            echo '<td colspan="2">';
            echo '<div class="notice notice-error inline" style="margin: 5px 0 0 0;">';
            echo '<p>' . esc_html__('Could not retrieve API information. Please check your API key.', 'thinkany-wp-ai-search') . '</p>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
        
        // Right column - Available models
        echo '<div class="thinkany-api-info-column">';
        echo '<h4>' . esc_html__('Available OpenAI Models', 'thinkany-wp-ai-search') . '</h4>';
        
        if (isset($usage_data['relevant_models']) && is_array($usage_data['relevant_models']) && !empty($usage_data['relevant_models'])) {
            // Group models by type to check availability
            $model_availability = array(
                'gpt-3.5-turbo' => false,
                'gpt-4' => false,
                'gpt-4-turbo' => false
            );
            
            // Check which model types are available
            foreach ($usage_data['relevant_models'] as $model) {
                if (strpos($model, 'gpt-3.5-turbo') !== false) {
                    $model_availability['gpt-3.5-turbo'] = true;
                } elseif (strpos($model, 'gpt-4-turbo') !== false) {
                    $model_availability['gpt-4-turbo'] = true;
                } elseif (strpos($model, 'gpt-4') !== false && strpos($model, 'gpt-4-turbo') === false) {
                    $model_availability['gpt-4'] = true;
                }
            }
            
            echo '<div class="thinkany-models-container">';
            
            // Display only the main model types that are available
            $model_labels = array(
                'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
                'gpt-4' => 'GPT-4',
                'gpt-4-turbo' => 'GPT-4 Turbo'
            );
            
            if (isset($model_availability) && is_array($model_availability)) {
                foreach ($model_availability as $model_type => $is_available) {
                    if ($is_available) {
                        echo '<div class="thinkany-model-card">';
                        echo '<span class="dashicons dashicons-yes-alt" style="color: green;"></span> ';
                        echo '<strong>' . esc_html($model_labels[$model_type]) . '</strong>';
                        echo '</div>';
                    } else {
                        echo '<div class="thinkany-model-card thinkany-model-unavailable">';
                        echo '<span class="dashicons dashicons-no-alt" style="color: #ccc;"></span> ';
                        echo '<span style="color: #999;">' . esc_html($model_labels[$model_type]) . '</span>';
                        echo '</div>';
                    }
                }
            }
            
            echo '</div>';
        } else {
            echo '<p class="description">' . esc_html__('No compatible models found with your API key.', 'thinkany-wp-ai-search') . '</p>';
        }
        
        echo '</div>'; // End right column
        echo '</div>'; // End container
        
        echo '<style>
            .thinkany-api-info-container {
                display: flex;
                flex-wrap: wrap;
                gap: 30px;
                margin-top: 20px;
            }
            .thinkany-api-info-column {
                flex: 1;
                min-width: 300px;
            }
            .thinkany-models-container {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                margin-top: 15px;
            }
            .thinkany-model-card {
                padding: 10px 15px;
                background-color: #f8f9fa;
                border-radius: 4px;
                border: 1px solid #ddd;
            }
            .thinkany-model-unavailable {
                background-color: #f5f5f5;
                border-color: #eee;
            }
        </style>';
        
        echo '<p class="description">' . esc_html__('API information is refreshed on each page load during testing.', 'thinkany-wp-ai-search') . '</p>';
        echo '</div>';
    }
    
    /**
     * Enqueue admin CSS for styling the toggle switches
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook != 'settings_page_thinkany-wp-ai-search') {
            return;
        }
        
        wp_enqueue_style(
            'thinkany-wp-ai-search-admin',
            THINKANY_WP_AI_SEARCH_URL . 'assets/css/admin.css',
            array(),
            THINKANY_WP_AI_SEARCH_VERSION
        );
    }
}