<?php
/**
 * Admin settings for ThinkAny WP AI Search
 */

class ThinkAny_WP_AI_Search_Admin {
    private static $instance = null;
    
    private function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function add_admin_menu() {
        add_options_page(
            __('ThinkAny WP AI Search Settings', 'thinkany-wp-ai-search'),
            __('ThinkAny WP AI Search', 'thinkany-wp-ai-search'),
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
    }
    
    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        // Sanitize API key
        if (isset($input['api_key']) && !empty($input['api_key'])) {
            // Encrypt API key before saving
            $sanitized_input['api_key'] = $this->encrypt_api_key($input['api_key']);
        } else {
            // Keep the old encrypted value if exists
            $options = get_option('thinkany_wp_ai_search_settings');
            $sanitized_input['api_key'] = isset($options['api_key']) ? $options['api_key'] : '';
        }

        // Sanitize Enabled
        $sanitized_input['enabled'] = isset($input['enabled']) ? (bool)$input['enabled'] : false;
        
        // Sanitize model
        $sanitized_input['model'] = isset($input['model']) ? sanitize_text_field($input['model']) : 'gpt-3.5-turbo';
        
        // Sanitize cache duration
        $sanitized_input['cache_duration'] = isset($input['cache_duration']) ? intval($input['cache_duration']) : 6;
        
        // Sanitize Debug Enabled
        $sanitized_input['debug'] = isset($input['debug']) ? (bool)$input['debug'] : false;
        
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
    
    public function render_section_info() {
        echo '<p>' . __('Configure your OpenAI API settings for AI-enhanced search functionality.', 'thinkany-wp-ai-search') . '</p>';
    }

    public function render_enabled_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        
        echo '<input type="checkbox" id="enabled" name="thinkany_wp_ai_search_settings[enabled]" value="1" ' . checked($enabled, true, false) . ' />';
        echo '<p class="description">' . __('Enable AI-enhanced search functionality. Uncheck to use standard WordPress search.', 'thinkany-wp-ai-search') . '</p>';
    }
        
    public function render_api_key_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        // Check if we have a saved API key
        $has_api_key = !empty($api_key);
        
        echo '<input type="password" id="api_key" name="thinkany_wp_ai_search_settings[api_key]" value="" class="regular-text" placeholder="' . 
            ($has_api_key ? '••••••••••••••••••••••' : __('Enter your OpenAI API key', 'thinkany-wp-ai-search')) . '" />';
        
        if ($has_api_key) {
            echo '<p class="description">' . __('API key is securely stored. Enter a new key to change it.', 'thinkany-wp-ai-search') . '</p>';
        } else {
            echo '<p class="description">' . __('Enter your OpenAI API key. This will be encrypted before storage.', 'thinkany-wp-ai-search') . '</p>';
        }
    }
    
    public function render_model_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $model = isset($options['model']) ? $options['model'] : 'gpt-3.5-turbo';
        
        $models = array(
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-4-turbo' => 'GPT-4 Turbo'
        );
        
        echo '<select id="model" name="thinkany_wp_ai_search_settings[model]">';
        foreach ($models as $model_id => $model_name) {
            echo '<option value="' . esc_attr($model_id) . '" ' . selected($model, $model_id, false) . '>' . esc_html($model_name) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . __('Select the OpenAI model to use for search enhancement.', 'thinkany-wp-ai-search') . '</p>';
    }
    
    public function render_cache_duration_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 6;
        
        echo '<input type="number" id="cache_duration" name="thinkany_wp_ai_search_settings[cache_duration]" value="' . esc_attr($cache_duration) . '" min="1" max="72" />';
        echo '<p class="description">' . __('How long to cache AI-enhanced search results (in hours).', 'thinkany-wp-ai-search') . '</p>';
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
        </div>
        <?php
    }

    public function render_debug_field() {
        $options = get_option('thinkany_wp_ai_search_settings');
        $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        
        echo '<input type="checkbox" id="debug" name="thinkany_wp_ai_search_settings[debug]" value="1" ' . checked($debug, true, false) . ' />';
        echo '<p class="description">' . __('Enable detailed logging for troubleshooting. Check server logs for output.', 'thinkany-wp-ai-search') . '</p>';
    }
}