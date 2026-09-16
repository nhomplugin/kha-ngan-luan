<?php
/**
 * Plugin Name: Chatbot AI DeepSeek (AI Customer Assistant)
 * Description: Chatbot hỗ trợ khách hàng tự động thông minh sử dụng DeepSeek API (deepseek-flash, deepseek-v4-pro). Tự động suy luận, phân tích ngữ cảnh và trò chuyện tự nhiên với khách hàng 24/7 theo định hướng của quản trị viên.
 * Version: 1.1.0
 * Author: Antigravity
 * Text Domain: chatbot-ai-gemini
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WP_Gemini_AI_Chatbot {

    const VERSION = '1.1.0';
    const OPTION_SETTINGS = 'wp_gemini_chatbot_settings';
    const OPTION_LOGS = 'wp_gemini_chatbot_logs';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, [__CLASS__, 'on_activate']);

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_admin_actions']);

        // Frontend Widget
        add_action('wp_footer', [$this, 'render_frontend_widget']);

        // AJAX handlers
        add_action('wp_ajax_gemini_ai_send_message', [$this, 'ajax_handle_message']);
        add_action('wp_ajax_nopriv_gemini_ai_send_message', [$this, 'ajax_handle_message']);

        add_action('wp_ajax_gemini_ai_stream_message', [$this, 'ajax_stream_message']);
        add_action('wp_ajax_nopriv_gemini_ai_stream_message', [$this, 'ajax_stream_message']);

        add_action('wp_ajax_gemini_ai_test_api', [$this, 'ajax_test_api']);

        // Auto initialize settings if not exists
        if (!get_option(self::OPTION_SETTINGS)) {
            self::on_activate();
        }
    }

    public static function on_activate() {
        $default_key = 'sk-4d92ceabf90e47cfbe6286db7786ef1d';
        if (!get_option(self::OPTION_SETTINGS)) {
            // Nếu plugin chatbot thường cũng đang chạy, đặt launcher AI ở vị trí thuận tiện (hoặc góc trái) để không bị đè
            $is_faq_bot_active = function_exists('is_plugin_active') && is_plugin_active('chatbot-support.php');
            $default_bottom = $is_faq_bot_active ? '96' : '24';

            $default_settings = [
                'api_key' => $default_key,
                'model' => 'deepseek-flash',
                'system_instruction' => "Bạn là chuyên viên tư vấn khách hàng thông minh, thân thiện và nhiệt tình của cửa hàng/doanh nghiệp. Nhiệm vụ của bạn là giải đáp câu hỏi của khách hàng bằng tiếng Việt một cách lịch sự, ngắn gọn, dễ hiểu và chuẩn xác. Nếu khách hỏi về giá hoặc muốn đặt hàng, hãy khuyến khích khách để lại số điện thoại hoặc liên hệ hotline để được hỗ trợ tốt nhất.",
                'temperature' => 0.7,
                'bot_name' => 'Stylist AI Thời Trang ✨',
                'welcome_msg' => 'Xin chào! Tôi là Trợ lý AI thông minh được phát triển bởi DeepSeek AI. Tôi có thể giúp gì cho bạn hôm nay?',
                'primary_color' => '#0ea5e9',
                'secondary_color' => '#2563eb',
                'bot_position' => 'right',
                'offset_bottom' => $default_bottom,
                'enable_widget' => '1',
                'max_history_turns' => 8
            ];
            update_option(self::OPTION_SETTINGS, $default_settings);
        }

        if (get_option(self::OPTION_LOGS) === false) {
            update_option(self::OPTION_LOGS, []);
        }
    }

    public static function get_settings() {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $is_faq_bot_active = function_exists('is_plugin_active') && is_plugin_active('chatbot-support.php');
        $default_bottom = $is_faq_bot_active ? '96' : '24';
        $default_key = 'sk-4d92ceabf90e47cfbe6286db7786ef1d';

        $fashion_system_instruction = "Bạn là Stylist AI và chuyên viên tư vấn thời trang cao cấp của Shop Quần Áo LTTheme Fashion.
Nhiệm vụ của bạn:
1. Tư vấn phối đồ (outfit) thời trang, thẩm mỹ, có gu và phù hợp cho từng hoàn cảnh: đi làm công sở, đi tiệc, hẹn hò, dạo phố, du lịch mùa hè/mùa đông.
2. Hướng dẫn khách chọn size chuẩn xác theo chiều cao, cân nặng và phom người:
   • Size S: 45 - 53kg | Cao 1m50 - 1m60
   • Size M: 54 - 62kg | Cao 1m60 - 1m68
   • Size L: 63 - 72kg | Cao 1m68 - 1m75
   • Size XL: 73 - 82kg | Cao 1m75 - 1m82
   • Size XXL: Trên 82kg | Cao trên 1m80
   (Nếu khách thích phong cách rộng rãi Oversize hoặc có bụng/vai to thì khuyên tăng 1 size).
3. Cung cấp thông tin sản phẩm và chính sách cửa hàng:
   • Sản phẩm: Áo thun 100% cotton compact 250gsm, sơ mi oxford/lụa chống nhăn, quần jeans, kaki, âu, đầm váy thiết kế, blazer, áo khoác.
   • Vận chuyển: Freeship toàn quốc từ 300.000đ; Đơn dưới 300.000đ phí ship 25.000đ; Được kiểm tra hàng trước khi nhận (COD).
   • Đổi trả: Đổi hàng MIỄN PHÍ trong 7 ngày nếu không vừa size hoặc có lỗi từ nhà sản xuất (quần áo còn tem mác).
   • Showroom: 123 Đường Thời Trang Q1 TP.HCM & 456 Phố Phong Cách Hoàn Kiếm HN (8h30 - 22h00 cả tuần). Hotline/Zalo: 0988.123.456.
4. Phong cách giao tiếp: Tự nhiên, nhiệt tình, lịch sự, trẻ trung, dùng emoji sinh động. Cuối mỗi câu trả lời, hãy khéo léo gợi mở hoặc khuyến khích khách hàng tiếp tục đặt câu hỏi.";

        $defaults = [
            'api_key' => $default_key,
            'model' => 'deepseek-flash',
            'system_instruction' => $fashion_system_instruction,
            'temperature' => 0.7,
            'bot_name' => 'Stylist AI Thời Trang ✨',
            'welcome_msg' => "Xin chào! Em là Stylist AI của Shop Quần Áo LTTheme Fashion 👗. Bạn đang tìm trang phục đi làm, đi tiệc hay cần tư vấn phối đồ và chọn size chuẩn ạ?",
            'primary_color' => '#0ea5e9',
            'secondary_color' => '#2563eb',
            'bot_position' => 'right',
            'offset_bottom' => '92',
            'enable_widget' => '1',
            'max_history_turns' => 8,
            'suggestions' => "Tư vấn phối đồ đi tiệc cưới mùa hè thanh lịch ✨\nGợi ý outfit công sở trẻ trung, thoải mái 👔\nCao 1m65, nặng 58kg nên chọn size áo gì? 📏\nShop có chính sách freeship và đổi size ra sao? 🛍️"
        ];
        $settings = get_option(self::OPTION_SETTINGS, []);
        $parsed = wp_parse_args($settings, $defaults);

        // Tự động chuyển đổi khóa API Key cũ hoặc rỗng sang DeepSeek API Key được cung cấp
        if (empty($parsed['api_key']) || strpos($parsed['api_key'], 'AIzaSy') === 0) {
            $parsed['api_key'] = $default_key;
        }

        // Tự động chuyển đổi mô hình cũ sang DeepSeek model
        $allowed_models = ['deepseek-flash', 'deepseek-v4-pro'];
        if (empty($parsed['model']) || !in_array($parsed['model'], $allowed_models)) {
            $parsed['model'] = 'deepseek-flash';
        }

        if (isset($parsed['bot_name']) && ($parsed['bot_name'] === 'Trợ Lý AI Thông Minh ✨' || $parsed['bot_name'] === 'Trợ Lý AI Gemini ✨' || empty($parsed['bot_name']))) {
            $parsed['bot_name'] = $defaults['bot_name'];
        }
        if (isset($parsed['welcome_msg']) && (mb_stripos($parsed['welcome_msg'], 'chuyên viên hỗ trợ') !== false || mb_stripos($parsed['welcome_msg'], 'phát triển bởi Google Gemini') !== false || empty($parsed['welcome_msg']))) {
            $parsed['welcome_msg'] = $defaults['welcome_msg'];
        }
        if (isset($parsed['system_instruction']) && (mb_stripos($parsed['system_instruction'], 'cửa hàng/doanh nghiệp') !== false || mb_stripos($parsed['system_instruction'], 'chuyên viên hỗ trợ khách hàng AI') !== false || empty($parsed['system_instruction']))) {
            $parsed['system_instruction'] = $defaults['system_instruction'];
        }
        if (isset($parsed['suggestions']) && (mb_stripos($parsed['suggestions'], 'Giờ làm việc') !== false || empty($parsed['suggestions']))) {
            $parsed['suggestions'] = $defaults['suggestions'];
        }
        $parsed['offset_bottom'] = '92';
        $parsed['bot_position'] = 'right';
        return $parsed;
    }

    public function register_admin_menu() {
        add_menu_page(
            'Chatbot AI DeepSeek',
            'Chatbot AI (DeepSeek)',
            'manage_options',
            'chatbot-ai-gemini',
            [$this, 'render_admin_page'],
            'dashicons-superhero-alt',
            27
        );
    }

    public function handle_admin_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'chatbot-ai-gemini') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Lưu Cài đặt
        if (isset($_POST['gemini_action']) && $_POST['gemini_action'] === 'save_settings') {
            check_admin_referer('gemini_save_settings_nonce');

            $current = self::get_settings();
            $new_api_key = sanitize_text_field($_POST['api_key'] ?? '');

            $allowed_models = ['deepseek-flash', 'deepseek-v4-pro'];
            $selected_model = in_array($_POST['model'] ?? '', $allowed_models) ? $_POST['model'] : 'deepseek-flash';

            $settings = [
                'api_key' => !empty($new_api_key) ? trim($new_api_key) : $current['api_key'],
                'model' => $selected_model,
                'system_instruction' => sanitize_textarea_field($_POST['system_instruction'] ?? ''),
                'temperature' => floatval($_POST['temperature'] ?? 0.7),
                'bot_name' => sanitize_text_field($_POST['bot_name'] ?? 'Stylist AI Thời Trang ✨'),
                'welcome_msg' => sanitize_textarea_field($_POST['welcome_msg'] ?? ''),
                'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#0ea5e9'),
                'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '#2563eb'),
                'bot_position' => in_array($_POST['bot_position'] ?? '', ['right', 'left']) ? $_POST['bot_position'] : 'right',
                'offset_bottom' => absint($_POST['offset_bottom'] ?? 24),
                'enable_widget' => isset($_POST['enable_widget']) ? '1' : '0',
                'max_history_turns' => absint($_POST['max_history_turns'] ?? 8),
                'suggestions' => sanitize_textarea_field($_POST['suggestions'] ?? '')
            ];

            update_option(self::OPTION_SETTINGS, $settings);
            wp_safe_redirect(admin_url('admin.php?page=chatbot-ai-gemini&msg=saved'));
            exit;
        }

        // Xóa tất cả logs
        if (isset($_POST['gemini_action']) && $_POST['gemini_action'] === 'clear_logs') {
            check_admin_referer('gemini_clear_logs_nonce');
            update_option(self::OPTION_LOGS, []);
            wp_safe_redirect(admin_url('admin.php?page=chatbot-ai-gemini&tab=logs&msg=logs_cleared'));
            exit;
        }
    }

    /**
     * Gọi DeepSeek API (OpenAI-compatible)
     */
    public static function call_deepseek_api($user_message, $history = [], $custom_key = null, $custom_model = null) {
        $settings = self::get_settings();
        $api_key = !empty($custom_key) ? trim($custom_key) : trim($settings['api_key']);
        $model = !empty($custom_model) ? trim($custom_model) : $settings['model'];

        if (empty($api_key)) {
            return [
                'success' => false,
                'error' => 'Chưa cấu hình DeepSeek API Key. Vui lòng vào trang quản trị để nhập API Key.'
            ];
        }

        $endpoint = 'https://api.deepseek.com/chat/completions';

        // Xây dựng danh sách messages chuẩn OpenAI / DeepSeek
        $messages = [];

        // System Instruction định hướng phong cách stylist & chính sách shop
        if (!empty($settings['system_instruction'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $settings['system_instruction']
            ];
        }

        // Thêm lịch sử hội thoại trước đó nếu có
        if (is_array($history) && !empty($history)) {
            foreach ($history as $item) {
                if (isset($item['role']) && isset($item['text'])) {
                    $role = in_array($item['role'], ['assistant', 'model']) ? 'assistant' : 'user';
                    $messages[] = [
                        'role' => $role,
                        'content' => $item['text']
                    ];
                }
            }
        }

        // Thêm câu hỏi hiện tại của user
        $messages[] = [
            'role' => 'user',
            'content' => $user_message
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float)$settings['temperature'],
            'max_tokens' => 1500,
            'stream' => false
        ];

        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode($payload),
            'timeout' => 50,
            'sslverify' => false, // Đảm bảo hoạt động trơn tru trên XAMPP / Windows localhost
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => 'Lỗi kết nối đến DeepSeek API: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $err_msg = $data['error']['message'] ?? ('Lỗi từ DeepSeek API (Mã HTTP: ' . $status_code . ')');
            if ($status_code === 402 || stripos($err_msg, 'Insufficient Balance') !== false) {
                $err_msg = 'Tài khoản DeepSeek hiện tại có số dư 0 USD (Insufficient Balance). Vui lòng nạp tiền (top-up) tại platform.deepseek.com để tiếp tục trò chuyện.';
            } elseif ($status_code === 401) {
                $err_msg = 'DeepSeek API Key không hợp lệ hoặc đã hết hạn (Authentication Fails).';
            }
            return [
                'success' => false,
                'error' => $err_msg,
                'status_code' => $status_code
            ];
        }

        if (isset($data['choices'][0]['message']['content'])) {
            $ai_text = trim($data['choices'][0]['message']['content']);
            return [
                'success' => true,
                'answer' => $ai_text,
                'model' => $model
            ];
        }

        return [
            'success' => false,
            'error' => 'Không nhận được câu trả lời hợp lệ từ DeepSeek AI. Phản hồi thô: ' . substr($body, 0, 150)
        ];
    }

    /**
     * Alias giữ tương thích với các hook/hàm gọi cũ
     */
    public static function call_gemini_api($user_message, $history = [], $custom_key = null, $custom_model = null) {
        return self::call_deepseek_api($user_message, $history, $custom_key, $custom_model);
    }

    /**
     * Ghi log cuộc hội thoại
     */
    private static function log_conversation($user_msg, $ai_reply, $status = 'success') {
        $logs = get_option(self::OPTION_LOGS, []);
        if (!is_array($logs)) $logs = [];

        $logs[] = [
            'id' => 'log_' . time() . '_' . wp_rand(100, 999),
            'time' => current_time('d/m/Y H:i:s'),
            'user' => $user_msg,
            'ai' => $ai_reply,
            'status' => $status
        ];

        // Giữ lại tối đa 50 logs gần nhất
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }

        update_option(self::OPTION_LOGS, $logs);
    }

    /**
     * AJAX: Xử lý tin nhắn người dùng và gọi AI suy luận
     */
    public function ajax_handle_message() {
        check_ajax_referer('gemini_ai_widget_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_send_json_error(['message' => 'Vui lòng nhập nội dung câu hỏi.']);
        }

        // Lấy lịch sử hội thoại từ client
        $history = [];
        if (!empty($_POST['history'])) {
            $raw_history = json_decode(stripslashes($_POST['history']), true);
            if (is_array($raw_history)) {
                $history = $raw_history;
            }
        }

        $result = self::call_deepseek_api($message, $history);

        if ($result['success']) {
            self::log_conversation($message, $result['answer'], 'success');
            wp_send_json_success([
                'answer' => $result['answer'],
                'model' => $result['model'] ?? 'deepseek-flash'
            ]);
        } else {
            self::log_conversation($message, $result['error'], 'error');
            wp_send_json_error([
                'message' => $result['error']
            ]);
        }
    }

    /**
     * AJAX: Xử lý streaming tin nhắn theo thời gian thực (SSE)
     */
    public function ajax_stream_message() {
        check_ajax_referer('gemini_ai_widget_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_die('Nội dung câu hỏi không được để trống.');
        }

        $history = [];
        if (!empty($_POST['history'])) {
            $raw_history = json_decode(stripslashes($_POST['history']), true);
            if (is_array($raw_history)) {
                $history = $raw_history;
            }
        }

        self::stream_deepseek_api($message, $history);
        exit;
    }

    /**
     * Gọi DeepSeek API dạng Streaming (Server-Sent Events)
     */
    public static function stream_deepseek_api($user_message, $history = []) {
        // Tắt bộ đệm và nén dữ liệu để server gửi token ngay lập tức
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        @ini_set('implicit_flush', true);
        ob_implicit_flush(true);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream; charset=utf-8');
        header('Cache-Control: no-cache, no-transform');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        // Gửi chuỗi padding SSE comment để buộc proxy/Apache đẩy dữ liệu ngay lập tức
        echo ":" . str_repeat(" ", 2048) . "\n\n";
        flush();

        $settings = self::get_settings();
        $api_key = trim($settings['api_key']);
        $model = !empty($settings['model']) ? trim($settings['model']) : 'deepseek-flash';

        if (empty($api_key)) {
            echo "data: " . json_encode(['error' => 'Chưa cấu hình DeepSeek API Key. Vui lòng vào trang quản trị để nhập API Key.'], JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: " . json_encode(['done' => true, 'full_text' => ''], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            exit;
        }

        $endpoint = 'https://api.deepseek.com/chat/completions';

        $messages = [];
        if (!empty($settings['system_instruction'])) {
            $messages[] = [
                'role' => 'system',
                'content' => $settings['system_instruction']
            ];
        }

        if (is_array($history) && !empty($history)) {
            foreach ($history as $item) {
                if (isset($item['role']) && isset($item['text'])) {
                    $role = in_array($item['role'], ['assistant', 'model']) ? 'assistant' : 'user';
                    $messages[] = [
                        'role' => $role,
                        'content' => $item['text']
                    ];
                }
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $user_message
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float)$settings['temperature'],
            'max_tokens' => 1500,
            'stream' => true
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key,
            'Accept: text/event-stream'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $buffer = '';
        $accumulated_text = '';

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$buffer, &$accumulated_text) {
            $buffer .= $chunk;
            while (($newline_pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $newline_pos);
                $buffer = substr($buffer, $newline_pos + 1);
                $trimmed = trim($line);
                if (strpos($trimmed, 'data: ') === 0) {
                    $json_str = substr($trimmed, 6);
                    if ($json_str === '[DONE]') {
                        continue;
                    }
                    $json = json_decode($json_str, true);
                    if ($json && isset($json['choices'][0]['delta']['content'])) {
                        $text_part = $json['choices'][0]['delta']['content'];
                        if ($text_part !== '') {
                            $accumulated_text .= $text_part;
                            echo "data: " . json_encode(['text' => $text_part], JSON_UNESCAPED_UNICODE) . "\n\n";
                            if (ob_get_level() > 0) {
                                ob_flush();
                            }
                            flush();
                        }
                    }
                }
            }
            return strlen($chunk);
        });

        curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!empty($buffer)) {
            $lines = explode("\n", $buffer);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (strpos($trimmed, 'data: ') === 0) {
                    $json_str = substr($trimmed, 6);
                    if ($json_str === '[DONE]') {
                        continue;
                    }
                    $json = json_decode($json_str, true);
                    if ($json && isset($json['choices'][0]['delta']['content'])) {
                        $text_part = $json['choices'][0]['delta']['content'];
                        if ($text_part !== '') {
                            $accumulated_text .= $text_part;
                            echo "data: " . json_encode(['text' => $text_part], JSON_UNESCAPED_UNICODE) . "\n\n";
                            if (ob_get_level() > 0) {
                                ob_flush();
                            }
                            flush();
                        }
                    }
                }
            }
        }

        if (!empty($curl_error) || ($http_status !== 200 && empty($accumulated_text))) {
            $err_msg = !empty($curl_error) ? $curl_error : ("Lỗi từ DeepSeek API (HTTP $http_status)");
            if ($http_status === 402) {
                $err_msg = "Tài khoản DeepSeek hiện tại có số dư 0 USD (Insufficient Balance). Vui lòng nạp tiền tại platform.deepseek.com để tiếp tục.";
            } elseif ($http_status === 401) {
                $err_msg = "DeepSeek API Key không chính xác hoặc đã bị vô hiệu hóa.";
            }
            echo "data: " . json_encode(['error' => $err_msg], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            self::log_conversation($user_message, $err_msg, 'error');
        } else {
            if (!empty($accumulated_text)) {
                self::log_conversation($user_message, $accumulated_text, 'success');
            }
        }

        echo "data: " . json_encode(['done' => true, 'full_text' => $accumulated_text], JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        exit;
    }

    /**
     * Alias giữ tương thích streaming cũ
     */
    public static function stream_gemini_api($user_message, $history = []) {
        return self::stream_deepseek_api($user_message, $history);
    }

    /**
     * AJAX: Kiểm tra kết nối API Key trong Admin với DeepSeek
     */
    public function ajax_test_api() {
        check_ajax_referer('gemini_test_api_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền thực hiện.']);
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $raw_model = sanitize_text_field($_POST['model'] ?? 'deepseek-flash');
        $model = in_array($raw_model, ['deepseek-flash', 'deepseek-v4-pro']) ? $raw_model : 'deepseek-flash';

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'Vui lòng nhập DeepSeek API Key để kiểm tra.']);
        }

        // Bước 1: Kiểm tra tính hợp lệ của key qua endpoint /models
        $models_check = wp_remote_get('https://api.deepseek.com/models', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            ],
            'timeout' => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($models_check)) {
            wp_send_json_error(['message' => 'Lỗi kết nối máy chủ DeepSeek: ' . $models_check->get_error_message()]);
        }

        $models_code = wp_remote_retrieve_response_code($models_check);
        if ($models_code === 401) {
            wp_send_json_error(['message' => 'Khóa API Key không hợp lệ (Authentication Fails). Vui lòng kiểm tra lại.']);
        }

        // Bước 2: Kiểm tra số dư tài khoản
        $balance_check = wp_remote_get('https://api.deepseek.com/user/balance', [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json'
            ],
            'timeout' => 15,
            'sslverify' => false,
        ]);

        $balance_str = '';
        $is_available = true;
        if (!is_wp_error($balance_check) && wp_remote_retrieve_response_code($balance_check) === 200) {
            $balance_data = json_decode(wp_remote_retrieve_body($balance_check), true);
            if (isset($balance_data['balance_infos'][0]['total_balance'])) {
                $balance_str = ' (Số dư: $' . $balance_data['balance_infos'][0]['total_balance'] . ' ' . ($balance_data['balance_infos'][0]['currency'] ?? 'USD') . ')';
            }
            if (isset($balance_data['is_available'])) {
                $is_available = (bool)$balance_data['is_available'];
            }
        }

        // Bước 3: Thử nghiệm gửi prompt nhỏ
        $test_prompt = "Chào bạn! Hãy xác nhận kết nối thành công và trả lời ngắn gọn trong 1 câu: Tôi là DeepSeek AI sẵn sàng hỗ trợ!";
        $result = self::call_deepseek_api($test_prompt, [], $api_key, $model);

        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Kết nối thành công tới DeepSeek API!' . $balance_str,
                'ai_response' => $result['answer']
            ]);
        } else {
            // Nếu là lỗi 402 hoặc số dư = 0 nhưng key đã chứng thực thành công ở bước 1
            if (($result['status_code'] ?? 0) === 402 || !$is_available || stripos($result['error'], 'Insufficient Balance') !== false) {
                wp_send_json_success([
                    'message' => 'Xác thực DeepSeek API Key thành công 100%!' . $balance_str,
                    'ai_response' => 'Khóa API hoàn toàn chuẩn xác. Tài khoản hiện có số dư 0 USD, bạn chỉ cần nạp tiền (top-up) tại platform.deepseek.com là bot có thể tạo câu trả lời cho khách hàng.'
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['error']
                ]);
            }
        }
    }

    /**
     * Render Giao diện Admin
     */
    public function render_admin_page() {
        $tab = sanitize_key($_GET['tab'] ?? 'settings');
        $settings = self::get_settings();
        $logs = get_option(self::OPTION_LOGS, []);
        if (!is_array($logs)) $logs = [];
        ?>
        <style>
            .ai-wrap { margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
            .ai-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
            .ai-header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #1e1b4b; display: flex; align-items: center; gap: 10px; }
            .ai-badge { background: linear-gradient(135deg, #6366f1, #a855f7); color: #fff; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 9999px; box-shadow: 0 2px 4px rgba(99,102,241,0.3); }
            .ai-tabs { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; }
            .ai-tab-item { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; text-decoration: none; font-weight: 600; font-size: 14px; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
            .ai-tab-item:hover { color: #6366f1; }
            .ai-tab-item.active { color: #6366f1; border-bottom-color: #6366f1; }
            .ai-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); padding: 26px; margin-bottom: 24px; }
            .ai-form-group { margin-bottom: 20px; }
            .ai-form-group label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 6px; font-size: 13.5px; }
            .ai-form-group input[type="text"], .ai-form-group input[type="password"], .ai-form-group textarea, .ai-form-group select { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 13px; font-size: 14px; box-sizing: border-box; }
            .ai-form-group input:focus, .ai-form-group textarea:focus, .ai-form-group select:focus { border-color: #6366f1; outline: none; box-shadow: 0 0 0 3px rgba(99,102,241,0.18); }
            .ai-hint { font-size: 12.5px; color: #64748b; margin-top: 5px; line-height: 1.45; }
            .ai-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 20px; font-weight: 600; font-size: 13.5px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; transition: all 0.15s ease; }
            .ai-btn-primary { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: #fff; box-shadow: 0 2px 6px rgba(99,102,241,0.3); }
            .ai-btn-primary:hover { background: linear-gradient(135deg, #4f46e5, #7c3aed); color: #fff; transform: translateY(-1px); }
            .ai-btn-secondary { background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; }
            .ai-btn-secondary:hover { background: #f1f5f9; color: #1e293b; }
            .ai-btn-danger { background: #ef4444; color: #fff; }
            .ai-btn-danger:hover { background: #dc2626; color: #fff; }
            .ai-notice { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; }
            .ai-key-box { display: flex; gap: 8px; }
            .ai-status-indicator { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 6px; }
            .ai-status-indicator.ready { background: #22c55e; box-shadow: 0 0 6px #22c55e; }
            .ai-status-indicator.not-ready { background: #f59e0b; }
            .ai-table { width: 100%; border-collapse: collapse; text-align: left; }
            .ai-table th { background: #f8fafc; color: #475569; font-size: 12px; font-weight: 600; text-transform: uppercase; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; }
            .ai-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; vertical-align: top; }
            .ai-table tr:hover td { background: #faf5ff; }
        </style>

        <div class="wrap ai-wrap">
            <div class="ai-header">
                <h1>
                    <span style="font-size: 28px;">✨</span>
                    Chatbot AI DeepSeek (DeepSeek Assistant)
                </h1>
                <div>
                    <?php if (!empty($settings['api_key'])): ?>
                        <span style="font-size: 13px; font-weight: 600; color: #15803d; background: #dcfce7; padding: 6px 12px; border-radius: 20px; border: 1px solid #86efac; margin-right: 8px;">
                            <span class="ai-status-indicator ready"></span> Đã cấu hình DeepSeek API
                        </span>
                    <?php else: ?>
                        <span style="font-size: 13px; font-weight: 600; color: #b45309; background: #fef3c7; padding: 6px 12px; border-radius: 20px; border: 1px solid #fde68a; margin-right: 8px;">
                            <span class="ai-status-indicator not-ready"></span> Chưa có API Key
                        </span>
                    <?php endif; ?>
                    <span class="ai-badge">v<?php echo esc_html(self::VERSION); ?></span>
                </div>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="ai-notice">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php
                    switch ($_GET['msg']) {
                        case 'saved': echo 'Đã lưu cấu hình Chatbot AI DeepSeek thành công!'; break;
                        case 'logs_cleared': echo 'Đã xóa toàn bộ nhật ký hội thoại!'; break;
                        default: echo 'Thao tác thành công!';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="ai-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-ai-gemini&tab=settings')); ?>" class="ai-tab-item <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> Cấu Hình API & Định Hướng AI
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-ai-gemini&tab=sandbox')); ?>" class="ai-tab-item <?php echo $tab === 'sandbox' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-format-chat"></span> Trò Chuyện Trực Tiếp (Live Sandbox)
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-ai-gemini&tab=logs')); ?>" class="ai-tab-item <?php echo $tab === 'logs' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span> Nhật Ký Hội Thoại (<?php echo count($logs); ?>)
                </a>
            </div>

            <?php if ($tab === 'settings'): ?>
                <div style="max-width: 820px;">
                    <form method="post" action="">
                        <?php wp_nonce_field('gemini_save_settings_nonce'); ?>
                        <input type="hidden" name="gemini_action" value="save_settings">

                        <!-- Khối API Key -->
                        <div class="ai-card" style="border-left: 4px solid #0ea5e9;">
                            <h2 style="margin: 0 0 16px; font-size: 17px; font-weight: 700; color: #1e1b4b; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-admin-network" style="color: #0ea5e9;"></span>
                                1. Cấu Hình DeepSeek API Key
                            </h2>

                            <div class="ai-form-group">
                                <label>DeepSeek API Key <span style="color: #ef4444;">*</span></label>
                                <div class="ai-key-box">
                                    <input type="password" id="ai-api-key-field" name="api_key" value="<?php echo esc_attr($settings['api_key']); ?>" placeholder="Dán khóa API Key của bạn (bắt đầu bằng sk-...)" required>
                                    <button type="button" class="ai-btn ai-btn-secondary" id="ai-toggle-key-btn" title="Ẩn/Hiện khóa">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <button type="button" class="ai-btn ai-btn-secondary" id="ai-test-api-btn" style="white-space: nowrap; font-weight: 600;">
                                        ⚡ Kiểm Tra Kết Nối
                                    </button>
                                </div>
                                <div id="ai-test-result" style="margin-top: 10px; font-size: 13px; display: none; padding: 10px 14px; border-radius: 6px;"></div>
                                <div class="ai-hint">
                                    Quản lý khóa và nạp số dư tài khoản tại: <a href="https://platform.deepseek.com/api_keys" target="_blank" style="color: #0284c7; font-weight: 600; text-decoration: underline;">DeepSeek Platform API Keys</a>.
                                </div>
                            </div>

                            <div class="ai-form-group">
                                <label>Mô hình DeepSeek (Model)</label>
                                <select name="model" id="ai-model-select">
                                    <option value="deepseek-flash" <?php selected($settings['model'], 'deepseek-flash'); ?>>deepseek-flash (Khuyên dùng: Tốc độ cao, tối ưu chi phí, kiến trúc V4.1)</option>
                                    <option value="deepseek-v4-pro" <?php selected($settings['model'], 'deepseek-v4-pro'); ?>>deepseek-v4-pro (Mô hình hiệu năng cao chuyên sâu)</option>
                                </select>
                                <div class="ai-hint">Hệ thống sử dụng các dòng mô hình DeepSeek chính thức mới nhất: <strong>deepseek-flash</strong> và <strong>deepseek-v4-pro</strong>.</div>
                            </div>
                        </div>

                        <!-- Khối Huấn luyện Ngữ cảnh (System Instruction) -->
                        <div class="ai-card" style="border-left: 4px solid #a855f7;">
                            <h2 style="margin: 0 0 16px; font-size: 17px; font-weight: 700; color: #1e1b4b; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-welcome-learn-more" style="color: #a855f7;"></span>
                                2. Định Hướng Hành Vi & Kiến Thức Cửa Hàng (System Instruction)
                            </h2>

                            <div class="ai-form-group">
                                <label>Hướng dẫn ngữ cảnh cho AI (System Prompt)</label>
                                <textarea name="system_instruction" rows="6" placeholder="Nhập vai trò, thông tin sản phẩm, hotline, chính sách giao hàng của shop để AI biết cách trả lời khách hàng..."><?php echo esc_textarea($settings['system_instruction']); ?></textarea>
                                <div class="ai-hint">
                                    💡 <strong>Mẹo hay:</strong> Bạn có thể đưa vào đây các thông tin như: Hotline liên hệ, địa chỉ shop, thời gian mở cửa, bảng giá hoặc danh sách sản phẩm nổi bật để AI tự động trích xuất và tư vấn cho khách.
                                </div>
                            </div>

                            <div class="ai-form-group">
                                <label>Độ sáng tạo của AI (Temperature): <span id="temp-val" style="color: #6366f1; font-weight: 700;"><?php echo esc_html($settings['temperature']); ?></span></label>
                                <input type="range" name="temperature" min="0" max="1" step="0.1" value="<?php echo esc_attr($settings['temperature']); ?>" oninput="document.getElementById('temp-val').innerText = this.value;" style="width: 100%;">
                                <div class="ai-hint">0.2: Trả lời chính xác, bám sát thông tin | 0.7: Tư vấn tự nhiên, linh hoạt và thân thiện (mặc định) | 1.0: Sáng tạo cao.</div>
                            </div>

                            <div class="ai-form-group">
                                <label>Số lượt hội thoại AI cần ghi nhớ (Multi-turn Memory)</label>
                                <input type="number" name="max_history_turns" min="2" max="20" value="<?php echo esc_attr($settings['max_history_turns']); ?>" style="width: 120px;"> lượt trao đổi gần nhất
                                <div class="ai-hint">Giúp AI hiểu được ngữ cảnh các câu hỏi liên tiếp phía trước của khách trong cùng một phiên trò chuyện.</div>
                            </div>
                        </div>

                        <!-- Khối Giao diện Widget -->
                        <div class="ai-card" style="border-left: 4px solid #ec4899;">
                            <h2 style="margin: 0 0 16px; font-size: 17px; font-weight: 700; color: #1e1b4b; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-art" style="color: #ec4899;"></span>
                                3. Cấu Hình Widget Hiển Thị Ngoài Trang Chủ
                            </h2>

                            <div class="ai-form-group">
                                <label>
                                    <input type="checkbox" name="enable_widget" value="1" <?php checked($settings['enable_widget'], '1'); ?>>
                                    <strong>Kích hoạt hiển thị Widget Chatbot AI trên toàn bộ website</strong>
                                </label>
                            </div>

                            <div class="ai-form-group">
                                <label>Tên hiển thị của Trợ Lý AI</label>
                                <input type="text" name="bot_name" value="<?php echo esc_attr($settings['bot_name']); ?>" required>
                            </div>

                            <div class="ai-form-group">
                                <label>Lời chào mở đầu khi khách mở khung chat</label>
                                <textarea name="welcome_msg" rows="3" required><?php echo esc_textarea($settings['welcome_msg']); ?></textarea>
                            </div>

                            <div class="ai-form-group">
                                <label>Gợi ý câu hỏi nhanh cho khách hàng (Mỗi câu trên 1 dòng)</label>
                                <textarea name="suggestions" rows="4" placeholder="Tư vấn giúp tôi quà tặng sinh nhật ý nghĩa 🎁..."><?php echo esc_textarea($settings['suggestions'] ?? ''); ?></textarea>
                                <div class="ai-hint">Các nút gợi ý câu hỏi sẽ hiển thị ngay dưới lời chào của bot để khách hàng có thể bấm hỏi nhanh bằng 1 chạm.</div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                                <div class="ai-form-group">
                                    <label>Màu Gradient 1</label>
                                    <input type="color" name="primary_color" value="<?php echo esc_attr($settings['primary_color']); ?>" style="height: 40px; padding: 2px; cursor: pointer;">
                                </div>
                                <div class="ai-form-group">
                                    <label>Màu Gradient 2</label>
                                    <input type="color" name="secondary_color" value="<?php echo esc_attr($settings['secondary_color']); ?>" style="height: 40px; padding: 2px; cursor: pointer;">
                                </div>
                                <div class="ai-form-group">
                                    <label>Vị trí hiển thị nút chat</label>
                                    <select name="bot_position">
                                        <option value="right" <?php selected($settings['bot_position'], 'right'); ?>>Góc Phải Màn Hình</option>
                                        <option value="left" <?php selected($settings['bot_position'], 'left'); ?>>Góc Trái Màn Hình</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="ai-btn ai-btn-primary" style="padding: 12px 30px; font-size: 15px;">
                                <span class="dashicons dashicons-saved"></span> Lưu Tất Cả Cài Đặt AI
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                (function() {
                    var keyField = document.getElementById('ai-api-key-field');
                    var toggleBtn = document.getElementById('ai-toggle-key-btn');
                    var testBtn = document.getElementById('ai-test-api-btn');
                    var testResult = document.getElementById('ai-test-result');
                    var modelSelect = document.getElementById('ai-model-select');

                    toggleBtn.addEventListener('click', function() {
                        if (keyField.type === 'password') {
                            keyField.type = 'text';
                            toggleBtn.innerHTML = '<span class="dashicons dashicons-hidden"></span>';
                        } else {
                            keyField.type = 'password';
                            toggleBtn.innerHTML = '<span class="dashicons dashicons-visibility"></span>';
                        }
                    });

                    testBtn.addEventListener('click', function() {
                        var key = keyField.value.trim();
                        if (!key) {
                            alert('Vui lòng nhập API Key trước khi kiểm tra!');
                            keyField.focus();
                            return;
                        }

                        testBtn.disabled = true;
                        testBtn.innerText = '⏳ Đang kiểm tra...';
                        testResult.style.display = 'none';

                        var formData = new FormData();
                        formData.append('action', 'gemini_ai_test_api');
                        formData.append('nonce', '<?php echo wp_create_nonce("gemini_test_api_nonce"); ?>');
                        formData.append('api_key', key);
                        formData.append('model', modelSelect.value);

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(res) { return res.json(); })
                        .then(function(data) {
                            testBtn.disabled = false;
                            testBtn.innerText = '⚡ Kiểm Tra Kết Nối';
                            testResult.style.display = 'block';

                            if (data.success) {
                                testResult.style.background = '#dcfce7';
                                testResult.style.border = '1px solid #86efac';
                                testResult.style.color = '#15803d';
                                testResult.innerHTML = '<strong>✓ ' + data.data.message + '</strong><br><span style="font-style: italic; font-size: 12px; margin-top: 4px; display: block;">Phản hồi từ AI: "' + data.data.ai_response + '"</span>';
                            } else {
                                testResult.style.background = '#fee2e2';
                                testResult.style.border = '1px solid #fca5a5';
                                testResult.style.color = '#b91c1c';
                                testResult.innerHTML = '<strong>✕ Kiểm tra thất bại:</strong> ' + (data.data.message || 'Lỗi không xác định.');
                            }
                        })
                        .catch(function(err) {
                            testBtn.disabled = false;
                            testBtn.innerText = '⚡ Kiểm Tra Kết Nối';
                            testResult.style.display = 'block';
                            testResult.style.background = '#fee2e2';
                            testResult.style.border = '1px solid #fca5a5';
                            testResult.style.color = '#b91c1c';
                            testResult.innerHTML = '<strong>✕ Lỗi kết nối mạng:</strong> Không thể gửi yêu cầu kiểm tra.';
                        });
                    });
                })();
                </script>

            <?php elseif ($tab === 'sandbox'): ?>
                <div style="max-width: 820px;">
                    <div class="ai-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                            <div>
                                <h2 style="margin: 0; font-size: 17px; font-weight: 700; color: #1e1b4b;">Trò Chuyện Thử Nghiệm Trực Tiếp Với DeepSeek AI</h2>
                                <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Thử nghiệm khả năng tư duy và phản hồi của AI theo đúng cấu hình Prompt và API Key hiện tại.</p>
                            </div>
                            <button type="button" class="ai-btn ai-btn-secondary" id="ai-sandbox-clear-btn" style="font-size: 12px; padding: 6px 12px;">
                                🔄 Xóa Ngữ Cảnh & Chat Lại
                            </button>
                        </div>

                        <!-- Khung chat sandbox -->
                        <div id="ai-sandbox-box" style="height: 400px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
                            <div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 12px; max-width: 80%; align-self: flex-start; font-size: 13.5px; line-height: 1.5; color: #1e293b;">
                                <?php echo nl2br(esc_html($settings['welcome_msg'])); ?>
                            </div>
                            <?php
                            $sug_list = array_filter(array_map('trim', explode("\n", $settings['suggestions'] ?? '')));
                            if (!empty($sug_list)):
                            ?>
                                <div id="ai-sandbox-suggestions" style="display: flex; flex-direction: column; gap: 6px;">
                                    <span style="font-size: 11px; font-weight: 700; color: #6366f1;">💡 Gợi ý câu hỏi nhanh:</span>
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                        <?php foreach ($sug_list as $sug): ?>
                                            <button type="button" class="ai-sandbox-chip" data-query="<?php echo esc_attr($sug); ?>" style="background: #ffffff; color: #4338ca; border: 1px solid #e0e7ff; border-radius: 999px; padding: 5px 12px; font-size: 12px; cursor: pointer; text-align: left; transition: all 0.2s;">
                                                <?php echo esc_html($sug); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Form gửi chat sandbox -->
                        <form id="ai-sandbox-form" style="display: flex; gap: 8px;">
                            <input type="text" id="ai-sandbox-input" placeholder="Nhập câu hỏi bất kỳ để thử thách khả năng suy luận của AI..." style="flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 14px; outline: none;">
                            <button type="submit" id="ai-sandbox-send" class="ai-btn ai-btn-primary" style="padding: 10px 20px;">
                                <span>Gửi Tin</span> <span class="dashicons dashicons-arrow-right-alt"></span>
                            </button>
                        </form>
                    </div>
                </div>

                <style>
                    @keyframes aiCursorBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
                    .ai-sandbox-chip:hover { background: #eef2ff !important; border-color: #a5b4fc !important; transform: translateY(-1px); }
                </style>

                <script>
                (function() {
                    var box = document.getElementById('ai-sandbox-box');
                    var form = document.getElementById('ai-sandbox-form');
                    var input = document.getElementById('ai-sandbox-input');
                    var sendBtn = document.getElementById('ai-sandbox-send');
                    var clearBtn = document.getElementById('ai-sandbox-clear-btn');
                    var history = [];

                    function appendMessage(text, isUser) {
                        var div = document.createElement('div');
                        div.style.padding = '10px 14px';
                        div.style.borderRadius = '12px';
                        div.style.maxWidth = '80%';
                        div.style.fontSize = '13.5px';
                        div.style.lineHeight = '1.5';
                        div.style.wordBreak = 'break-word';

                        if (isUser) {
                            div.style.background = '#6366f1';
                            div.style.color = '#ffffff';
                            div.style.alignSelf = 'flex-end';
                        } else {
                            div.style.background = '#ffffff';
                            div.style.color = '#1e293b';
                            div.style.border = '1px solid #e2e8f0';
                            div.style.alignSelf = 'flex-start';
                        }
                        div.innerText = text;
                        box.appendChild(div);
                        box.scrollTop = box.scrollHeight;
                    }

                    function getInitialSandboxHtml() {
                        var html = '<div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 12px; max-width: 80%; align-self: flex-start; font-size: 13.5px; line-height: 1.5; color: #1e293b;"><?php echo esc_js($settings["welcome_msg"]); ?></div>';
                        <?php if (!empty($sug_list)): ?>
                        html += '<div id="ai-sandbox-suggestions" style="display: flex; flex-direction: column; gap: 6px;"><span style="font-size: 11px; font-weight: 700; color: #6366f1;">💡 Gợi ý câu hỏi nhanh:</span><div style="display: flex; flex-wrap: wrap; gap: 6px;">';
                        <?php foreach ($sug_list as $sug): ?>
                        html += '<button type="button" class="ai-sandbox-chip" data-query="<?php echo esc_js($sug); ?>" style="background: #ffffff; color: #4338ca; border: 1px solid #e0e7ff; border-radius: 999px; padding: 5px 12px; font-size: 12px; cursor: pointer; text-align: left; transition: all 0.2s;"><?php echo esc_js($sug); ?></button>';
                        <?php endforeach; ?>
                        html += '</div></div>';
                        <?php endif; ?>
                        return html;
                    }

                    clearBtn.addEventListener('click', function() {
                        history = [];
                        box.innerHTML = getInitialSandboxHtml();
                    });

                    box.addEventListener('click', function(e) {
                        var chip = e.target.closest('.ai-sandbox-chip');
                        if (!chip) return;
                        var q = chip.getAttribute('data-query');
                        if (q) {
                            input.value = q;
                            form.dispatchEvent(new Event('submit', { cancelable: true }));
                            var sug = document.getElementById('ai-sandbox-suggestions');
                            if (sug) sug.style.display = 'none';
                        }
                    });

                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var query = input.value.trim();
                        if (!query) return;

                        var sug = document.getElementById('ai-sandbox-suggestions');
                        if (sug) sug.style.display = 'none';

                        appendMessage(query, true);
                        input.value = '';
                        input.disabled = true;
                        sendBtn.disabled = true;

                        // Hiệu ứng đang suy nghĩ
                        var thinking = document.createElement('div');
                        thinking.id = 'ai-sandbox-thinking';
                        thinking.style.padding = '10px 14px';
                        thinking.style.borderRadius = '12px';
                        thinking.style.background = '#ffffff';
                        thinking.style.border = '1px solid #e2e8f0';
                        thinking.style.alignSelf = 'flex-start';
                        thinking.style.fontSize = '13px';
                        thinking.style.color = '#8b5cf6';
                        thinking.innerText = '✨ DeepSeek AI đang suy luận...';
                        box.appendChild(thinking);
                        box.scrollTop = box.scrollHeight;

                        var formData = new FormData();
                        formData.append('action', 'gemini_ai_stream_message');
                        formData.append('nonce', '<?php echo wp_create_nonce("gemini_ai_widget_nonce"); ?>');
                        formData.append('message', query);
                        formData.append('history', JSON.stringify(history));

                        var targetText = '';
                        var displayedText = '';
                        var typingTimer = null;
                        var streamDone = false;
                        var botDiv = null;

                        function ensureBox() {
                            if (!botDiv) {
                                if (thinking && thinking.parentNode) thinking.remove();
                                botDiv = document.createElement('div');
                                botDiv.style.padding = '10px 14px';
                                botDiv.style.borderRadius = '12px';
                                botDiv.style.maxWidth = '80%';
                                botDiv.style.fontSize = '13.5px';
                                botDiv.style.lineHeight = '1.5';
                                botDiv.style.wordBreak = 'break-word';
                                botDiv.style.background = '#ffffff';
                                botDiv.style.color = '#1e293b';
                                botDiv.style.border = '1px solid #e2e8f0';
                                botDiv.style.alignSelf = 'flex-start';
                                box.appendChild(botDiv);
                                box.scrollTop = box.scrollHeight;
                            }
                        }

                        function startTypewriter() {
                            if (typingTimer) return;
                            typingTimer = setInterval(function() {
                                if (displayedText.length < targetText.length) {
                                    ensureBox();
                                    var backlog = targetText.length - displayedText.length;
                                    var step = backlog > 120 ? 4 : (backlog > 60 ? 2 : 1);
                                    displayedText = targetText.slice(0, displayedText.length + step);
                                    botDiv.innerHTML = displayedText.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '<span style="display:inline-block;width:2px;height:13px;background:#6366f1;margin-left:2px;vertical-align:middle;animation:aiCursorBlink 0.7s infinite;"></span>';
                                    box.scrollTop = box.scrollHeight;
                                } else if (streamDone) {
                                    clearInterval(typingTimer);
                                    typingTimer = null;
                                    if (botDiv) {
                                        botDiv.innerHTML = targetText.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');

                                        // Gợi ý câu hỏi tiếp theo
                                        var followupBox = document.createElement('div');
                                        followupBox.className = 'ai-sandbox-followup';
                                        followupBox.style.marginTop = '8px';
                                        followupBox.style.display = 'flex';
                                        followupBox.style.flexDirection = 'column';
                                        followupBox.style.gap = '6px';
                                        followupBox.innerHTML = '<span style="font-size:11px; font-weight:700; color:#6366f1;">💡 Gợi ý câu hỏi tiếp theo:</span>';
                                        var fWrap = document.createElement('div');
                                        fWrap.style.display = 'flex';
                                        fWrap.style.flexWrap = 'wrap';
                                        fWrap.style.gap = '6px';

                                        var fashionFollowUps = [
                                            'Cách phối đồ tôn dáng cho người gầy / tròn? 👗',
                                            'Bảng size chi tiết cho áo sơ mi và quần tây 📏',
                                            'Chính sách đổi trả hàng và phí ship thế nào? 🚚',
                                            'Mẹo phối màu quần áo thời thượng, sang trọng 🎨',
                                            'Shop có những mẫu áo thun / sơ mi nào hot nhất? 👕',
                                            'Showroom mở cửa đến mấy giờ và ở đâu? ⏰'
                                        ];
                                        var added = 0;
                                        fashionFollowUps.forEach(function(sug) {
                                            if (added < 3 && query.indexOf(sug.slice(0, 6)) === -1) {
                                                var btn = document.createElement('button');
                                                btn.type = 'button';
                                                btn.className = 'ai-sandbox-chip';
                                                btn.setAttribute('data-query', sug);
                                                btn.style.cssText = 'background: #ffffff; color: #4338ca; border: 1px solid #e0e7ff; border-radius: 999px; padding: 5px 12px; font-size: 12px; cursor: pointer; text-align: left; transition: all 0.2s;';
                                                btn.innerText = '👉 ' + sug;
                                                fWrap.appendChild(btn);
                                                added++;
                                            }
                                        });
                                        followupBox.appendChild(fWrap);
                                        botDiv.appendChild(followupBox);
                                    }
                                    if (thinking && thinking.parentNode) thinking.remove();
                                    input.disabled = false;
                                    sendBtn.disabled = false;
                                    input.focus();
                                    if (targetText) {
                                        history.push({ role: 'user', text: query });
                                        history.push({ role: 'model', text: targetText });
                                        if (history.length > 16) history = history.slice(-16);
                                    }
                                    box.scrollTop = box.scrollHeight;
                                }
                            }, 22);
                        }

                        fetch(ajaxurl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(function(res) {
                            if (!res.ok) throw new Error('HTTP error ' + res.status);
                            var reader = res.body.getReader();
                            var decoder = new TextDecoder('utf-8');
                            var buffer = '';

                            function read() {
                                return reader.read().then(function(chunk) {
                                    if (chunk.done) {
                                        streamDone = true;
                                        startTypewriter();
                                        return;
                                    }

                                    buffer += decoder.decode(chunk.value, { stream: true });
                                    var lines = buffer.split('\n');
                                    buffer = lines.pop();

                                    for (var i = 0; i < lines.length; i++) {
                                        var line = lines[i].trim();
                                        if (line.indexOf('data: ') === 0) {
                                            var jsonStr = line.substring(6);
                                            try {
                                                var d = JSON.parse(jsonStr);
                                                if (d.text) {
                                                    targetText += d.text;
                                                    startTypewriter();
                                                }
                                                if (d.error) {
                                                    ensureBox();
                                                    botDiv.innerHTML = '<span style="color:#ef4444;">⚠️ ' + d.error + '</span>';
                                                    box.scrollTop = box.scrollHeight;
                                                }
                                            } catch (e) {}
                                        }
                                    }
                                    return read();
                                });
                            }
                            return read();
                        })
                        .catch(function() {
                            if (typingTimer) {
                                clearInterval(typingTimer);
                                typingTimer = null;
                            }
                            if (thinking && thinking.parentNode) thinking.remove();
                            input.disabled = false;
                            sendBtn.disabled = false;
                            input.focus();
                            appendMessage('Lỗi kết nối máy chủ.', false);
                        });
                    });
                })();
                </script>


            <?php elseif ($tab === 'logs'): ?>
                <div class="ai-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <div>
                            <h2 style="margin: 0; font-size: 17px; font-weight: 700; color: #1e1b4b;">Nhật Ký Tương Tác Giữa Khách Hàng & DeepSeek AI</h2>
                            <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Theo dõi các câu hỏi của khách và phản hồi suy luận của AI trong 50 lượt gần nhất.</p>
                        </div>
                        <?php if (!empty($logs)): ?>
                            <form method="post" action="" onsubmit="return confirm('Bạn có chắc muốn làm trống toàn bộ nhật ký này?');">
                                <?php wp_nonce_field('gemini_clear_logs_nonce'); ?>
                                <input type="hidden" name="gemini_action" value="clear_logs">
                                <button type="submit" class="ai-btn ai-btn-danger" style="font-size: 12px; padding: 6px 14px;">
                                    <span class="dashicons dashicons-trash"></span> Xóa Toàn Bộ Nhật Ký
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($logs)): ?>
                        <div style="text-align: center; padding: 40px; color: #94a3b8;">
                            <span class="dashicons dashicons-format-chat" style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 8px;"></span>
                            <p style="font-size: 15px; font-weight: 600; margin: 0 0 4px; color: #475569;">Chưa có hội thoại nào được ghi lại</p>
                            <p style="font-size: 13px; margin: 0;">Khi khách trò chuyện với widget trên website, lịch sử sẽ tự động hiển thị ở đây.</p>
                        </div>
                    <?php else: ?>
                        <table class="ai-table">
                            <thead>
                                <tr>
                                    <th style="width: 14%;">Thời Gian</th>
                                    <th style="width: 38%;">Câu Hỏi Của Khách Hàng</th>
                                    <th style="width: 48%;">Câu Trả Lời Suy Luận Của AI</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($logs) as $log): ?>
                                    <tr>
                                        <td style="color: #64748b; font-size: 12px; white-space: nowrap;">
                                            <?php echo esc_html($log['time'] ?? ''); ?>
                                        </td>
                                        <td style="font-weight: 600; color: #1e293b; line-height: 1.45;">
                                            "<?php echo esc_html($log['user'] ?? ''); ?>"
                                        </td>
                                        <td style="line-height: 1.5; color: #475569;">
                                            <?php if (($log['status'] ?? '') === 'error'): ?>
                                                <span style="color: #ef4444; font-weight: 500;">⚠️ <?php echo esc_html($log['ai'] ?? ''); ?></span>
                                            <?php else: ?>
                                                <?php echo nl2br(esc_html($log['ai'] ?? '')); ?>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Giao diện Frontend AI Widget
     */
    public function render_frontend_widget() {
        $settings = self::get_settings();
        if ($settings['enable_widget'] !== '1') {
            return;
        }

        $primary = esc_attr($settings['primary_color']);
        $secondary = esc_attr($settings['secondary_color']);
        $position = $settings['bot_position'] === 'left' ? 'left' : 'right';
        $offset_bottom = absint($settings['offset_bottom']);
        $nonce = wp_create_nonce('gemini_ai_widget_nonce');
        $ajax_url = admin_url('admin-ajax.php');
        $max_history = absint($settings['max_history_turns']);
        ?>
        <!-- CHATBOT AI GEMINI WIDGET -->
        <style>
            :root {
                --gemini-grad: linear-gradient(135deg, <?php echo $primary; ?>, <?php echo $secondary; ?>);
                --gemini-glow: rgba(99, 102, 241, 0.35);
            }

            #cb-ai-widget {
                position: fixed;
                bottom: <?php echo $offset_bottom; ?>px;
                <?php echo $position; ?>: 24px;
                z-index: 999998;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-size: 14px;
                line-height: 1.5;
            }

            /* Launcher Button */
            #cb-ai-launcher {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: var(--gemini-grad);
                color: #ffffff;
                box-shadow: 0 6px 18px var(--gemini-glow);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
                border: none;
                outline: none;
                position: relative;
            }

            #cb-ai-launcher:hover {
                transform: scale(1.1);
                box-shadow: 0 8px 24px var(--gemini-glow);
            }

            #cb-ai-launcher .cb-ai-open-icon,
            #cb-ai-launcher .cb-ai-close-icon {
                position: absolute;
                transition: all 0.25s ease;
            }

            #cb-ai-launcher .cb-ai-close-icon {
                opacity: 0;
                transform: rotate(-90deg) scale(0.6);
            }

            #cb-ai-widget.active #cb-ai-launcher .cb-ai-open-icon {
                opacity: 0;
                transform: rotate(90deg) scale(0.6);
            }

            #cb-ai-widget.active #cb-ai-launcher .cb-ai-close-icon {
                opacity: 1;
                transform: rotate(0deg) scale(1);
            }

            /* Pulse Animation */
            .cb-ai-sparkle-badge {
                position: absolute;
                top: -2px;
                right: -2px;
                background: #facc15;
                color: #78350f;
                font-size: 10px;
                font-weight: 800;
                padding: 2px 5px;
                border-radius: 999px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.15);
                border: 2px solid #ffffff;
                animation: cbAiPulse 2s infinite;
            }

            @keyframes cbAiPulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.15); }
            }

            /* Chat Window */
            #cb-ai-chatbox {
                position: fixed;
                bottom: 24px;
                right: 96px;
                width: 375px;
                max-width: calc(100vw - 110px);
                height: 540px;
                max-height: calc(100vh - 48px);
                background: #ffffff;
                border-radius: 18px;
                box-shadow: 0 16px 36px rgba(0,0,0,0.12), 0 0 0 1px rgba(99,102,241,0.1);
                display: flex;
                flex-direction: column;
                overflow: hidden;
                opacity: 0;
                visibility: hidden;
                transform: translateY(20px) scale(0.96);
                transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
                z-index: 999999;
            }

            @media (max-width: 600px) {
                #cb-ai-chatbox {
                    right: 12px !important;
                    left: 12px !important;
                    bottom: 12px !important;
                    width: auto !important;
                    max-width: none !important;
                    height: calc(100vh - 24px) !important;
                }
            }

            #cb-ai-widget.active #cb-ai-chatbox {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }

            /* Header */
            .cb-ai-header {
                background: var(--gemini-grad);
                color: #ffffff;
                padding: 14px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            }

            .cb-ai-header-left {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .cb-ai-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.22);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }

            .cb-ai-title {
                font-weight: 700;
                font-size: 14.5px;
                line-height: 1.2;
            }

            .cb-ai-sub {
                font-size: 11px;
                opacity: 0.9;
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .cb-ai-header-actions button {
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.85);
                cursor: pointer;
                padding: 6px;
                border-radius: 6px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                transition: background 0.15s;
            }

            .cb-ai-header-actions button:hover {
                background: rgba(255, 255, 255, 0.2);
                color: #ffffff;
            }

            /* Messages Area */
            .cb-ai-messages {
                flex: 1;
                padding: 16px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 12px;
                background: #f8fafc;
                scroll-behavior: smooth;
            }

            .cb-ai-msg {
                display: flex;
                flex-direction: column;
                max-width: 84%;
                animation: cbAiFadeIn 0.25s ease-out;
            }

            @keyframes cbAiFadeIn {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .cb-ai-msg.bot { align-self: flex-start; }
            .cb-ai-msg.user { align-self: flex-end; }

            .cb-ai-bubble {
                padding: 11px 15px;
                border-radius: 16px;
                font-size: 13.5px;
                line-height: 1.5;
                word-break: break-word;
            }

            .cb-ai-msg.bot .cb-ai-bubble {
                background: #ffffff;
                color: #1e293b;
                border: 1px solid #e2e8f0;
                border-bottom-left-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }

            .cb-ai-msg.user .cb-ai-bubble {
                background: var(--gemini-grad);
                color: #ffffff;
                border-bottom-right-radius: 4px;
                box-shadow: 0 2px 8px var(--gemini-glow);
            }

            .cb-ai-time {
                font-size: 10px;
                color: #94a3b8;
                margin-top: 4px;
                padding: 0 4px;
            }

            .cb-ai-msg.user .cb-ai-time { text-align: right; }

            /* Typing animation */
            .cb-ai-typing {
                display: none;
                align-self: flex-start;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                padding: 10px 14px;
                border-radius: 14px;
                border-bottom-left-radius: 4px;
                align-items: center;
                gap: 6px;
                font-size: 12px;
                color: #7c3aed;
                font-weight: 500;
            }

            .cb-ai-typing.show { display: flex; }

            .cb-ai-typing-dots {
                display: inline-flex;
                gap: 4px;
                align-items: center;
            }

            .cb-ai-dot {
                width: 5px;
                height: 5px;
                background: #a855f7;
                border-radius: 50%;
                animation: cbAiBounce 1.2s infinite ease-in-out;
            }

            .cb-ai-dot:nth-child(2) { animation-delay: 0.2s; }
            .cb-ai-dot:nth-child(3) { animation-delay: 0.4s; }

            @keyframes cbAiBounce {
                0%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-4px); }
            }

            /* Input Area */
            .cb-ai-input-area {
                padding: 12px;
                background: #ffffff;
                border-top: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .cb-ai-input {
                flex: 1;
                border: 1px solid #cbd5e1;
                border-radius: 20px;
                padding: 9px 15px;
                font-size: 13.5px;
                outline: none;
                transition: border-color 0.15s, box-shadow 0.15s;
                font-family: inherit;
            }

            .cb-ai-input:focus {
                border-color: #6366f1;
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
            }

            .cb-ai-send-btn {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                background: var(--gemini-grad);
                color: #ffffff;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.15s ease;
                flex-shrink: 0;
            }

            .cb-ai-send-btn:hover { transform: scale(1.08); }
            .cb-ai-send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

            /* Suggestions chips */
            .cb-ai-suggestions {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-top: 4px;
                align-self: flex-start;
                width: 100%;
            }
            .cb-ai-suggestions-title {
                font-size: 11px;
                font-weight: 700;
                color: #6366f1;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .cb-ai-chips {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .cb-ai-chip {
                background: #ffffff;
                color: #4338ca;
                border: 1px solid #c7d2fe;
                border-radius: 999px;
                padding: 5px 12px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.18s ease;
                text-align: left;
                line-height: 1.35;
                box-shadow: 0 1px 3px rgba(99, 102, 241, 0.08);
            }
            .cb-ai-chip:hover {
                background: #eef2ff;
                border-color: #6366f1;
                color: #3730a3;
                transform: translateY(-1px);
                box-shadow: 0 3px 6px rgba(99, 102, 241, 0.18);
            }

            /* Blinking cursor for typewriter */
            .cb-ai-cursor {
                display: inline-block;
                width: 2px;
                height: 13px;
                background: #6366f1;
                margin-left: 2px;
                vertical-align: middle;
                animation: cbAiCursorBlink 0.7s infinite;
            }
            @keyframes cbAiCursorBlink {
                0%, 100% { opacity: 1; }
                50% { opacity: 0; }
            }
        </style>

        <?php
        $suggestions_raw = $settings['suggestions'] ?? '';
        $suggestions_list = array_filter(array_map('trim', explode("\n", $suggestions_raw)));
        ?>

        <div id="cb-ai-widget">
            <!-- Launcher Button -->
            <button id="cb-ai-launcher" aria-label="Mở Trợ Lý AI" title="2. Chatbot AI DeepSeek (Tư vấn tự do) ✨">
                <span class="cb-ai-sparkle-badge">AI</span>
                <!-- Sparkle SVG Icon -->
                <svg class="cb-ai-open-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2l2.4 6.8L21 10.5l-5.3 4.2 1.8 6.8-5.5-3.8-5.5 3.8 1.8-6.8L3 10.5l6.6-1.7L12 2z"></path>
                </svg>
                <!-- Close SVG Icon -->
                <svg class="cb-ai-close-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- Chat Window -->
            <div id="cb-ai-chatbox">
                <!-- Header -->
                <div class="cb-ai-header">
                    <div class="cb-ai-header-left">
                        <div class="cb-ai-avatar">✨</div>
                        <div>
                            <div class="cb-ai-title"><?php echo esc_html($settings['bot_name']); ?></div>
                            <div class="cb-ai-sub">
                                <span>Powered by DeepSeek AI</span>
                            </div>
                        </div>
                    </div>
                    <div class="cb-ai-header-actions">
                        <button id="cb-ai-reset-btn" title="Xóa ngữ cảnh & bắt đầu lại">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                        </button>
                        <button id="cb-ai-close-btn" title="Thu nhỏ">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages -->
                <div class="cb-ai-messages" id="cb-ai-messages-list">
                    <!-- Welcome msg -->
                    <div class="cb-ai-msg bot">
                        <div class="cb-ai-bubble">
                            <?php echo nl2br(esc_html($settings['welcome_msg'])); ?>
                        </div>
                        <div class="cb-ai-time"><?php echo date_i18n('H:i'); ?></div>
                    </div>

                    <?php if (!empty($suggestions_list)): ?>
                        <div class="cb-ai-suggestions" id="cb-ai-suggestions-box">
                            <div class="cb-ai-suggestions-title">💡 Gợi ý câu hỏi nhanh:</div>
                            <div class="cb-ai-chips">
                                <?php foreach ($suggestions_list as $sug): ?>
                                    <button type="button" class="cb-ai-chip" data-question="<?php echo esc_attr($sug); ?>">
                                        <?php echo esc_html($sug); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Typing Indicator -->
                    <div class="cb-ai-typing" id="cb-ai-typing">
                        <span>✨ AI đang suy luận</span>
                        <span class="cb-ai-typing-dots">
                            <span class="cb-ai-dot"></span>
                            <span class="cb-ai-dot"></span>
                            <span class="cb-ai-dot"></span>
                        </span>
                    </div>
                </div>

                <!-- Input form -->
                <form class="cb-ai-input-area" id="cb-ai-form">
                    <input type="text" class="cb-ai-input" id="cb-ai-input-field" placeholder="Hỏi bất kỳ điều gì với DeepSeek AI..." autocomplete="off">
                    <button type="submit" class="cb-ai-send-btn" id="cb-ai-send-btn" aria-label="Gửi">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <script>
        (function() {
            var widget = document.getElementById('cb-ai-widget');
            var launcher = document.getElementById('cb-ai-launcher');
            var closeBtn = document.getElementById('cb-ai-close-btn');
            var resetBtn = document.getElementById('cb-ai-reset-btn');
            var messagesList = document.getElementById('cb-ai-messages-list');
            var form = document.getElementById('cb-ai-form');
            var input = document.getElementById('cb-ai-input-field');
            var sendBtn = document.getElementById('cb-ai-send-btn');
            var typing = document.getElementById('cb-ai-typing');

            var ajaxUrl = <?php echo json_encode($ajax_url); ?>;
            var nonce = <?php echo json_encode($nonce); ?>;
            var maxHistory = <?php echo $max_history; ?>;
            var history = [];

            // Lưu nội dung khởi tạo ban đầu để khôi phục khi bấm reset
            var initialMessagesHtml = messagesList.innerHTML;

            function scrollToBottom() {
                setTimeout(function() {
                    messagesList.scrollTop = messagesList.scrollHeight;
                }, 40);
            }

            function toggleChat() {
                var wasActive = widget.classList.contains('active');
                document.querySelectorAll('#cb-widget-container, #cb-doc-widget').forEach(function(el) {
                    el.classList.remove('active');
                });
                if (!wasActive) {
                    widget.classList.add('active');
                    setTimeout(function() { input.focus(); }, 150);
                    scrollToBottom();
                } else {
                    widget.classList.remove('active');
                }
            }

            launcher.addEventListener('click', toggleChat);
            closeBtn.addEventListener('click', toggleChat);

            resetBtn.addEventListener('click', function() {
                if (confirm('Bắt đầu cuộc trò chuyện mới và xóa ngữ cảnh cũ?')) {
                    history = [];
                    messagesList.innerHTML = initialMessagesHtml;
                    // Lấy lại tham chiếu typing element sau khi phục hồi html
                    typing = document.getElementById('cb-ai-typing');
                    scrollToBottom();
                }
            });

            // Bấm chọn gợi ý câu hỏi nhanh (Suggestion chip)
            messagesList.addEventListener('click', function(e) {
                var chip = e.target.closest('.cb-ai-chip');
                if (!chip || input.disabled) return;
                var q = chip.getAttribute('data-question') || chip.innerText.trim();
                if (q) {
                    input.value = q;
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                    var sugBox = document.getElementById('cb-ai-suggestions-box');
                    if (sugBox) sugBox.style.display = 'none';
                }
            });

            function getCurrentTime() {
                var d = new Date();
                var h = ('0' + d.getHours()).slice(-2);
                var m = ('0' + d.getMinutes()).slice(-2);
                return h + ':' + m;
            }

            function escapeHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            function appendUserMsg(text) {
                var div = document.createElement('div');
                div.className = 'cb-ai-msg user';
                div.innerHTML = '<div class="cb-ai-bubble">' + escapeHtml(text) + '</div><div class="cb-ai-time">' + getCurrentTime() + '</div>';
                messagesList.insertBefore(div, typing);
                scrollToBottom();
            }

            function appendBotMsg(text) {
                var div = document.createElement('div');
                div.className = 'cb-ai-msg bot';
                var formatted = escapeHtml(text).replace(/\n/g, '<br>');
                div.innerHTML = '<div class="cb-ai-bubble">' + formatted + '</div><div class="cb-ai-time">' + getCurrentTime() + '</div>';
                messagesList.insertBefore(div, typing);
                scrollToBottom();
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var query = input.value.trim();
                if (!query) return;

                var sugBox = document.getElementById('cb-ai-suggestions-box');
                if (sugBox) sugBox.style.display = 'none';

                appendUserMsg(query);
                input.value = '';
                input.disabled = true;
                sendBtn.disabled = true;

                typing.classList.add('show');
                scrollToBottom();

                var formData = new FormData();
                formData.append('action', 'gemini_ai_stream_message');
                formData.append('nonce', nonce);
                formData.append('message', query);
                formData.append('history', JSON.stringify(history));

                var targetText = '';
                var displayedText = '';
                var typingTimer = null;
                var streamDone = false;
                var hasError = false;
                var botMsgDiv = null;
                var bubbleDiv = null;

                function ensureBotBubble() {
                    if (!botMsgDiv) {
                        typing.classList.remove('show');
                        botMsgDiv = document.createElement('div');
                        botMsgDiv.className = 'cb-ai-msg bot';
                        bubbleDiv = document.createElement('div');
                        bubbleDiv.className = 'cb-ai-bubble';
                        botMsgDiv.appendChild(bubbleDiv);
                        var timeDiv = document.createElement('div');
                        timeDiv.className = 'cb-ai-time';
                        timeDiv.innerText = getCurrentTime();
                        botMsgDiv.appendChild(timeDiv);
                        messagesList.insertBefore(botMsgDiv, typing);
                        scrollToBottom();
                    }
                }

                function startTypewriter() {
                    if (typingTimer) return;
                    typingTimer = setInterval(function() {
                        if (displayedText.length < targetText.length) {
                            ensureBotBubble();
                            var backlog = targetText.length - displayedText.length;
                            // Nhịp gõ mượt mà: 1 ký tự / 22ms. Nếu dữ liệu dồn về nhiều thì tăng nhịp để đuổi kịp
                            var step = backlog > 120 ? 4 : (backlog > 60 ? 2 : 1);
                            displayedText = targetText.slice(0, displayedText.length + step);
                            bubbleDiv.innerHTML = escapeHtml(displayedText).replace(/\n/g, '<br>') + '<span class="cb-ai-cursor"></span>';
                            scrollToBottom();
                        } else if (streamDone) {
                            clearInterval(typingTimer);
                            typingTimer = null;
                            if (bubbleDiv && !hasError) {
                                bubbleDiv.innerHTML = escapeHtml(targetText).replace(/\n/g, '<br>');
                            }

                            // Gợi ý câu hỏi tiếp theo cho khách hàng
                            if (botMsgDiv && !hasError && !botMsgDiv.querySelector('.cb-ai-followup-box')) {
                                var fBox = document.createElement('div');
                                fBox.className = 'cb-ai-followup-box';
                                fBox.style.cssText = 'margin-top: 8px; width: 100%;';
                                fBox.innerHTML = '<div class="cb-ai-suggestions-title" style="margin-bottom: 4px;">💡 Gợi ý câu hỏi tiếp theo:</div>';
                                var fChips = document.createElement('div');
                                fChips.className = 'cb-ai-chips';
                                var fashionFollowUps = [
                                    'Cách phối đồ tôn dáng cho người gầy / tròn? 👗',
                                    'Bảng size chi tiết cho áo sơ mi và quần tây 📏',
                                    'Chính sách đổi trả hàng và phí ship thế nào? 🚚',
                                    'Mẹo phối màu quần áo thời thượng, sang trọng 🎨',
                                    'Shop có những mẫu áo thun / sơ mi nào hot nhất? 👕',
                                    'Showroom mở cửa đến mấy giờ và ở đâu? ⏰'
                                ];
                                var count = 0;
                                fashionFollowUps.forEach(function(sug) {
                                    if (count < 3 && query.indexOf(sug.slice(0, 6)) === -1) {
                                        var btn = document.createElement('button');
                                        btn.type = 'button';
                                        btn.className = 'cb-ai-chip';
                                        btn.setAttribute('data-question', sug);
                                        btn.innerText = '👉 ' + sug;
                                        fChips.appendChild(btn);
                                        count++;
                                    }
                                });
                                fBox.appendChild(fChips);
                                var timeEl = botMsgDiv.querySelector('.cb-ai-time');
                                if (timeEl) {
                                    botMsgDiv.insertBefore(fBox, timeEl);
                                } else {
                                    botMsgDiv.appendChild(fBox);
                                }
                            }

                            typing.classList.remove('show');
                            input.disabled = false;
                            sendBtn.disabled = false;
                            input.focus();

                            if (targetText && !hasError) {
                                history.push({ role: 'user', text: query });
                                history.push({ role: 'model', text: targetText });
                                if (history.length > maxHistory * 2) {
                                    history = history.slice(-maxHistory * 2);
                                }
                            }
                            scrollToBottom();
                        }
                    }, 22);
                }

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(res) {
                    if (!res.ok) {
                        throw new Error('HTTP error ' + res.status);
                    }
                    var reader = res.body.getReader();
                    var decoder = new TextDecoder('utf-8');
                    var buffer = '';

                    function readStream() {
                        return reader.read().then(function(chunk) {
                            if (chunk.done) {
                                streamDone = true;
                                startTypewriter();
                                return;
                            }

                            buffer += decoder.decode(chunk.value, { stream: true });
                            var lines = buffer.split('\n');
                            buffer = lines.pop();

                            for (var i = 0; i < lines.length; i++) {
                                var line = lines[i].trim();
                                if (line.indexOf('data: ') === 0) {
                                    var jsonStr = line.substring(6);
                                    try {
                                        var data = JSON.parse(jsonStr);
                                        if (data.text) {
                                            targetText += data.text;
                                            startTypewriter();
                                        }
                                        if (data.error) {
                                            hasError = true;
                                            if (typingTimer) {
                                                clearInterval(typingTimer);
                                                typingTimer = null;
                                            }
                                            ensureBotBubble();
                                            bubbleDiv.innerHTML = '<span style="color:#ef4444; font-size:13px; line-height:1.4; display:block;">⚠️ ' + escapeHtml(data.error) + '</span>';
                                            scrollToBottom();
                                        }
                                    } catch (err) {
                                        // Ignore comments or malformed SSE line
                                    }
                                }
                            }

                            return readStream();
                        });
                    }

                    return readStream();
                })
                .catch(function(err) {
                    if (typingTimer) {
                        clearInterval(typingTimer);
                        typingTimer = null;
                    }
                    typing.classList.remove('show');
                    input.disabled = false;
                    sendBtn.disabled = false;
                    input.focus();
                    appendBotMsg('⚠️ Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại kết nối.');
                });
            });
        })();
        </script>
        <?php
    }
}

// Khởi tạo Plugin
function wp_gemini_ai_chatbot_init() {
    return WP_Gemini_AI_Chatbot::get_instance();
}
add_action('plugins_loaded', 'wp_gemini_ai_chatbot_init');
