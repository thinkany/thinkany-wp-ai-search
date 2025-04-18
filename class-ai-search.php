<?php
/**
 * AI-Enhanced Search functionality for ThinkAny
 */

class ThinkAny_WP_AI_Search {
    private $api_key;
    private $model;
    private $cache_duration;
    private static $instance = null;

    private function __construct() {
        $options = get_option('thinkany_wp_ai_search_settings');
        
        // Get and decrypt API key
        $encrypted_api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $this->api_key = ThinkAny_WP_AI_Search_Admin::decrypt_api_key($encrypted_api_key);
        
        // Get other settings
        $this->model = isset($options['model']) ? $options['model'] : 'gpt-3.5-turbo';
        $this->cache_duration = isset($options['cache_duration']) ? intval($options['cache_duration']) : 6;
        
        // Add filters for search enhancement
        add_filter('posts_search', array($this, 'enhance_search_query'), 999, 2);
        add_filter('posts_where', array($this, 'modify_search_where'), 999, 2);
        add_filter('posts_request', array($this, 'modify_search_request'), 10, 2);
        //add_filter('posts_clauses', array($this, 'modify_search_relevance'), 10, 2);
        //add_filter('posts_orderby', array($this, 'modify_search_orderby'), 10, 2);
        add_filter('the_posts', array($this, 'debug_search_results'), 10, 2);
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enhance search query with AI assistance
     */
    public function enhance_search_query($search, $query) {
        // Get settings
        $options = get_option('thinkany_wp_ai_search_settings');
        $enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        
        // Only log the original search clause but don't modify it
        // The actual modification will happen in modify_search_where
        if (is_search() && $query->is_main_query() && $enabled && $debug) {
            error_log('ThinkAny WP AI Search: Original search clause in posts_search: ' . $search);
        }
        
        // Return the original search clause
        return $search;
    }

    /**
     * Modify the WHERE clause of the search query
     */
    public function modify_search_where($where, $query) {
        global $wpdb;
        
        // Get settings
        $options = get_option('thinkany_wp_ai_search_settings');
        $enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        
        // Only modify search on main query
        if (!is_search() || !$query->is_main_query() || !$enabled) {
            return $where;
        }
        
        $search_term = get_search_query();
        
        if ($debug) {
            error_log('ThinkAny WP AI Search: Original WHERE clause: ' . $where);
        }
        
        // If API key is available, try to get AI-enhanced terms
        if (!empty($this->api_key)) {
            $enhanced_terms = $this->get_ai_enhanced_terms($search_term);
            
            // Make sure the original search term is included
            if (!in_array($search_term, $enhanced_terms)) {
                $enhanced_terms[] = $search_term;
            }
            
            if ($debug) {
                error_log('ThinkAny WP AI Search: Enhanced terms for WHERE: ' . implode(', ', $enhanced_terms));
            }
        } else {
            $enhanced_terms = array($search_term);
        }
        
        // Build the search conditions
        $search_conditions = array();
        
        foreach ($enhanced_terms as $term) {
            $term = trim($term);
            if (empty($term)) continue;
            
            $escaped_term = $wpdb->esc_like($term);
            $search_conditions[] = "({$wpdb->posts}.post_title LIKE '%{$escaped_term}%' OR {$wpdb->posts}.post_content LIKE '%{$escaped_term}%' OR {$wpdb->posts}.post_excerpt LIKE '%{$escaped_term}%')";
        }
        
        if (!empty($search_conditions)) {
            // Instead of trying to replace parts of the WHERE clause, 
            // let's extract the post_password check and post_type conditions
            
            // Extract post_password check
            $password_check = '';
            if (preg_match('/AND \(wp_posts\.post_password = \'[^\']*\'\)/', $where, $matches)) {
                $password_check = $matches[0];
            }
            
            // Extract post_type conditions
            $post_type_conditions = '';
            if (preg_match('/AND \(\(wp_posts\.post_type = \'[^\']*\'.*?\)\)/', $where, $matches)) {
                $post_type_conditions = $matches[0];
            }
            
            // Build a new WHERE clause
            $new_where = " AND (" . implode(" OR ", $search_conditions) . ")";
            
            // Add password check if found
            if (!empty($password_check)) {
                $new_where .= " " . $password_check;
            }
            
            // Add post_type conditions if found
            if (!empty($post_type_conditions)) {
                $new_where .= " " . $post_type_conditions;
            }
            
            if ($debug) {
                error_log('ThinkAny WP AI Search: New WHERE clause: ' . $new_where);
            }
            
            // Replace the entire WHERE clause
            $where = $new_where;
        }
        
        return $where;
    }

    /**
     * Get AI-enhanced search terms using OpenAI
     */
    private function get_ai_enhanced_terms($search_term) {
        $cache_key = 'thinkany_wp_ai_search_' . md5($search_term);
        $cached_result = get_transient($cache_key);

        if (false !== $cached_result) {
            error_log('ThinkAny WP AI Search: Using cached results for query: ' . $search_term);
            return $cached_result;
        }

        error_log('ThinkAny WP AI Search: Making API request for query: ' . $search_term);
        
        try {
            $request_body = array(
                'model' => $this->model,
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You are a search enhancement assistant. Given a search query, return 3-5 related search terms that would help find relevant content. Return only the terms separated by commas, no other text.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => $search_term
                    )
                ),
                'temperature' => 0.3,
                'max_tokens' => 100
            );
            
            error_log('ThinkAny WP AI Search: Request body: ' . json_encode($request_body));
            
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions',array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $this->api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode($request_body)
            ));

            if (is_wp_error($response)) {
                error_log('ThinkAny WP AI Search Error: ' . $response->get_error_message());
                return array($search_term);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log('ThinkAny WP AI Search: Response code: ' . $response_code);
            error_log('ThinkAny WP AI Search: Response body: ' . $response_body);
            
            if ($response_code !== 200) {
                error_log('ThinkAny WP AI Search Error: API returned status ' . $response_code . '. Response: ' . $response_body);
                return array($search_term);
            }

            $body = json_decode($response_body, true);
            
            if (empty($body['choices'][0]['message']['content'])) {
                error_log('ThinkAny WP AI Search Error: Empty response from OpenAI. Full response: ' . $response_body);
                return array($search_term);
            }

            $enhanced_terms = array_map('trim', explode(',', $body['choices'][0]['message']['content']));
            $enhanced_terms[] = $search_term;
            
            error_log('ThinkAny WP AI Search: Enhanced terms: ' . implode(', ', $enhanced_terms));
            
            // Cache for specified duration
            set_transient($cache_key, $enhanced_terms, $this->cache_duration * HOUR_IN_SECONDS);

            if (isset($options['debug']) && $options['debug']) {
                error_log('ThinkAny WP AI Search: Enhanced terms before returning: ' . implode(', ', $enhanced_terms));
            }
            
            return $enhanced_terms;

        } catch (Exception $e) {
            error_log('ThinkAny WP AI Search Error: ' . $e->getMessage());
            return array($search_term);
        }
    }

    /**
     * Build enhanced MySQL search query
     */
    private function build_enhanced_search_query($terms, $wpdb, $original_term = '') {
        $options = get_option('thinkany_wp_ai_search_settings');
        $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        
        if ($debug) {
            error_log('ThinkAny WP AI Search: Building query with terms: ' . implode(', ', $terms));
        }
        
        // If we have an original term, make sure it's the first one in the array for relevance
        if (!empty($original_term)) {
            // Remove the original term if it exists in the array to avoid duplication
            $terms = array_filter($terms, function($term) use ($original_term) {
                return strtolower(trim($term)) !== strtolower(trim($original_term));
            });
            
            // Add the original term at the beginning of the array
            array_unshift($terms, $original_term);
            
            if ($debug) {
                error_log('ThinkAny WP AI Search: Reordered terms with original first: ' . implode(', ', $terms));
            }
        }
        
        $search_conditions = array();
        
        foreach ($terms as $term) {
            $term = trim($term);
            if (empty($term)) continue;
            
            // Direct approach without using wpdb->prepare for LIKE statements
            $escaped_term = $wpdb->esc_like($term);
            $search_conditions[] = "({$wpdb->posts}.post_title LIKE '%{$escaped_term}%' OR {$wpdb->posts}.post_content LIKE '%{$escaped_term}%' OR {$wpdb->posts}.post_excerpt LIKE '%{$escaped_term}%')";
        }
        
        if (!empty($search_conditions)) {
            // Use OR between all conditions to include results from any term
            $search_sql = " AND (" . implode(" OR ", $search_conditions) . ")";
            
            if ($debug) {
                error_log('ThinkAny WP AI Search: Built enhanced query with ' . count($terms) . ' terms');
                error_log('ThinkAny WP AI Search: SQL: ' . $search_sql);
            }
            
            return $search_sql;
        }
        
        return '';
    }

    /**
     * Modify the entire search request SQL query
     */
    public function modify_search_request($sql, $query) {
        global $wpdb;
        
        // Get settings
        $options = get_option('thinkany_wp_ai_search_settings');
        $enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        
        // Only modify search on main query
        if (!is_search() || !$query->is_main_query() || !$enabled) {
            return $sql;
        }
        
        $search_term = get_search_query();
        
        if ($debug) {
            error_log('ThinkAny WP AI Search: Original SQL query: ' . $sql);
        }
        
        // If API key is available, try to get AI-enhanced terms
        if (!empty($this->api_key)) {
            $enhanced_terms = $this->get_ai_enhanced_terms($search_term);
            
            // Make sure the original search term is included
            if (!in_array($search_term, $enhanced_terms)) {
                $enhanced_terms[] = $search_term;
            }
            
            if ($debug) {
                error_log('ThinkAny WP AI Search: Using cached results for query: ' . $search_term);
            }
        } else {
            $enhanced_terms = array($search_term);
        }
        
        // Reorder enhanced terms to prioritize the original search term
        $reordered_terms = array();
        
        // First add the original search term
        $reordered_terms[] = $search_term;
        
        // Then add all other terms
        foreach ($enhanced_terms as $term) {
            if (strtolower(trim($term)) !== strtolower(trim($search_term))) {
                $reordered_terms[] = $term;
            }
        }
        
        // Build the CASE parts for the ORDER BY clause
        $case_parts = array();
        $priority = 1;
        
        // First prioritize the original search term with special weighting
        $escaped_search_term = $wpdb->esc_like($search_term);
        
        // Exact match of original search term in title (highest priority)
        $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) = LOWER('{$escaped_search_term}') THEN {$priority}";
        $priority += 1;
        
        // Title starts with original search term (very high priority)
        $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) LIKE LOWER('{$escaped_search_term}%') THEN {$priority}";
        $priority += 1;
        
        // Original search term appears in title (high priority)
        $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) LIKE LOWER('%{$escaped_search_term}%') THEN {$priority}";
        $priority += 1;
        
        // Title ends with original search term
        $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) LIKE LOWER('%{$escaped_search_term}') THEN {$priority}";
        $priority += 1;
        
        // Now add all other terms with lower priority
        foreach ($reordered_terms as $term) {
            if (strtolower(trim($term)) === strtolower(trim($search_term))) {
                // Skip the original search term as we've already handled it
                continue;
            }
            
            $term = trim($term);
            if (empty($term)) continue;
            
            $escaped_term = $wpdb->esc_like($term);
            
            // Exact title match
            $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) = LOWER('{$escaped_term}') THEN {$priority}";
            $priority += 1;
            
            // Title starts with term
            $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) LIKE LOWER('{$escaped_term}%') THEN {$priority}";
            $priority += 1;
            
            // Term is in title
            $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) LIKE LOWER('%{$escaped_term}%') THEN {$priority}";
            $priority += 1;
            
            // Title ends with term
            $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_title) LIKE LOWER('%{$escaped_term}') THEN {$priority}";
            $priority += 1;
        }
        
        // Add content and excerpt matches with lower priority
        foreach ($reordered_terms as $term) {
            $term = trim($term);
            if (empty($term)) continue;
            
            $escaped_term = $wpdb->esc_like($term);
            
            // Term is in content
            $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_content) LIKE LOWER('%{$escaped_term}%') THEN {$priority}";
            $priority += 1;
            
            // Term is in excerpt
            $case_parts[] = "WHEN LOWER({$wpdb->posts}.post_excerpt) LIKE LOWER('%{$escaped_term}%') THEN {$priority}";
            $priority += 1;
        }
        
        // Add a default case
        $case_parts[] = "ELSE 999";
        
        // Build the ORDER BY clause
        $orderby = "CASE " . implode(" ", $case_parts) . " END, {$wpdb->posts}.post_date DESC";
        
        // Clean up the SQL query by removing extra newlines and whitespace
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        // Extract LIMIT clause if it exists
        $limit_clause = '';
        if (preg_match('/\bLIMIT\s+(\d+(?:\s*,\s*\d+)?)\s*$/i', $sql, $matches)) {
            $limit_clause = $matches[0];
            // Remove the LIMIT clause from the SQL for now
            $sql = preg_replace('/\bLIMIT\s+(\d+(?:\s*,\s*\d+)?)\s*$/i', '', $sql);
        }
        
        // Build the new WHERE clause with all enhanced terms
        $search_conditions = array();
        
        foreach ($reordered_terms as $term) {
            $term = trim($term);
            if (empty($term)) continue;
            
            $escaped_term = $wpdb->esc_like($term);
            $search_conditions[] = "({$wpdb->posts}.post_title LIKE '%{$escaped_term}%' OR {$wpdb->posts}.post_content LIKE '%{$escaped_term}%' OR {$wpdb->posts}.post_excerpt LIKE '%{$escaped_term}%')";
        }
        
        // Combine all search conditions with OR
        $new_where = " AND (" . implode(' OR ', $search_conditions) . ")";
        
        // Add post password check
        $new_where .= " AND ({$wpdb->posts}.post_password = '')";
        
        // Add post type and status conditions for both posts and pages
        $new_where .= " AND (({$wpdb->posts}.post_type = 'page' AND ({$wpdb->posts}.post_status = 'publish' OR {$wpdb->posts}.post_status = 'acf-disabled')) OR ({$wpdb->posts}.post_type = 'post' AND ({$wpdb->posts}.post_status = 'publish' OR {$wpdb->posts}.post_status = 'acf-disabled')))";
        
        // Construct the new SQL query
        $new_sql = "SELECT SQL_CALC_FOUND_ROWS {$wpdb->posts}.* FROM {$wpdb->posts} WHERE 1=1" . $new_where . " ORDER BY " . $orderby;
        
        // Add the LIMIT clause back to the end of the query
        if (!empty($limit_clause)) {
            $new_sql .= ' ' . $limit_clause;
        }
        
        if ($debug) {
            error_log('ThinkAny WP AI Search: Modified SQL query: ' . $new_sql);
        }
        
        return $new_sql;
    }

    /**
     * Add a debugging method to check if the plugin is actually expanding search results
     */
    public function debug_search_results($posts, $query) {
        if (!is_search() || !$query->is_main_query()) {
            return $posts;
        }
        
        // Get settings
        $options = get_option('thinkany_wp_ai_search_settings');
        $enabled = isset($options['enabled']) ? (bool)$options['enabled'] : false;
        $debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        
        if (!$enabled || !$debug) {
            return $posts;
        }
        
        // Log the number of search results
        $count = count($posts);
        error_log('ThinkAny WP AI Search: Found ' . $count . ' search results for query: ' . get_search_query());
        
        // Log the titles of the search results
        if ($count > 0) {
            $titles = array();
            foreach ($posts as $post) {
                $titles[] = $post->post_title;
            }
            error_log('ThinkAny WP AI Search: Search result titles: ' . implode(', ', $titles));
        }
        
        return $posts;
    }
}