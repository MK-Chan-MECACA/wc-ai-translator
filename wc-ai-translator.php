<?php
/**
 * Plugin Name: AI Product Title Translator – Bahasa Malaysia
 * Plugin URI: https://github.com/MK-Chan-MECACA/wc-ai-translator
 * Description: Translate WooCommerce product titles to Bahasa Malaysia using AI (OpenAI, Claude, Gemini)
 * Version: 1.0.1
 * Author: MK CHAN
 * License: GPL v2 or later
 * Text Domain: wc-ai-translator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WCAIT_VERSION', '1.0.1');
define('WCAIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WCAIT_PLUGIN_URL', plugin_dir_url(__FILE__));

class WC_AI_Translator {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // Add translate button to product edit page
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_translate_button'));

        // AJAX handlers
        add_action('wp_ajax_wcait_translate_title', array($this, 'ajax_translate_title'));
        add_action('wp_ajax_wcait_bulk_translate', array($this, 'ajax_bulk_translate'));
        add_action('wp_ajax_wcait_test_connection', array($this, 'ajax_test_connection'));

        // Add bulk action to product list
        add_filter('bulk_actions-edit-product', array($this, 'add_bulk_translate_action'));
        add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_translate_action'), 10, 3);

        // Add translate button column to product list
        add_filter('manage_product_posts_columns', array($this, 'add_translate_column'));
        add_action('manage_product_posts_custom_column', array($this, 'render_translate_column'), 10, 2);

        // Admin notices for bulk translate results
        add_action('admin_notices', array($this, 'show_bulk_translate_notices'));
    }

    public function init() {
        load_plugin_textdomain('wc-ai-translator', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('AI Title Translator', 'wc-ai-translator'),
            __('AI Title Translator', 'wc-ai-translator'),
            'manage_woocommerce',
            'wc-ai-translator',
            array($this, 'settings_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, array('post.php', 'post-new.php', 'edit.php')) ||
            (isset($_GET['page']) && $_GET['page'] === 'wc-ai-translator')) {

            wp_enqueue_script(
                'wcait-admin',
                WCAIT_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                WCAIT_VERSION,
                true
            );

            wp_localize_script('wcait-admin', 'wcait_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wcait_nonce'),
                'translating' => __('Translating...', 'wc-ai-translator'),
                'translate' => __('Translate to BM', 'wc-ai-translator'),
                'error' => __('Translation failed', 'wc-ai-translator'),
                'bulk_confirm' => __('Are you sure you want to translate the selected products? This action cannot be undone.', 'wc-ai-translator'),
                'processing' => __('Processing translations...', 'wc-ai-translator'),
                'completed' => __('Translation completed!', 'wc-ai-translator'),
                'cancelled' => __('Translation cancelled!', 'wc-ai-translator')
            ));

            wp_enqueue_style(
                'wcait-admin',
                WCAIT_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WCAIT_VERSION
            );
        }
    }

    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }

        $ai_provider = get_option('wcait_ai_provider', 'openai');
        $api_key = get_option('wcait_api_key', '');
        ?>
        <div class="wrap">
            <h1><?php _e('AI Product Title Translator Settings', 'wc-ai-translator'); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field('wcait_settings', 'wcait_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="ai_provider"><?php _e('AI Provider', 'wc-ai-translator'); ?></label>
                        </th>
                        <td>
                            <select name="ai_provider" id="ai_provider">
                                <option value="openai" <?php selected($ai_provider, 'openai'); ?>>OpenAI (GPT)</option>
                                <option value="claude" <?php selected($ai_provider, 'claude'); ?>>Anthropic Claude</option>
                                <option value="gemini" <?php selected($ai_provider, 'gemini'); ?>>Google Gemini</option>
                                <option value="mesolitica" <?php selected($ai_provider, 'mesolitica'); ?>>Mesolitica</option>
                            </select>
                            <p class="description" id="model-info">
                                <?php if ($ai_provider === 'openai'): ?>
                                    <?php _e('Using model: <strong>gpt-4o-mini</strong>', 'wc-ai-translator'); ?>
                                <?php elseif ($ai_provider === 'claude'): ?>
                                    <?php _e('Using model: <strong>claude-3-haiku-20240307</strong>', 'wc-ai-translator'); ?>
                                <?php elseif ($ai_provider === 'gemini'): ?>
                                    <?php _e('Using model: <strong>gemini-pro</strong>', 'wc-ai-translator'); ?>
                                <?php elseif ($ai_provider === 'mesolitica'): ?>
                                    <?php _e('Using model: <strong>base (Malay-focused)</strong>', 'wc-ai-translator'); ?>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e('API Key', 'wc-ai-translator'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="api_key" id="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" />
                            <button type="button" id="test-connection" class="button button-secondary"><?php _e('Test Connection', 'wc-ai-translator'); ?></button>
                            <span id="test-result"></span>
                            <p class="description"><?php _e('Enter your API key for the selected AI provider', 'wc-ai-translator'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <div class="wcait-instructions">
                <h2><?php _e('Instructions', 'wc-ai-translator'); ?></h2>
                <ol>
                    <li><?php _e('Select your preferred AI provider', 'wc-ai-translator'); ?></li>
                    <li><?php _e('Enter your API key for the selected provider', 'wc-ai-translator'); ?></li>
                    <li><?php _e('Go to individual product pages and click "Translate to BM" button', 'wc-ai-translator'); ?></li>
                    <li><?php _e('Or use bulk translate feature from the product list page', 'wc-ai-translator'); ?></li>
                </ol>
            </div>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#ai_provider').on('change', function() {
                    var provider = $(this).val();
                    var modelInfo = '';
                    switch(provider) {
                        case 'openai':
                            modelInfo = '<?php _e('Using model: <strong>gpt-4o-mini</strong>', 'wc-ai-translator'); ?>';
                            break;
                        case 'claude':
                            modelInfo = '<?php _e('Using model: <strong>claude-3-haiku-20240307</strong>', 'wc-ai-translator'); ?>';
                            break;
                        case 'gemini':
                            modelInfo = '<?php _e('Using model: <strong>gemini-pro</strong>', 'wc-ai-translator'); ?>';
                            break;
                        case 'mesolitica':
                            modelInfo = '<?php _e('Using model: <strong>base (Malay-focused)</strong>', 'wc-ai-translator'); ?>';
                            break;
                    }
                    $('#model-info').html(modelInfo);
                });

                // Test connection button
                $('#test-connection').on('click', function() {
                    var $btn = $(this);
                    var $result = $('#test-result');
                    var apiKey = $('#api_key').val();
                    var provider = $('#ai_provider').val();
                    if (!apiKey) {
                        $result.html('<span style="color: red; margin-left: 10px;"><?php _e('Please enter an API key', 'wc-ai-translator'); ?></span>');
                        return;
                    }
                    $btn.prop('disabled', true).text('<?php _e('Testing...', 'wc-ai-translator'); ?>');
                    $result.html('');
                    $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                        action: 'wcait_test_connection',
                        api_key: apiKey,
                        provider: provider,
                        nonce: '<?php echo wp_create_nonce('wcait_test_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            $result.html('<span style="color: green; margin-left: 10px;">✓ ' + response.data.message + '</span>');
                        } else {
                            $result.html('<span style="color: red; margin-left: 10px;">✗ ' + response.data + '</span>');
                        }
                        $btn.prop('disabled', false).text('<?php _e('Test Connection', 'wc-ai-translator'); ?>');
                    }).fail(function() {
                        $result.html('<span style="color: red; margin-left: 10px;">✗ <?php _e('Connection failed', 'wc-ai-translator'); ?></span>');
                        $btn.prop('disabled', false).text('<?php _e('Test Connection', 'wc-ai-translator'); ?>');
                    });
                });
            });
            </script>
        </div>
        <?php
    }

    private function save_settings() {
        if (!isset($_POST['wcait_settings_nonce']) ||
            !wp_verify_nonce($_POST['wcait_settings_nonce'], 'wcait_settings')) {
            return;
        }
        update_option('wcait_ai_provider', sanitize_text_field($_POST['ai_provider']));
        update_option('wcait_api_key', sanitize_text_field($_POST['api_key']));
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'wc-ai-translator') . '</p></div>';
    }

    public function add_translate_button() {
        global $post;
        ?>
        <div class="options_group wcait-translate-group">
            <p class="form-field">
                <label><?php _e('AI Translation', 'wc-ai-translator'); ?></label>
                <button type="button" class="button wcait-translate-btn" data-product-id="<?php echo $post->ID; ?>">
                    <?php _e('Translate to BM', 'wc-ai-translator'); ?>
                </button>
                <span class="wcait-status"></span>
            </p>
        </div>
        <?php
    }

    public function add_translate_column($columns) {
        $columns['translate'] = __('Translate', 'wc-ai-translator');
        return $columns;
    }

    public function render_translate_column($column, $post_id) {
        if ($column === 'translate') {
            ?>
            <button type="button" class="button button-small wcait-translate-single" data-product-id="<?php echo $post_id; ?>">
                <?php _e('Translate to BM', 'wc-ai-translator'); ?>
            </button>
            <?php
        }
    }

    public function add_bulk_translate_action($actions) {
        $actions['translate_to_bm'] = __('Translate titles to Bahasa Malaysia', 'wc-ai-translator');
        return $actions;
    }

    public function handle_bulk_translate_action($redirect_to, $action, $post_ids) {
        if ($action !== 'translate_to_bm') {
            return $redirect_to;
        }
        if (empty($post_ids) || !is_array($post_ids)) {
            $redirect_to = add_query_arg('wcait_bulk_error', 'no_products', $redirect_to);
            return $redirect_to;
        }
        // Filter valid product IDs
        $product_ids = array();
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_type === 'product') {
                $product_ids[] = intval($post_id);
            }
        }
        if (empty($product_ids)) {
            $redirect_to = add_query_arg('wcait_bulk_error', 'no_valid_products', $redirect_to);
            return $redirect_to;
        }
        $api_key = get_option('wcait_api_key', '');
        if (empty($api_key)) {
            $redirect_to = add_query_arg('wcait_bulk_error', 'no_api_key', $redirect_to);
            return $redirect_to;
        }
        // Store product IDs in a transient for the current user
        $user_id = get_current_user_id();
        set_transient('wcait_bulk_product_ids_' . $user_id, $product_ids, 60 * 10); // valid for 10 mins

        $redirect_to = add_query_arg(array(
            'wcait_bulk_translate' => 1,
            'wcait_count' => count($product_ids)
        ), $redirect_to);
        return $redirect_to;
    }

    public function show_bulk_translate_notices() {
        if (isset($_GET['wcait_bulk_error'])) {
            $error = sanitize_text_field($_GET['wcait_bulk_error']);
            $message = '';
            switch ($error) {
                case 'no_products':
                    $message = __('No products selected for translation.', 'wc-ai-translator');
                    break;
                case 'no_valid_products':
                    $message = __('No valid products found in selection.', 'wc-ai-translator');
                    break;
                case 'no_api_key':
                    $message = __('API key not configured. Please configure your AI provider settings first.', 'wc-ai-translator');
                    break;
            }
            if ($message) {
                echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
        if (isset($_GET['wcait_bulk_success'])) {
            $success_data = json_decode(stripslashes($_GET['wcait_bulk_success']), true);
            if ($success_data && is_array($success_data)) {
                $total = intval($success_data['total']);
                $success = intval($success_data['success']);
                $failed = intval($success_data['failed']);
                $message = sprintf(
                    __('Bulk translation completed! Success: %d, Failed: %d out of %d total products.', 'wc-ai-translator'),
                    $success,
                    $failed,
                    $total
                );
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
        }
    }

    // --- AJAX HANDLERS ---

    public function ajax_translate_title() {
        check_ajax_referer('wcait_nonce', 'nonce');
        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(__('Product not found', 'wc-ai-translator'));
        }
        $original_title = $product->get_name();
        $translated_title = $this->translate_text($original_title);
        if ($translated_title) {
            $product->set_name($translated_title);
            $product->save();
            wp_send_json_success(array(
                'title' => $translated_title,
                'message' => __('Title translated successfully', 'wc-ai-translator')
            ));
        } else {
            wp_send_json_error(__('Translation failed', 'wc-ai-translator'));
        }
    }

    public function ajax_bulk_translate() {
        check_ajax_referer('wcait_nonce', 'nonce');
        $user_id = get_current_user_id();
        $product_ids = get_transient('wcait_bulk_product_ids_' . $user_id);
        if (empty($product_ids)) {
            wp_send_json_error(__('No products found for bulk translation', 'wc-ai-translator'));
        }
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 5;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $current_batch = array_slice($product_ids, $offset, $batch_size);
        if (empty($current_batch)) {
            delete_transient('wcait_bulk_product_ids_' . $user_id);
            wp_send_json_success(array(
                'completed' => true,
                'message' => __('All translations completed', 'wc-ai-translator')
            ));
        }
        $results = array();
        $success_count = 0;
        $error_count = 0;
        foreach ($current_batch as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                $original_title = $product->get_name();
                $translated_title = $this->translate_text($original_title);
                if ($translated_title && $translated_title !== $original_title) {
                    $product->set_name($translated_title);
                    $product->save();
                    $results[] = array(
                        'id' => $product_id,
                        'success' => true,
                        'original_title' => $original_title,
                        'translated_title' => $translated_title
                    );
                    $success_count++;
                } else {
                    $results[] = array(
                        'id' => $product_id,
                        'success' => false,
                        'original_title' => $original_title,
                        'error' => __('Translation failed or no change needed', 'wc-ai-translator')
                    );
                    $error_count++;
                }
            } else {
                $results[] = array(
                    'id' => $product_id,
                    'success' => false,
                    'error' => __('Product not found', 'wc-ai-translator')
                );
                $error_count++;
            }
            usleep(100000); // 0.1 second delay
        }
        wp_send_json_success(array(
            'results' => $results,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'processed' => $offset + count($current_batch),
            'total' => count($product_ids),
            'has_more' => ($offset + count($current_batch)) < count($product_ids)
        ));
    }

    public function ajax_test_connection() {
        check_ajax_referer('wcait_test_nonce', 'nonce');
        $provider = sanitize_text_field($_POST['provider']);
        $api_key = sanitize_text_field($_POST['api_key']);
        if (empty($api_key)) {
            wp_send_json_error(__('API key is required', 'wc-ai-translator'));
        }
        $test_text = "Hello World";
        $prompt = "Translate the following to Bahasa Malaysia: " . $test_text;
        $result = false;
        $error_message = '';
        switch ($provider) {
            case 'openai':
                $result = $this->test_openai_connection($prompt, $api_key, $error_message);
                break;
            case 'claude':
                $result = $this->test_claude_connection($prompt, $api_key, $error_message);
                break;
            case 'gemini':
                $result = $this->test_gemini_connection($prompt, $api_key, $error_message);
                break;
            case 'mesolitica':
                $result = $this->test_mesolitica_connection($test_text, $api_key, $error_message);
                break;
        }
        if ($result) {
            wp_send_json_success(array(
                'message' => sprintf(__('Connection successful! Test translation: "%s"', 'wc-ai-translator'), $result)
            ));
        } else {
            wp_send_json_error($error_message ?: __('Connection failed. Please check your API key.', 'wc-ai-translator'));
        }
    }

    // --- TRANSLATE METHODS ---

    private function translate_text($text) {
        $provider = get_option('wcait_ai_provider', 'openai');
        $api_key = get_option('wcait_api_key', '');
        if (empty($api_key)) {
            return false;
        }
        if ($provider === 'mesolitica') {
            return $this->translate_with_mesolitica($text, $api_key);
        }
        $prompt = "Translate the following product title to Bahasa Malaysia. Only provide the translation without any explanation: " . $text;
        switch ($provider) {
            case 'openai':
                return $this->translate_with_openai($prompt, $api_key);
            case 'claude':
                return $this->translate_with_claude($prompt, $api_key);
            case 'gemini':
                return $this->translate_with_gemini($prompt, $api_key);
            default:
                return false;
        }
    }

    private function translate_with_openai($prompt, $api_key) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.3,
                'max_tokens' => 100
            )),
            'timeout' => 30
        ));
        if (is_wp_error($response)) {
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['choices'][0]['message']['content']) ? trim($body['choices'][0]['message']['content']) : false;
    }

    private function translate_with_claude($prompt, $api_key) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 100
            )),
            'timeout' => 30
        ));
        if (is_wp_error($response)) {
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['content'][0]['text']) ? trim($body['content'][0]['text']) : false;
    }

    private function translate_with_gemini($prompt, $api_key) {
        $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array(
                                'text' => $prompt
                            )
                        )
                    )
                )
            )),
            'timeout' => 30
        ));
        if (is_wp_error($response)) {
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['candidates'][0]['content']['parts'][0]['text']) ? trim($body['candidates'][0]['content']['parts'][0]['text']) : false;
    }

    private function translate_with_mesolitica($text, $api_key) {
        $response = wp_remote_post('https://api.mesolitica.com/translation', array(
            'headers' => array(
                'accept' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'input' => $text,
                'to_lang' => 'ms',
                'model' => 'base',
                'temperature' => 0,
                'top_p' => 1,
                'top_k' => 1,
                'repetition_penalty' => 1.1
            )),
            'timeout' => 30
        ));
        if (is_wp_error($response)) {
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['result']) ? trim($body['result']) : false;
    }

    // --- TEST CONNECTION METHODS ---

    private function test_openai_connection($prompt, $api_key, &$error_message) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'temperature' => 0.3,
                'max_tokens' => 50
            )),
            'timeout' => 15
        ));
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            $error_message = $body['error']['message'] ?? __('Invalid API key or request', 'wc-ai-translator');
            return false;
        }
        return isset($body['choices'][0]['message']['content']) ? trim($body['choices'][0]['message']['content']) : false;
    }

    private function test_claude_connection($prompt, $api_key, &$error_message) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key' => $api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'claude-3-haiku-20240307',
                'messages' => array(
                    array(
                        'role' => 'user',
                        'content' => $prompt
                    )
                ),
                'max_tokens' => 50
            )),
            'timeout' => 15
        ));
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            $error_message = $body['error']['message'] ?? __('Invalid API key or request', 'wc-ai-translator');
            return false;
        }
        return isset($body['content'][0]['text']) ? trim($body['content'][0]['text']) : false;
    }

    private function test_gemini_connection($prompt, $api_key, &$error_message) {
        $response = wp_remote_post('https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array(
                                'text' => $prompt
                            )
                        )
                    )
                )
            )),
            'timeout' => 15
        ));
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            $error_message = $body['error']['message'] ?? __('Invalid API key or request', 'wc-ai-translator');
            return false;
        }
        return isset($body['candidates'][0]['content']['parts'][0]['text']) ? trim($body['candidates'][0]['content']['parts'][0]['text']) : false;
    }

    private function test_mesolitica_connection($text, $api_key, &$error_message) {
        $response = wp_remote_post('https://api.mesolitica.com/translation', array(
            'headers' => array(
                'accept' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'input' => $text,
                'to_lang' => 'ms',
                'model' => 'base',
                'temperature' => 0,
                'top_p' => 1,
                'top_k' => 1,
                'repetition_penalty' => 1.1
            )),
            'timeout' => 15
        ));
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['error'])) {
            $error_message = $body['error']['message'] ?? __('Invalid API key or request', 'wc-ai-translator');
            return false;
        }
        return isset($body['result']) ? trim($body['result']) : false;
    }
}

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('WooCommerce')) {
        WC_AI_Translator::get_instance();
    }
});

// Create assets folder structure on activation
register_activation_hook(__FILE__, function() {
    $upload_dir = wp_upload_dir();
    $plugin_dir = $upload_dir['basedir'] . '/wc-ai-translator';
    if (!file_exists($plugin_dir)) {
        wp_mkdir_p($plugin_dir);
    }
});
