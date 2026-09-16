<?php
/**
 * Plugin Name: Chatbot AI Tra Cứu Tài Liệu (Document AI Assistant - DeepSeek)
 * Description: Chatbot hỗ trợ khách hàng tự động thông minh bằng cách đọc hiểu 1 tài liệu duy nhất (Document) và trả lời theo lời văn tự nhiên sử dụng DeepSeek API (deepseek-flash, deepseek-v4-pro). Nếu tài liệu không có thông tin, bot tự động phản hồi "Nhân viên sẽ liên lạc với bạn sớm".
 * Version: 1.1.0
 * Author: Antigravity
 * Text Domain: chatbot-doc-ai
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WP_Document_AI_Chatbot {

    const VERSION = '1.1.0';
    const OPTION_DOC = 'wp_doc_chatbot_document';
    const OPTION_SETTINGS = 'wp_doc_chatbot_settings';
    const OPTION_LEADS = 'wp_doc_chatbot_leads';
    const OPTION_LOGS = 'wp_doc_chatbot_logs';

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

        // AJAX endpoints
        add_action('wp_ajax_doc_ai_send_message', [$this, 'ajax_handle_message']);
        add_action('wp_ajax_nopriv_doc_ai_send_message', [$this, 'ajax_handle_message']);

        add_action('wp_ajax_doc_ai_stream_message', [$this, 'ajax_stream_message']);
        add_action('wp_ajax_nopriv_doc_ai_stream_message', [$this, 'ajax_stream_message']);

        add_action('wp_ajax_doc_ai_save_lead', [$this, 'ajax_save_lead']);
        add_action('wp_ajax_nopriv_doc_ai_save_lead', [$this, 'ajax_save_lead']);

        add_action('wp_ajax_doc_ai_test_api', [$this, 'ajax_test_api']);

        // Auto initialize
        if (get_option(self::OPTION_SETTINGS) === false || get_option(self::OPTION_DOC) === false) {
            self::on_activate();
        }
    }

    public static function get_sample_document() {
        return "CẨM NANG TOÀN DIỆN & TÀI LIỆU CỬA HÀNG THỜI TRANG LTTHEME FASHION:\n\n" .
            "1. GIỚI THIỆU CHUNG VỀ CỬA HÀNG:\n" .
            "- LTTheme Fashion là thương hiệu thời trang thiết kế công sở, dạo phố và dạ tiệc cao cấp hàng đầu Việt Nam.\n" .
            "- Định hướng thiết kế: Hiện đại, thanh lịch, chuẩn form dáng người Việt, sử dụng các chất liệu cao cấp (Linen tự nhiên, Lụa tơ tằm, Cotton Compact 100%, Wool dệt kim, Voan tơ óng ả).\n\n" .
            "2. HỆ THỐNG SHOWROOM & THỜI GIAN PHỤC VỤ:\n" .
            "- Showroom Hà Nội: 123 Phố Cầu Giấy, Quận Cầu Giấy, TP. Hà Nội. (Hotline: 0988.123.456)\n" .
            "- Showroom TP. Hồ Chí Minh: 456 Đường Lê Văn Sỹ, Phường 14, Quận 3, TP. Hồ Chí Minh. (Hotline: 0912.987.654)\n" .
            "- Giờ mở cửa đón khách: 8h30 sáng đến 22h00 tối tất cả các ngày trong tuần (kể cả Thứ Bảy, Chủ Nhật và ngày lễ).\n" .
            "- Hotline đặt hàng & CSKH: 0988.123.456 (Hỗ trợ từ 8h00 - 22h30 hàng ngày).\n" .
            "- Email liên hệ: cskh@ltthemefashion.vn | Website: ltthemefashion.vn\n\n" .
            "3. CÁC DÒNG SẢN PHẨM NỔI BẬT:\n" .
            "- Thời trang Nữ: Đầm xòe hoa nhí, Đầm suông chữ A tôn dáng giấu bụng, Đầm dạ hội lụa satin sang trọng, Áo sơ mi lụa cổ nơ, Áo sơ mi basic chống nhăn, Áo blazer nữ 2 lớp đứng form, Chân váy bút chì, Chân váy xếp ly midi dài, Quần tây nữ ống suông cạp cao hack dáng đôi chân dài.\n" .
            "- Thời trang Nam: Áo polo nam sợi cafe / dệt kim co giãn 4 chiều mát lạnh khử mùi, Áo sơ mi nam sợi tre Bamboo và Oxford chống nhăn tuyệt đối, Quần âu nam co giãn cạp thông minh, Quần khaki trẻ trung, Quần short đũi nam thoáng khí, Áo blazer nam mỏng nhẹ hiện đại.\n" .
            "- Phụ kiện thời trang cao cấp: Khăn lụa vuông tơ tằm, Thắt lưng da bò thật, Cà vạt dệt thủ công, Túi xách và ví cầm tay.\n\n" .
            "4. BẢNG SIZE CHUẨN CHI TIẾT (CHIỀU CAO & CÂN NẶNG):\n" .
            "* BẢNG SIZE NỮ:\n" .
            "  + Size S: Chiều cao 1m50 - 1m58 | Cân nặng 42kg - 48kg | Vòng ngực 80-84cm | Vòng eo 62-66cm\n" .
            "  + Size M: Chiều cao 1m55 - 1m62 | Cân nặng 49kg - 54kg | Vòng ngực 85-88cm | Vòng eo 67-71cm\n" .
            "  + Size L: Chiều cao 1m58 - 1m66 | Cân nặng 55kg - 60kg | Vòng ngực 89-93cm | Vòng eo 72-76cm\n" .
            "  + Size XL: Chiều cao 1m60 - 1m70 | Cân nặng 61kg - 68kg | Vòng ngực 94-98cm | Vòng eo 77-82cm\n" .
            "  + Size XXL: Chiều cao 1m60 - 1m72 | Cân nặng 69kg - 76kg | Vòng ngực 99-105cm | Vòng eo 83-90cm\n" .
            "* BẢNG SIZE NAM:\n" .
            "  + Size S (48): Chiều cao 1m60 - 1m65 | Cân nặng 50kg - 57kg | Vòng ngực 86-90cm\n" .
            "  + Size M (50): Chiều cao 1m65 - 1m70 | Cân nặng 58kg - 65kg | Vòng ngực 91-95cm\n" .
            "  + Size L (52): Chiều cao 1m68 - 1m75 | Cân nặng 66kg - 73kg | Vòng ngực 96-100cm\n" .
            "  + Size XL (54): Chiều cao 1m72 - 1m80 | Cân nặng 74kg - 82kg | Vòng ngực 101-106cm\n" .
            "  + Size XXL (56): Chiều cao 1m75 - 1m85 | Cân nặng 83kg - 92kg | Vòng ngực 107-114cm\n" .
            "* Lưu ý: Nếu khách hàng có vòng bụng hoặc bắp tay to, hoặc phân vân giữa 2 size thì nên chọn tăng lên 1 size để mặc thoải mái nhất.\n\n" .
            "5. CHÍNH SÁCH GIAO HÀNG & PHÍ VẬN CHUYỂN:\n" .
            "- Miễn phí vận chuyển (Freeship) 100% toàn quốc cho mọi đơn hàng từ 300.000đ trở lên.\n" .
            "- Đơn hàng dưới 300.000đ: Phí vận chuyển đồng giá chỉ 25.000đ trên toàn quốc.\n" .
            "- Giao hàng hỏa tốc trong 2-4 giờ tại nội thành Hà Nội & TP.HCM.\n" .
            "- Thời gian giao hàng tiêu chuẩn: 1 - 2 ngày (Hà Nội, TP.HCM) và 2 - 4 ngày đối với các tỉnh thành khác.\n" .
            "- Khách hàng được quyền KIỂM TRA HÀNG (đồng kiểm) trước khi nhận và thanh toán cho shipper.\n\n" .
            "6. CHÍNH SÁCH ĐỔI TRẢ & HOÀN TIỀN TRONG 7 NGÀY:\n" .
            "- Thời hạn đổi hàng: Trong vòng 7 ngày kể từ ngày nhận hàng thành công.\n" .
            "- Điều kiện đổi: Sản phẩm còn nguyên vẹn tem mác, chưa qua sử dụng, chưa giặt tẩy và không dính vết bẩn hay mùi lạ.\n" .
            "- Hỗ trợ đổi size nếu mặc không vừa, hoặc đổi sang sản phẩm bất kỳ khác có giá trị tương đương hoặc lớn hơn.\n" .
            "- Lỗi do nhà sản xuất (rách, ố bẩn, đứt chỉ): Shop chịu 100% chi phí vận chuyển 2 chiều để gửi sản phẩm mới cho khách.\n" .
            "- Đổi hàng do sở thích cá nhân: Khách thanh toán cước phí gửi 1 chiều hoặc mang qua trực tiếp Showroom để đổi hoàn toàn miễn phí.\n\n" .
            "7. HÌNH THỨC THANH TOÁN:\n" .
            "- Thanh toán COD (tiền mặt khi shipper giao hàng tận nơi).\n" .
            "- Quét mã VietQR chuyển khoản ngân hàng 24/7.\n" .
            "- Thanh toán qua ví điện tử: MoMo, ZaloPay, VNPay.\n" .
            "- Thẻ ngân hàng ATM nội địa, Visa, Mastercard, JCB.\n\n" .
            "8. HƯỚNG DẪN BẢO QUẢN & GIẶT ỦI TRANG PHỤC:\n" .
            "- Đồ Lụa tơ tằm, Voan tơ và Linen: Ưu tiên giặt tay với nước lạnh dưới 30 độ C hoặc dùng túi giặt ở chế độ giặt nhẹ; không vắt xoắn mạnh.\n" .
            "- Tuyệt đối không dùng thuốc tẩy chứa clo hoặc chất tẩy rửa cực mạnh.\n" .
            "- Phơi trang phục trong bóng râm, nơi thoáng gió mát mẻ, tránh ánh nắng mặt trời chiếu trực tiếp làm giòn sợi vải và phai màu.\n" .
            "- Là/ủi bằng bàn là hơi nước hoặc chọn nhiệt độ thấp dưới 110 độ C đối với chất liệu lụa, voan.\n\n" .
            "9. CHÍNH SÁCH KHÁCH HÀNG THÂN THIẾT & ƯU ĐÃI VIP:\n" .
            "- Khách hàng mới: Nhận ngay Voucher giảm giá 10% cho đơn hàng đầu tiên khi đăng ký thành viên (Mã: WELCOME10).\n" .
            "- Hạng Bạc (Tích lũy từ 2.000.000đ): Giảm 5% cho tất cả các đơn hàng tiếp theo.\n" .
            "- Hạng Vàng (Tích lũy từ 5.000.000đ): Giảm 10% trọn đời + Tặng quà sinh nhật đặc biệt trị giá 300.000đ.\n" .
            "- Hạng Kim Cương (Tích lũy từ 10.000.000đ): Giảm 15% trọn đời + Miễn phí vận chuyển trọn đời cho mọi đơn hàng.";
    }

    public static function on_activate() {
        $sample_document = self::get_sample_document();
        $current_doc = get_option(self::OPTION_DOC, false);
        if ($current_doc === false || empty($current_doc) || mb_stripos($current_doc, 'công nghệ') !== false) {
            update_option(self::OPTION_DOC, $sample_document);
        }

        $default_key = 'sk-4d92ceabf90e47cfbe6286db7786ef1d';
        if (get_option(self::OPTION_SETTINGS) === false) {
            $default_settings = [
                'api_key' => $default_key,
                'model' => 'deepseek-flash',
                'temperature' => 0.4,
                'bot_name' => 'Trợ Lý Cửa Hàng Thời Trang 📖',
                'welcome_msg' => 'Kính chào quý khách! Tôi là Trợ lý AI tra cứu tài liệu & sản phẩm của Shop Quần Áo LTTheme Fashion 👗. Bạn cần tra cứu bảng size, mẫu mã quần áo hay chính sách mua hàng nào ạ?',
                'fallback_msg' => 'Nhân viên sẽ liên lạc với bạn sớm',
                'primary_color' => '#059669',
                'secondary_color' => '#10b981',
                'bot_position' => 'left',
                'offset_bottom' => '24',
                'enable_widget' => '1',
                'enable_lead_capture' => '1',
                'max_history_turns' => 8,
                'suggestions' => "Bảng chọn size quần áo nam nữ chuẩn 📏\nChính sách đổi trả trong vòng 7 ngày 🔄\nPhí ship và đơn hàng từ 300.000đ 🚚\nHướng dẫn giặt là và bảo quản đồ lụa/linen 🧺\nGợi ý set đồ công sở thanh lịch tôn dáng ✨"
            ];
            update_option(self::OPTION_SETTINGS, $default_settings);
        }

        if (get_option(self::OPTION_LEADS) === false) {
            update_option(self::OPTION_LEADS, []);
        }

        if (get_option(self::OPTION_LOGS) === false) {
            update_option(self::OPTION_LOGS, []);
        }
    }

    public static function get_settings() {
        $default_key = 'sk-4d92ceabf90e47cfbe6286db7786ef1d';
        $defaults = [
            'api_key' => $default_key,
            'model' => 'deepseek-flash',
            'temperature' => 0.4,
            'bot_name' => 'Trợ Lý Cửa Hàng Thời Trang 📖',
            'welcome_msg' => 'Kính chào quý khách! Tôi là Trợ lý AI tra cứu tài liệu & sản phẩm của Shop Quần Áo LTTheme Fashion 👗. Bạn cần tra cứu bảng size, mẫu mã quần áo hay chính sách mua hàng nào ạ?',
            'fallback_msg' => 'Nhân viên sẽ liên lạc với bạn sớm',
            'primary_color' => '#059669',
            'secondary_color' => '#10b981',
            'bot_position' => 'right',
            'offset_bottom' => '160',
            'enable_widget' => '1',
            'enable_lead_capture' => '1',
            'max_history_turns' => 8,
            'suggestions' => "Bảng chọn size quần áo nam nữ chuẩn 📏\nChính sách đổi trả trong vòng 7 ngày 🔄\nPhí ship và đơn hàng từ 300.000đ 🚚\nHướng dẫn giặt là và bảo quản đồ lụa/linen 🧺\nGợi ý set đồ công sở thanh lịch tôn dáng ✨"
        ];
        $settings = get_option(self::OPTION_SETTINGS, []);
        $parsed = wp_parse_args($settings, $defaults);

        // Tự động chuyển đổi khóa API Key cũ hoặc rỗng sang DeepSeek API Key
        if (empty($parsed['api_key']) || strpos($parsed['api_key'], 'AIzaSy') === 0) {
            $parsed['api_key'] = $default_key;
        }

        // Tự động chuyển đổi mô hình cũ sang DeepSeek model
        $allowed_models = ['deepseek-flash', 'deepseek-v4-pro'];
        if (empty($parsed['model']) || !in_array($parsed['model'], $allowed_models)) {
            $parsed['model'] = 'deepseek-flash';
        }

        if (isset($parsed['bot_name']) && ($parsed['bot_name'] === 'Trợ Lý Tài Liệu AI 📖' || $parsed['bot_name'] === 'Trợ Lý AI Hybrid' || empty($parsed['bot_name']))) {
            $parsed['bot_name'] = $defaults['bot_name'];
        }
        if (isset($parsed['welcome_msg']) && (mb_stripos($parsed['welcome_msg'], 'đọc hiểu tài liệu') !== false || mb_stripos($parsed['welcome_msg'], 'Trợ lý AI Hybrid') !== false || empty($parsed['welcome_msg']))) {
            $parsed['welcome_msg'] = $defaults['welcome_msg'];
        }
        if (isset($parsed['suggestions']) && (mb_stripos($parsed['suggestions'], 'yêu thích hoa hồng') !== false || empty($parsed['suggestions']))) {
            $parsed['suggestions'] = $defaults['suggestions'];
        }
        $parsed['offset_bottom'] = '160';
        $parsed['bot_position'] = 'right';
        return $parsed;
    }

    public static function get_document() {
        $doc = get_option(self::OPTION_DOC, '');
        if (empty($doc) || mb_stripos($doc, 'công nghệ') !== false) {
            $sample = self::get_sample_document();
            update_option(self::OPTION_DOC, $sample);
            return $sample;
        }
        return $doc;
    }

    public function register_admin_menu() {
        add_menu_page(
            'Chatbot Tài Liệu AI',
            'Chatbot Tài Liệu AI',
            'manage_options',
            'chatbot-doc-ai',
            [$this, 'render_admin_page'],
            'dashicons-book',
            28
        );
    }

    public function handle_admin_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'chatbot-doc-ai') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Lưu Tài Liệu duy nhất
        if (isset($_POST['doc_ai_action']) && $_POST['doc_ai_action'] === 'save_document') {
            check_admin_referer('doc_ai_save_doc_nonce');
            $doc_content = wp_unslash($_POST['document_content'] ?? '');
            update_option(self::OPTION_DOC, trim($doc_content));
            wp_safe_redirect(admin_url('admin.php?page=chatbot-doc-ai&tab=document&msg=doc_saved'));
            exit;
        }

        // Nạp lại tài liệu mẫu
        if (isset($_POST['doc_ai_action']) && $_POST['doc_ai_action'] === 'load_sample_document') {
            check_admin_referer('doc_ai_sample_doc_nonce');
            delete_option(self::OPTION_DOC);
            self::on_activate();
            wp_safe_redirect(admin_url('admin.php?page=chatbot-doc-ai&tab=document&msg=sample_loaded'));
            exit;
        }

        // Lưu Cài đặt
        if (isset($_POST['doc_ai_action']) && $_POST['doc_ai_action'] === 'save_settings') {
            check_admin_referer('doc_ai_save_settings_nonce');

            $current = self::get_settings();
            $new_api_key = sanitize_text_field($_POST['api_key'] ?? '');
            $allowed_models = ['deepseek-flash', 'deepseek-v4-pro'];
            $selected_model = in_array($_POST['model'] ?? '', $allowed_models) ? $_POST['model'] : 'deepseek-flash';

            $settings = [
                'api_key' => !empty($new_api_key) ? trim($new_api_key) : $current['api_key'],
                'model' => $selected_model,
                'temperature' => floatval($_POST['temperature'] ?? 0.4),
                'bot_name' => sanitize_text_field($_POST['bot_name'] ?? 'Trợ Lý Cửa Hàng Thời Trang 📖'),
                'welcome_msg' => sanitize_textarea_field($_POST['welcome_msg'] ?? ''),
                'fallback_msg' => sanitize_text_field($_POST['fallback_msg'] ?? 'Nhân viên sẽ liên lạc với bạn sớm'),
                'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#059669'),
                'secondary_color' => sanitize_hex_color($_POST['secondary_color'] ?? '#10b981'),
                'bot_position' => in_array($_POST['bot_position'] ?? '', ['right', 'left']) ? $_POST['bot_position'] : 'left',
                'offset_bottom' => absint($_POST['offset_bottom'] ?? 24),
                'enable_widget' => isset($_POST['enable_widget']) ? '1' : '0',
                'enable_lead_capture' => isset($_POST['enable_lead_capture']) ? '1' : '0',
                'max_history_turns' => absint($_POST['max_history_turns'] ?? 8),
                'suggestions' => sanitize_textarea_field($_POST['suggestions'] ?? '')
            ];

            update_option(self::OPTION_SETTINGS, $settings);
            wp_safe_redirect(admin_url('admin.php?page=chatbot-doc-ai&tab=settings&msg=settings_saved'));
            exit;
        }

        // Xóa tất cả Leads
        if (isset($_POST['doc_ai_action']) && $_POST['doc_ai_action'] === 'clear_leads') {
            check_admin_referer('doc_ai_clear_leads_nonce');
            update_option(self::OPTION_LEADS, []);
            wp_safe_redirect(admin_url('admin.php?page=chatbot-doc-ai&tab=leads&msg=leads_cleared'));
            exit;
        }

        // Xóa tất cả logs
        if (isset($_POST['doc_ai_action']) && $_POST['doc_ai_action'] === 'clear_logs') {
            check_admin_referer('doc_ai_clear_logs_nonce');
            update_option(self::OPTION_LOGS, []);
            wp_safe_redirect(admin_url('admin.php?page=chatbot-doc-ai&tab=logs&msg=logs_cleared'));
            exit;
        }
    }

    /**
     * Ghi log cuộc hội thoại
     */
    private static function log_conversation($user_msg, $ai_reply, $source = 'doc', $status = 'success') {
        $logs = get_option(self::OPTION_LOGS, []);
        if (!is_array($logs)) $logs = [];

        $logs[] = [
            'id' => 'log_' . time() . '_' . wp_rand(100, 999),
            'time' => current_time('d/m/Y H:i:s'),
            'user' => $user_msg,
            'ai' => $ai_reply,
            'source' => $source,
            'status' => $status
        ];

        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }

        update_option(self::OPTION_LOGS, $logs);
    }

    /**
     * Gọi DeepSeek API với toàn bộ Document làm Context (OpenAI-compatible)
     */
    public static function query_document_ai($user_message, $history = [], $custom_key = null, $custom_model = null) {
        $settings = self::get_settings();
        $api_key = !empty($custom_key) ? trim($custom_key) : trim($settings['api_key']);
        $model = !empty($custom_model) ? trim($custom_model) : $settings['model'];
        $document = self::get_document();
        $fallback_msg = !empty($settings['fallback_msg']) ? $settings['fallback_msg'] : 'Nhân viên sẽ liên lạc với bạn sớm';

        if (empty($api_key)) {
            return [
                'success' => true,
                'is_fallback' => true,
                'answer' => $fallback_msg,
                'note' => 'Chưa cấu hình DeepSeek API Key'
            ];
        }

        $endpoint = 'https://api.deepseek.com/chat/completions';

        // Xây dựng System Instruction Hybrid: Kết hợp giữa Tài liệu có sẵn & Trí tuệ nhân tạo DeepSeek AI
        $system_instruction = "Bạn là Trợ lý tư vấn khách hàng thông minh, lễ phép và trung thực của cửa hàng (Hệ thống Chatbot Hybrid kết hợp giữa Dữ liệu tài liệu có sẵn và Trí tuệ nhân tạo DeepSeek AI).\n" .
            "DƯỚI ĐÂY LÀ TOÀN BỘ TÀI LIỆU THÔNG TIN & CHÍNH SÁCH DOANH NGHIỆP:\n" .
            "=== BẮT ĐẦU TÀI LIỆU ===\n" .
            $document . "\n" .
            "=== KẾT THÚC TÀI LIỆU ===\n\n" .
            "QUY TẮC HOẠT ĐỘNG VÀ PHÂN LOẠI NGUỒN (BẮT BUỘC TUÂN THỦ):\n" .
            "1. DÒ TÀI LIỆU TRƯỚC (ƯU TIÊN 1):\n" .
            "   - Hãy kiểm tra kỹ xem thông tin để trả lời câu hỏi có trong TÀI LIỆU ở trên hay không.\n" .
            "   - Nếu CÓ trong tài liệu: Bạn bắt buộc trả lời dựa trên tài liệu, và Ở DÒNG ĐẦU TIÊN CỦA CÂU TRẢ LỜI bắt buộc phải ghi đúng tag:\n" .
            "     [NGUON: TAI_LIEU]\n" .
            "2. DÙNG AI SUY LUẬN (ƯU TIÊN 2):\n" .
            "   - Nếu câu hỏi KHÔNG có trong tài liệu nhưng là câu hỏi kiến thức chung, tư vấn gợi ý quà tặng, phối đồ, lời chào hỏi, tính toán, phân tích hoặc tư vấn mở rộng: Hãy dùng trí tuệ nhân tạo (AI) để tư vấn cho khách hàng chu đáo, tự nhiên và Ở DÒNG ĐẦU TIÊN CỦA CÂU TRẢ LỜI bắt buộc phải ghi đúng tag:\n" .
            "     [NGUON: AI]\n" .
            "3. TRƯỜNG HỢP FALLBACK CỦA CỬA HÀNG:\n" .
            "   - Nếu câu hỏi hỏi về các chính sách riêng tư, giá đặt làm riêng, kiểm tra đơn hàng, hoặc thông tin nội bộ đặc thù của shop mà TÀI LIỆU KHÔNG CÓ và AI KHÔNG ĐƯỢC TỰ BỊA ĐẶT:\n" .
            "     Hãy trả lời chính xác câu:\n" .
            "     [NGUON: TAI_LIEU]\n" .
            "     " . $fallback_msg . "\n" .
            "4. ĐỊNH DẠNG XUẤT DỮ LIỆU:\n" .
            "   - Luôn đặt dòng tag [NGUON: TAI_LIEU] hoặc [NGUON: AI] ở ngay dòng đầu tiên (dòng 1).\n" .
            "   - Sau đó xuống dòng và trả lời bằng tiếng Việt lịch sự, thân thiện, ngắn gọn và rõ ràng.\n" .
            "5. GHI NHỚ NGỮ CẢNH HỘI THOẠI (MULTI-TURN MEMORY):\n" .
            "   - Hãy luôn đọc kỹ lịch sử trò chuyện phía trước để hiểu ngữ cảnh các câu hỏi liên tiếp của khách (các đại từ 'nó', 'sản phẩm đó', 'cái này', 'chi nhánh 2',...) và trả lời nhất quán, logic.";

        $messages = [];
        $messages[] = [
            'role' => 'system',
            'content' => $system_instruction
        ];

        // Thêm các lượt chat trước
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

        // Thêm câu hỏi hiện tại
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
                'Authorization' => 'Bearer ' . $api_key
            ],
            'body' => json_encode($payload),
            'timeout' => 50,
            'sslverify' => false,
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            return [
                'success' => true,
                'is_fallback' => true,
                'answer' => $fallback_msg,
                'source' => 'doc',
                'note' => 'Lỗi kết nối API: ' . $response->get_error_message()
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($status_code !== 200) {
            $err_note = $data['error']['message'] ?? ('API Code: ' . $status_code);
            if ($status_code === 402 || stripos($err_note, 'Insufficient Balance') !== false) {
                $err_note = 'Tài khoản DeepSeek có số dư 0 USD (Insufficient Balance). Cần nạp tiền tại platform.deepseek.com.';
            }
            return [
                'success' => true,
                'is_fallback' => true,
                'answer' => $fallback_msg,
                'source' => 'doc',
                'status_code' => $status_code,
                'note' => $err_note
            ];
        }

        if (isset($data['choices'][0]['message']['content'])) {
            $ai_answer = trim($data['choices'][0]['message']['content']);

            $source = 'doc';
            if (stripos($ai_answer, '[NGUON: AI]') !== false) {
                $source = 'ai';
            }
            $clean_answer = trim(preg_replace('/\[NGUON:\s*(TAI_LIEU|AI)\]/i', '', $ai_answer));

            // Kiểm tra xem phản hồi có chứa câu fallback không
            $is_fallback = (mb_stripos($clean_answer, $fallback_msg) !== false || mb_stripos($clean_answer, 'nhân viên sẽ liên lạc') !== false);

            self::log_conversation($user_message, $clean_answer, $source, 'success');

            return [
                'success' => true,
                'is_fallback' => $is_fallback,
                'answer' => $clean_answer,
                'source' => $source,
                'model' => $model
            ];
        }

        return [
            'success' => true,
            'is_fallback' => true,
            'source' => 'doc',
            'answer' => $fallback_msg
        ];
    }

    /**
     * AJAX: Xử lý tin nhắn khách hỏi
     */
    public function ajax_handle_message() {
        check_ajax_referer('doc_ai_widget_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_send_json_error(['message' => 'Vui lòng nhập nội dung câu hỏi.']);
        }

        $history = [];
        if (!empty($_POST['history'])) {
            $raw_history = json_decode(stripslashes($_POST['history']), true);
            if (is_array($raw_history)) {
                $history = $raw_history;
            }
        }

        $result = self::query_document_ai($message, $history);
        $settings = self::get_settings();

        wp_send_json_success([
            'answer' => nl2br(esc_html($result['answer'])),
            'source' => $result['source'] ?? 'doc',
            'is_fallback' => $result['is_fallback'],
            'enable_lead_capture' => ($settings['enable_lead_capture'] === '1')
        ]);
    }

    /**
     * AJAX: Xử lý streaming tin nhắn tra cứu tài liệu theo thời gian thực (SSE)
     */
    public function ajax_stream_message() {
        check_ajax_referer('doc_ai_widget_nonce', 'nonce');

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

        self::stream_document_ai($message, $history);
        exit;
    }

    /**
     * Gọi DeepSeek API dạng Streaming với toàn bộ Document làm Context
     */
    public static function stream_document_ai($user_message, $history = []) {
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

        // Padding comment để đẩy buffer ngay lập tức
        echo ":" . str_repeat(" ", 2048) . "\n\n";
        flush();

        $settings = self::get_settings();
        $api_key = trim($settings['api_key']);
        $model = !empty($settings['model']) ? trim($settings['model']) : 'deepseek-flash';
        $document = self::get_document();
        $fallback_msg = !empty($settings['fallback_msg']) ? $settings['fallback_msg'] : 'Nhân viên sẽ liên lạc với bạn sớm';

        if (empty($api_key)) {
            echo "data: " . json_encode(['text' => $fallback_msg], JSON_UNESCAPED_UNICODE) . "\n\n";
            echo "data: " . json_encode([
                'done' => true,
                'full_text' => $fallback_msg,
                'is_fallback' => true,
                'enable_lead_capture' => ($settings['enable_lead_capture'] === '1')
            ], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
            exit;
        }

        $endpoint = 'https://api.deepseek.com/chat/completions';

        $system_instruction = "Bạn là Trợ lý tư vấn khách hàng thông minh, lễ phép và trung thực của cửa hàng (Hệ thống Chatbot Hybrid kết hợp giữa Dữ liệu tài liệu có sẵn và Trí tuệ nhân tạo DeepSeek AI).\n" .
            "DƯỚI ĐÂY LÀ TOÀN BỘ TÀI LIỆU THÔNG TIN & CHÍNH SÁCH DOANH NGHIỆP:\n" .
            "=== BẮT ĐẦU TÀI LIỆU ===\n" .
            $document . "\n" .
            "=== KẾT THÚC TÀI LIỆU ===\n\n" .
            "QUY TẮC HOẠT ĐỘNG VÀ PHÂN LOẠI NGUỒN (BẮT BUỘC TUÂN THỦ):\n" .
            "1. DÒ TÀI LIỆU TRƯỚC (ƯU TIÊN 1):\n" .
            "   - Hãy kiểm tra kỹ xem thông tin để trả lời câu hỏi có trong TÀI LIỆU ở trên hay không.\n" .
            "   - Nếu CÓ trong tài liệu: Bạn bắt buộc trả lời dựa trên tài liệu, và Ở DÒNG ĐẦU TIÊN CỦA CÂU TRẢ LỜI bắt buộc phải ghi đúng tag:\n" .
            "     [NGUON: TAI_LIEU]\n" .
            "2. DÙNG AI SUY LUẬN (ƯU TIÊN 2):\n" .
            "   - Nếu câu hỏi KHÔNG có trong tài liệu nhưng là câu hỏi kiến thức chung, tư vấn gợi ý quà tặng, phối đồ, lời chào hỏi, tính toán, phân tích hoặc tư vấn mở rộng: Hãy dùng trí tuệ nhân tạo (AI) để tư vấn cho khách hàng chu đáo, tự nhiên và Ở DÒNG ĐẦU TIÊN CỦA CÂU TRẢ LỜI bắt buộc phải ghi đúng tag:\n" .
            "     [NGUON: AI]\n" .
            "3. TRƯỜNG HỢP FALLBACK CỦA CỬA HÀNG:\n" .
            "   - Nếu câu hỏi hỏi về các chính sách riêng tư, giá đặt làm riêng, kiểm tra đơn hàng, hoặc thông tin nội bộ đặc thù của shop mà TÀI LIỆU KHÔNG CÓ và AI KHÔNG ĐƯỢC TỰ BỊA ĐẶT:\n" .
            "     Hãy trả lời chính xác câu:\n" .
            "     [NGUON: TAI_LIEU]\n" .
            "     " . $fallback_msg . "\n" .
            "4. ĐỊNH DẠNG XUẤT DỮ LIỆU:\n" .
            "   - Luôn đặt dòng tag [NGUON: TAI_LIEU] hoặc [NGUON: AI] ở ngay dòng đầu tiên (dòng 1).\n" .
            "   - Sau đó xuống dòng và trả lời bằng tiếng Việt lịch sự, thân thiện, ngắn gọn và rõ ràng.\n" .
            "5. GHI NHỚ NGỮ CẢNH HỘI THOẠI (MULTI-TURN MEMORY):\n" .
            "   - Hãy luôn đọc kỹ lịch sử trò chuyện phía trước để hiểu ngữ cảnh các câu hỏi liên tiếp của khách (các đại từ 'nó', 'sản phẩm đó', 'cái này', 'chi nhánh 2',...) và trả lời nhất quán, logic.";

        $messages = [];
        $messages[] = [
            'role' => 'system',
            'content' => $system_instruction
        ];

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

        if (empty($accumulated_text) && ($http_status !== 200 || !empty($curl_error))) {
            $accumulated_text = $fallback_msg;
            echo "data: " . json_encode(['text' => $fallback_msg], JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        $source = 'doc';
        if (stripos($accumulated_text, '[NGUON: AI]') !== false) {
            $source = 'ai';
        }
        $clean_text = trim(preg_replace('/\[NGUON:\s*(TAI_LIEU|AI)\]/i', '', $accumulated_text));
        $is_fallback = (mb_stripos($accumulated_text, $fallback_msg) !== false || mb_stripos($accumulated_text, 'nhân viên sẽ liên lạc') !== false);

        if (!empty($clean_text)) {
            self::log_conversation($user_message, $clean_text, $source, 'success');
        }

        echo "data: " . json_encode([
            'done' => true,
            'full_text' => $clean_text,
            'source' => $source,
            'is_fallback' => $is_fallback,
            'enable_lead_capture' => ($settings['enable_lead_capture'] === '1')
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
        exit;
    }

    /**
     * AJAX: Lưu số điện thoại khi fallback
     */
    public function ajax_save_lead() {
        check_ajax_referer('doc_ai_widget_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $question = sanitize_text_field($_POST['question'] ?? '');

        if (empty($contact)) {
            wp_send_json_error(['message' => 'Vui lòng nhập số điện thoại hoặc thông tin liên hệ.']);
        }

        $leads = get_option(self::OPTION_LEADS, []);
        if (!is_array($leads)) $leads = [];

        $leads[] = [
            'id' => 'lead_' . time() . '_' . wp_rand(100, 999),
            'time' => current_time('d/m/Y H:i'),
            'contact' => $contact,
            'question' => $question
        ];

        update_option(self::OPTION_LEADS, $leads);

        wp_send_json_success([
            'message' => 'Đã gửi thông tin thành công! Nhân viên sẽ chủ động liên hệ hỗ trợ bạn sớm.'
        ]);
    }

    /**
     * AJAX: Test kết nối API DeepSeek
     */
    public function ajax_test_api() {
        check_ajax_referer('doc_ai_test_api_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền thực hiện.']);
        }

        $api_key = sanitize_text_field($_POST['api_key'] ?? '');
        $raw_model = sanitize_text_field($_POST['model'] ?? 'deepseek-flash');
        $allowed_models = ['deepseek-flash', 'deepseek-v4-pro'];
        $model = in_array($raw_model, $allowed_models) ? $raw_model : 'deepseek-flash';

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

        // Bước 3: Thử nghiệm query tài liệu
        $result = self::query_document_ai("Shop mở cửa đến mấy giờ?", [], $api_key, $model);

        if (!empty($result['answer']) && empty($result['note'])) {
            wp_send_json_success([
                'message' => 'Kết nối thành công tới DeepSeek API!' . $balance_str,
                'response' => $result['answer']
            ]);
        } else {
            if (($result['status_code'] ?? 0) === 402 || !$is_available || (isset($result['note']) && stripos($result['note'], 'Insufficient Balance') !== false)) {
                wp_send_json_success([
                    'message' => 'Xác thực DeepSeek API Key thành công 100%!' . $balance_str,
                    'response' => 'Khóa API hoàn toàn hợp lệ. Số dư hiện tại là 0 USD, bạn cần nạp tiền (top-up) tại platform.deepseek.com để AI đọc hiểu tài liệu trực tiếp.'
                ]);
            } else {
                wp_send_json_error([
                    'message' => $result['note'] ?? 'Không thể nhận phản hồi từ DeepSeek API.'
                ]);
            }
        }
    }

    /**
     * Render Giao diện Quản trị Admin
     */
    public function render_admin_page() {
        $tab = sanitize_key($_GET['tab'] ?? 'document');
        $document = self::get_document();
        $settings = self::get_settings();
        $leads = get_option(self::OPTION_LEADS, []);
        if (!is_array($leads)) $leads = [];
        $logs = get_option(self::OPTION_LOGS, []);
        if (!is_array($logs)) $logs = [];
        ?>
        <style>
            .doc-wrap { margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
            .doc-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 25px; }
            .doc-header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #064e3b; display: flex; align-items: center; gap: 10px; }
            .doc-badge { background: linear-gradient(135deg, #059669, #10b981); color: #fff; font-size: 12px; font-weight: 600; padding: 4px 12px; border-radius: 9999px; box-shadow: 0 2px 4px rgba(5,150,105,0.3); }
            .doc-tabs { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; }
            .doc-tab-item { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; text-decoration: none; font-weight: 600; font-size: 14px; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
            .doc-tab-item:hover { color: #059669; }
            .doc-tab-item.active { color: #059669; border-bottom-color: #059669; }
            .doc-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.04); padding: 26px; margin-bottom: 24px; }
            .doc-form-group { margin-bottom: 20px; }
            .doc-form-group label { display: block; font-weight: 600; color: #1e293b; margin-bottom: 6px; font-size: 13.5px; }
            .doc-form-group input[type="text"], .doc-form-group input[type="password"], .doc-form-group textarea, .doc-form-group select { width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 9px 13px; font-size: 14px; box-sizing: border-box; }
            .doc-form-group input:focus, .doc-form-group textarea:focus, .doc-form-group select:focus { border-color: #059669; outline: none; box-shadow: 0 0 0 3px rgba(5,150,105,0.18); }
            .doc-hint { font-size: 12.5px; color: #64748b; margin-top: 5px; line-height: 1.45; }
            .doc-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 20px; font-weight: 600; font-size: 13.5px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; transition: all 0.15s ease; }
            .doc-btn-primary { background: linear-gradient(135deg, #059669, #10b981); color: #fff; box-shadow: 0 2px 6px rgba(5,150,105,0.3); }
            .doc-btn-primary:hover { background: linear-gradient(135deg, #047857, #059669); color: #fff; transform: translateY(-1px); }
            .doc-btn-secondary { background: #f8fafc; color: #334155; border: 1px solid #cbd5e1; }
            .doc-btn-secondary:hover { background: #f1f5f9; color: #1e293b; }
            .doc-btn-danger { background: #ef4444; color: #fff; }
            .doc-btn-danger:hover { background: #dc2626; color: #fff; }
            .doc-notice { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 18px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-size: 14px; font-weight: 500; }
            .doc-table { width: 100%; border-collapse: collapse; text-align: left; }
            .doc-table th { background: #f8fafc; color: #475569; font-size: 12px; font-weight: 600; text-transform: uppercase; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; }
            .doc-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; vertical-align: top; }
            .doc-source-badge { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 9999px; }
            .doc-source-badge.doc { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
            .doc-source-badge.ai { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
        </style>

        <div class="wrap doc-wrap">
            <div class="doc-header">
                <h1>
                    <span class="dashicons dashicons-book" style="font-size: 30px; width: 30px; height: 30px; color: #059669;"></span>
                    Chatbot AI Tra Cứu Tài Liệu (Document Grounded)
                </h1>
                <span class="doc-badge">v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="doc-notice">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php
                    switch ($_GET['msg']) {
                        case 'doc_saved': echo 'Đã lưu tài liệu doanh nghiệp thành công! AI đã được cập nhật kiến thức mới.'; break;
                        case 'sample_loaded': echo 'Đã nạp lại tài liệu mẫu thành công!'; break;
                        case 'settings_saved': echo 'Đã cập nhật cài đặt API và Widget thành công!'; break;
                        case 'leads_cleared': echo 'Đã làm trống danh sách yêu cầu hỗ trợ!'; break;
                        case 'logs_cleared': echo 'Đã làm trống toàn bộ nhật ký hội thoại!'; break;
                        default: echo 'Thao tác thành công!';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="doc-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-doc-ai&tab=document')); ?>" class="doc-tab-item <?php echo $tab === 'document' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-media-document"></span> 1. Tài Liệu Doanh Nghiệp (Document)
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-doc-ai&tab=settings')); ?>" class="doc-tab-item <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> 2. Cài Đặt DeepSeek API & Widget
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-doc-ai&tab=sandbox')); ?>" class="doc-tab-item <?php echo $tab === 'sandbox' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-format-chat"></span> 3. Thử Nghiệm Trực Tiếp (Sandbox)
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-doc-ai&tab=leads')); ?>" class="doc-tab-item <?php echo $tab === 'leads' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-phone"></span> 4. Khách Cần Hỗ Trợ (<?php echo count($leads); ?>)
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-doc-ai&tab=logs')); ?>" class="doc-tab-item <?php echo $tab === 'logs' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-list-view"></span> 5. Nhật Ký Hội Thoại (<?php echo count($logs); ?>)
                </a>
            </div>

            <?php if ($tab === 'document'): ?>
                <div style="max-width: 920px;">
                    <div class="doc-card" style="border-left: 4px solid #059669;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <div>
                                <h2 style="margin: 0; font-size: 17px; font-weight: 700; color: #064e3b;">Tài Liệu Thông Tin & Kiến Thức Cửa Hàng</h2>
                                <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                                    Toàn bộ dữ liệu của bạn chỉ cần quản lý trong <strong>1 tài liệu duy nhất</strong> này. AI sẽ tự đọc hiểu và trả lời khách hàng theo văn phong tự nhiên.
                                </p>
                            </div>
                            <form method="post" action="" onsubmit="return confirm('Bạn có chắc muốn nạp lại tài liệu mẫu mặc định không? Nội dung hiện tại sẽ bị thay thế.');">
                                <?php wp_nonce_field('doc_ai_sample_doc_nonce'); ?>
                                <input type="hidden" name="doc_ai_action" value="load_sample_document">
                                <button type="submit" class="doc-btn doc-btn-secondary" style="font-size: 12px; padding: 6px 12px;">
                                    🔄 Nạp Tài Liệu Mẫu
                                </button>
                            </form>
                        </div>

                        <form method="post" action="">
                            <?php wp_nonce_field('doc_ai_save_doc_nonce'); ?>
                            <input type="hidden" name="doc_ai_action" value="save_document">

                            <div class="doc-form-group">
                                <textarea name="document_content" id="doc-content-field" rows="18" style="font-family: Consolas, Monaco, monospace; line-height: 1.6; font-size: 13.5px;" required><?php echo esc_textarea($document); ?></textarea>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
                                    <span class="doc-hint">💡 <em>Mẹo: Bạn có thể viết dưới dạng gạch đầu dòng, danh sách câu hỏi, chính sách giá, hotline, giờ mở cửa... AI đều hiểu được trọn vẹn.</em></span>
                                    <span style="font-size: 12px; color: #64748b;" id="doc-char-count"><?php echo mb_strlen($document); ?> ký tự</span>
                                </div>
                            </div>

                            <div style="margin-top: 20px;">
                                <button type="submit" class="doc-btn doc-btn-primary" style="padding: 12px 32px; font-size: 15px;">
                                    <span class="dashicons dashicons-saved"></span> Lưu Tài Liệu Doanh Nghiệp
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($tab === 'settings'): ?>
                <div style="max-width: 820px;">
                    <form method="post" action="">
                        <?php wp_nonce_field('doc_ai_save_settings_nonce'); ?>
                        <input type="hidden" name="doc_ai_action" value="save_settings">

                        <!-- Cấu hình DeepSeek API -->
                        <div class="doc-card" style="border-left: 4px solid #10b981;">
                            <h2 style="margin: 0 0 16px; font-size: 17px; font-weight: 700; color: #064e3b; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-admin-network" style="color: #059669;"></span>
                                Cấu Hình DeepSeek API
                            </h2>

                            <div class="doc-form-group">
                                <label>DeepSeek API Key <span style="color: #ef4444;">*</span></label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="password" id="doc-api-key" name="api_key" value="<?php echo esc_attr($settings['api_key']); ?>" placeholder="sk-..." required>
                                    <button type="button" class="doc-btn doc-btn-secondary" id="doc-toggle-key" title="Ẩn/Hiện">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                    <button type="button" class="doc-btn doc-btn-secondary" id="doc-test-api" style="white-space: nowrap;">
                                        ⚡ Kiểm Tra Kết Nối
                                    </button>
                                </div>
                                <div id="doc-test-res" style="margin-top: 10px; font-size: 13px; display: none; padding: 10px 14px; border-radius: 6px;"></div>
                                <div class="doc-hint">
                                    Quản lý khóa và nạp số dư tại: <a href="https://platform.deepseek.com/api_keys" target="_blank" style="color: #059669; font-weight: 600;">DeepSeek Platform API Keys</a>.
                                </div>
                            </div>

                            <div class="doc-form-group">
                                <label>Mô hình DeepSeek (Model)</label>
                                <select name="model" id="doc-model">
                                    <option value="deepseek-flash" <?php selected($settings['model'], 'deepseek-flash'); ?>>deepseek-flash (Khuyên dùng: Tốc độ cao, tối ưu chi phí, kiến trúc V4.1)</option>
                                    <option value="deepseek-v4-pro" <?php selected($settings['model'], 'deepseek-v4-pro'); ?>>deepseek-v4-pro (Mô hình hiệu năng cao chuyên sâu)</option>
                                </select>
                                <div class="doc-hint">Chỉ sử dụng các dòng mô hình DeepSeek thế hệ mới nhất theo đúng yêu cầu cấu hình.</div>
                            </div>

                            <div class="doc-form-group">
                                <label>Số lượt hội thoại AI ghi nhớ ngữ cảnh (Multi-turn Memory)</label>
                                <input type="number" name="max_history_turns" min="2" max="20" value="<?php echo esc_attr($settings['max_history_turns'] ?? 8); ?>" style="width: 120px;"> lượt trao đổi gần nhất
                                <div class="doc-hint">Giúp Chatbot AI ghi nhớ ngữ cảnh các câu hỏi liên tiếp của khách hàng.</div>
                            </div>

                            <div class="doc-form-group" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
                                <label style="font-weight: 700; color: #064e3b;">Câu phản hồi khi thông tin KHÔNG CÓ trong tài liệu (Fallback) <span style="color: #ef4444;">*</span></label>
                                <input type="text" name="fallback_msg" value="<?php echo esc_attr($settings['fallback_msg']); ?>" required style="font-weight: 600; color: #059669; background: #fff;">
                                <div class="doc-hint">Mặc định chuẩn xác: <code>Nhân viên sẽ liên lạc với bạn sớm</code>. AI bị ràng buộc tuyệt đối không bịa thông tin khi tài liệu không đề cập.</div>
                            </div>

                            <div class="doc-form-group">
                                <label>
                                    <input type="checkbox" name="enable_lead_capture" value="1" <?php checked($settings['enable_lead_capture'], '1'); ?>>
                                    Hiển thị form nhập Số Điện Thoại khi bot thông báo câu fallback
                                </label>
                            </div>
                        </div>

                        <!-- Cấu hình Widget -->
                        <div class="doc-card" style="border-left: 4px solid #059669;">
                            <h2 style="margin: 0 0 16px; font-size: 17px; font-weight: 700; color: #064e3b; display: flex; align-items: center; gap: 8px;">
                                <span class="dashicons dashicons-art" style="color: #059669;"></span>
                                Cấu Hình Widget Khách Hàng
                            </h2>

                            <div class="doc-form-group">
                                <label>
                                    <input type="checkbox" name="enable_widget" value="1" <?php checked($settings['enable_widget'], '1'); ?>>
                                    <strong>Kích hoạt hiển thị Widget trên trang web</strong>
                                </label>
                            </div>

                            <div class="doc-form-group">
                                <label>Tên hiển thị Trợ lý</label>
                                <input type="text" name="bot_name" value="<?php echo esc_attr($settings['bot_name']); ?>" required>
                            </div>

                            <div class="doc-form-group">
                                <label>Lời chào mở đầu</label>
                                <textarea name="welcome_msg" rows="3" required><?php echo esc_textarea($settings['welcome_msg']); ?></textarea>
                            </div>

                            <div class="doc-form-group">
                                <label>Gợi ý câu hỏi nhanh cho khách hàng (Mỗi câu trên 1 dòng)</label>
                                <textarea name="suggestions" rows="4" placeholder="Shop mở cửa đến mấy giờ? ⏰..."><?php echo esc_textarea($settings['suggestions'] ?? ''); ?></textarea>
                                <div class="doc-hint">Các nút gợi ý câu hỏi sẽ hiển thị ngay dưới lời chào của bot để khách hàng có thể bấm hỏi nhanh bằng 1 chạm.</div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="doc-form-group">
                                    <label>Màu sắc chủ đạo</label>
                                    <input type="color" name="primary_color" value="<?php echo esc_attr($settings['primary_color']); ?>" style="height: 40px; padding: 2px; cursor: pointer;">
                                </div>
                                <div class="doc-form-group">
                                    <label>Vị trí hiển thị nút chat</label>
                                    <select name="bot_position">
                                        <option value="left" <?php selected($settings['bot_position'], 'left'); ?>>Góc Dưới Bên Trái (Khuyên dùng để tránh đè)</option>
                                        <option value="right" <?php selected($settings['bot_position'], 'right'); ?>>Góc Dưới Bên Phải</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="doc-btn doc-btn-primary" style="padding: 12px 30px; font-size: 15px;">
                                <span class="dashicons dashicons-saved"></span> Lưu Cài Đặt
                            </button>
                        </div>
                    </form>
                </div>

                <script>
                (function() {
                    var key = document.getElementById('doc-api-key');
                    var toggle = document.getElementById('doc-toggle-key');
                    var test = document.getElementById('doc-test-api');
                    var res = document.getElementById('doc-test-res');
                    var model = document.getElementById('doc-model');

                    toggle.addEventListener('click', function() {
                        key.type = key.type === 'password' ? 'text' : 'password';
                    });

                    test.addEventListener('click', function() {
                        var k = key.value.trim();
                        if (!k) { alert('Vui lòng nhập API Key'); return; }
                        test.disabled = true;
                        test.innerText = 'Đang kiểm tra...';
                        res.style.display = 'none';

                        var fd = new FormData();
                        fd.append('action', 'doc_ai_test_api');
                        fd.append('nonce', '<?php echo wp_create_nonce("doc_ai_test_api_nonce"); ?>');
                        fd.append('api_key', k);
                        fd.append('model', model.value);

                        fetch(ajaxurl, { method: 'POST', body: fd })
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            test.disabled = false;
                            test.innerText = '⚡ Kiểm Tra Kết Nối';
                            res.style.display = 'block';
                            if (d.success) {
                                res.style.background = '#ecfdf5';
                                res.style.color = '#065f46';
                                res.innerHTML = '<strong>✓ ' + d.data.message + '</strong><br><span style="font-size:12px; font-style:italic;">AI đọc tài liệu và trả lời: "' + d.data.response + '"</span>';
                            } else {
                                res.style.background = '#fef2f2';
                                res.style.color = '#b91c1c';
                                res.innerHTML = '<strong>✕ Lỗi:</strong> ' + (d.data.message || 'Không thể kết nối.');
                            }
                        })
                        .catch(function() {
                            test.disabled = false;
                            test.innerText = '⚡ Kiểm Tra Kết Nối';
                            res.style.display = 'block';
                            res.style.background = '#fef2f2';
                            res.style.color = '#b91c1c';
                            res.innerHTML = 'Lỗi kết nối máy chủ.';
                        });
                    });
                })();
                </script>

            <?php elseif ($tab === 'sandbox'): ?>
                <div style="max-width: 820px;">
                    <div class="doc-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                            <div>
                                <h2 style="margin: 0; font-size: 17px; font-weight: 700; color: #064e3b;">Thử Nghiệm Trò Chuyện Trực Tiếp</h2>
                                <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">Kiểm tra xem AI đọc hiểu tài liệu hiện tại và trả lời tự nhiên ra sao.</p>
                            </div>
                            <button type="button" class="doc-btn doc-btn-secondary" id="doc-clear-chat" style="font-size: 12px; padding: 6px 12px;">
                                🔄 Xóa Ngữ Cảnh & Chat Lại
                            </button>
                        </div>

                        <?php
                        $sug_list = array_filter(array_map('trim', explode("\n", $settings['suggestions'] ?? '')));
                        ?>

                        <div id="doc-sandbox-box" style="height: 400px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; margin-bottom: 16px;">
                            <div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 12px; max-width: 80%; align-self: flex-start; font-size: 13.5px; line-height: 1.5; color: #1e293b;">
                                <?php echo nl2br(esc_html($settings['welcome_msg'])); ?>
                            </div>

                            <?php if (!empty($sug_list)): ?>
                                <div id="doc-sandbox-suggestions" style="display: flex; flex-direction: column; gap: 6px;">
                                    <span style="font-size: 11px; font-weight: 700; color: #059669;">💡 Gợi ý câu hỏi nhanh:</span>
                                    <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                        <?php foreach ($sug_list as $sug): ?>
                                            <button type="button" class="doc-sandbox-chip" data-query="<?php echo esc_attr($sug); ?>" style="background: #ffffff; color: #047857; border: 1px solid #a7f3d0; border-radius: 999px; padding: 5px 12px; font-size: 12px; cursor: pointer; text-align: left; transition: all 0.2s;">
                                                <?php echo esc_html($sug); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form id="doc-sandbox-form" style="display: flex; gap: 8px;">
                            <input type="text" id="doc-sandbox-input" placeholder="Hỏi bất kỳ điều gì có trong tài liệu (hoặc hỏi câu ngoài tài liệu)..." style="flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 14px; outline: none;">
                            <button type="submit" id="doc-sandbox-send" class="doc-btn doc-btn-primary" style="padding: 10px 20px;">
                                Gửi Tin
                            </button>
                        </form>
                    </div>
                </div>

                <style>
                    @keyframes docCursorBlink { 0%, 100% { opacity: 1; } 50% { opacity: 0; } }
                    .doc-sandbox-chip:hover { background: #ecfdf5 !important; border-color: #059669 !important; transform: translateY(-1px); }
                </style>

                <script>
                (function() {
                    var box = document.getElementById('doc-sandbox-box');
                    var form = document.getElementById('doc-sandbox-form');
                    var input = document.getElementById('doc-sandbox-input');
                    var send = document.getElementById('doc-sandbox-send');
                    var clear = document.getElementById('doc-clear-chat');
                    var maxHistory = <?php echo absint($settings['max_history_turns'] ?? 8); ?>;
                    var history = [];

                    function append(text, isUser) {
                        var d = document.createElement('div');
                        d.style.padding = '10px 14px';
                        d.style.borderRadius = '12px';
                        d.style.maxWidth = '80%';
                        d.style.fontSize = '13.5px';
                        d.style.lineHeight = '1.5';
                        if (isUser) {
                            d.style.background = '#059669';
                            d.style.color = '#fff';
                            d.style.alignSelf = 'flex-end';
                        } else {
                            d.style.background = '#fff';
                            d.style.color = '#1e293b';
                            d.style.border = '1px solid #e2e8f0';
                            d.style.alignSelf = 'flex-start';
                        }
                        d.innerHTML = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                        box.appendChild(d);
                        box.scrollTop = box.scrollHeight;
                    }

                    function getInitialSandboxHtml() {
                        var html = '<div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 10px 14px; border-radius: 12px; max-width: 80%; align-self: flex-start; font-size: 13.5px; line-height: 1.5; color: #1e293b;"><?php echo esc_js($settings["welcome_msg"]); ?></div>';
                        <?php if (!empty($sug_list)): ?>
                        html += '<div id="doc-sandbox-suggestions" style="display: flex; flex-direction: column; gap: 6px;"><span style="font-size: 11px; font-weight: 700; color: #059669;">💡 Gợi ý câu hỏi nhanh:</span><div style="display: flex; flex-wrap: wrap; gap: 6px;">';
                        <?php foreach ($sug_list as $sug): ?>
                        html += '<button type="button" class="doc-sandbox-chip" data-query="<?php echo esc_js($sug); ?>" style="background: #ffffff; color: #047857; border: 1px solid #a7f3d0; border-radius: 999px; padding: 5px 12px; font-size: 12px; cursor: pointer; text-align: left; transition: all 0.2s;"><?php echo esc_js($sug); ?></button>';
                        <?php endforeach; ?>
                        html += '</div></div>';
                        <?php endif; ?>
                        return html;
                    }

                    clear.addEventListener('click', function() {
                        history = [];
                        box.innerHTML = getInitialSandboxHtml();
                    });

                    box.addEventListener('click', function(e) {
                        var chip = e.target.closest('.doc-sandbox-chip');
                        if (!chip || input.disabled) return;
                        var q = chip.getAttribute('data-query');
                        if (q) {
                            input.value = q;
                            form.dispatchEvent(new Event('submit', { cancelable: true }));
                            var sug = document.getElementById('doc-sandbox-suggestions');
                            if (sug) sug.style.display = 'none';
                        }
                    });

                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        var q = input.value.trim();
                        if (!q) return;

                        var sug = document.getElementById('doc-sandbox-suggestions');
                        if (sug) sug.style.display = 'none';

                        append(q, true);
                        input.value = '';
                        input.disabled = true;
                        send.disabled = true;

                        var thinking = document.createElement('div');
                        thinking.style.padding = '10px 14px';
                        thinking.style.borderRadius = '12px';
                        thinking.style.background = '#fff';
                        thinking.style.border = '1px solid #e2e8f0';
                        thinking.style.alignSelf = 'flex-start';
                        thinking.style.fontSize = '12.5px';
                        thinking.style.color = '#059669';
                        thinking.innerText = '📖 AI đang đọc tài liệu và suy luận...';
                        box.appendChild(thinking);
                        box.scrollTop = box.scrollHeight;

                        var fd = new FormData();
                        fd.append('action', 'doc_ai_stream_message');
                        fd.append('nonce', '<?php echo wp_create_nonce("doc_ai_widget_nonce"); ?>');
                        fd.append('message', q);
                        fd.append('history', JSON.stringify(history));

                        var rawAccumulated = '';
                        var targetCleanText = '';
                        var displayedText = '';
                        var typingTimer = null;
                        var streamDone = false;
                        var finalFullText = '';
                        var botDiv = null;
                        var badgeDiv = null;
                        var contentDiv = null;

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
                                botDiv.style.background = '#fff';
                                botDiv.style.color = '#1e293b';
                                botDiv.style.border = '1px solid #e2e8f0';
                                botDiv.style.alignSelf = 'flex-start';

                                badgeDiv = document.createElement('div');
                                botDiv.appendChild(badgeDiv);

                                contentDiv = document.createElement('div');
                                botDiv.appendChild(contentDiv);

                                box.appendChild(botDiv);
                                box.scrollTop = box.scrollHeight;
                            }
                        }

                        function updateBadge(source) {
                            ensureBox();
                            if (!badgeDiv) return;
                            if (source === 'ai') {
                                badgeDiv.innerHTML = '<span class="doc-source-badge ai" style="margin-bottom:6px;">✨ Câu trả lời từ AI</span>';
                            } else {
                                badgeDiv.innerHTML = '<span class="doc-source-badge doc" style="margin-bottom:6px;">📖 Câu trả lời từ tài liệu có sẵn</span>';
                            }
                        }

                        function startTypewriter() {
                            if (typingTimer) return;
                            typingTimer = setInterval(function() {
                                if (displayedText.length < targetCleanText.length) {
                                    ensureBox();
                                    var backlog = targetCleanText.length - displayedText.length;
                                    var step = backlog > 120 ? 4 : (backlog > 60 ? 2 : 1);
                                    displayedText = targetCleanText.slice(0, displayedText.length + step);
                                    contentDiv.innerHTML = displayedText.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '<span style="display:inline-block;width:2px;height:13px;background:#059669;margin-left:2px;vertical-align:middle;animation:docCursorBlink 0.7s infinite;"></span>';
                                    box.scrollTop = box.scrollHeight;
                                } else if (streamDone) {
                                    clearInterval(typingTimer);
                                    typingTimer = null;
                                    var cleanFinal = finalFullText || targetCleanText;
                                    if (contentDiv) {
                                        contentDiv.innerHTML = cleanFinal.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                                    }
                                    if (thinking && thinking.parentNode) thinking.remove();
                                    input.disabled = false;
                                    send.disabled = false;
                                    input.focus();

                                    if (cleanFinal) {
                                        history.push({ role: 'user', text: q });
                                        history.push({ role: 'model', text: cleanFinal });
                                        if (history.length > maxHistory * 2) {
                                            history = history.slice(-maxHistory * 2);
                                        }
                                    }

                                    // Gợi ý câu hỏi tiếp theo
                                    if (botDiv && !botDiv.querySelector('.doc-sandbox-followup')) {
                                        var fBox = document.createElement('div');
                                        fBox.className = 'doc-sandbox-followup';
                                        fBox.style.cssText = 'margin-top: 10px; width: 100%; border-top: 1px dashed #cbd5e1; padding-top: 8px;';
                                        fBox.innerHTML = '<span style="font-size: 11px; font-weight: 700; color: #059669; display:block; margin-bottom: 6px;">💡 Gợi ý câu hỏi tiếp theo:</span>';
                                        var fChips = document.createElement('div');
                                        fChips.style.cssText = 'display: flex; flex-wrap: wrap; gap: 6px;';
                                        var followUps = [
                                            'Bảng chọn size chi tiết cho nam và nữ 📏',
                                            'Chính sách đổi trả trong vòng 7 ngày thế nào? 🔄',
                                            'Đơn hàng bao nhiêu thì được miễn phí giao hàng? 🚚',
                                            'Cửa hàng mở cửa từ mấy giờ và ở đâu? ⏰',
                                            'Cách giặt và bảo quản áo lụa / linen bền đẹp 🧺',
                                            'Gợi ý set đồ công sở thanh lịch tôn dáng ✨'
                                        ];
                                        var count = 0;
                                        followUps.forEach(function(sug) {
                                            if (count < 3 && q.indexOf(sug.slice(0, 6)) === -1) {
                                                var btn = document.createElement('button');
                                                btn.type = 'button';
                                                btn.className = 'doc-sandbox-chip';
                                                btn.setAttribute('data-query', sug);
                                                btn.style.cssText = 'background: #ffffff; color: #047857; border: 1px solid #a7f3d0; border-radius: 999px; padding: 4px 10px; font-size: 11.5px; cursor: pointer; text-align: left; transition: all 0.2s;';
                                                btn.innerText = '👉 ' + sug;
                                                fChips.appendChild(btn);
                                                count++;
                                            }
                                        });
                                        fBox.appendChild(fChips);
                                        botDiv.appendChild(fBox);
                                    }

                                    box.scrollTop = box.scrollHeight;
                                }
                            }, 22);
                        }

                        fetch(ajaxurl, { method: 'POST', body: fd })
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
                                                    rawAccumulated += d.text;
                                                    var currentSource = (rawAccumulated.indexOf('[NGUON: AI]') !== -1) ? 'ai' : 'doc';
                                                    updateBadge(currentSource);
                                                    targetCleanText = rawAccumulated.replace(/^\[[^\]]*\]?\s*/i, '').replace(/\[NGUON:\s*(TAI_LIEU|AI)\]/gi, '').trimStart();
                                                    startTypewriter();
                                                }
                                                if (d.done) {
                                                    if (d.source) updateBadge(d.source);
                                                    if (d.full_text) {
                                                        finalFullText = d.full_text;
                                                        targetCleanText = d.full_text;
                                                    }
                                                }
                                                if (d.error) {
                                                    if (typingTimer) {
                                                        clearInterval(typingTimer);
                                                        typingTimer = null;
                                                    }
                                                    ensureBox();
                                                    contentDiv.innerHTML = '<span style="color:#ef4444;">⚠️ ' + d.error + '</span>';
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
                            send.disabled = false;
                            input.focus();
                            append('Lỗi kết nối máy chủ.', false);
                        });
                    });
                })();
                </script>

            <?php elseif ($tab === 'leads'): ?>
                <div class="doc-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <div>
                            <h2 style="margin: 0; font-size: 17px; font-weight: 700; color: #064e3b;">Danh Sách Khách Hàng Cần Hỗ Trợ Trực Tiếp</h2>
                            <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                                Ghi nhận khi khách hỏi nội dung chưa có trong tài liệu và gửi lại số điện thoại.
                            </p>
                        </div>
                        <?php if (!empty($leads)): ?>
                            <form method="post" action="" onsubmit="return confirm('Bạn có chắc muốn xóa tất cả danh sách?');">
                                <?php wp_nonce_field('doc_ai_clear_leads_nonce'); ?>
                                <input type="hidden" name="doc_ai_action" value="clear_leads">
                                <button type="submit" class="doc-btn doc-btn-danger" style="font-size: 12px; padding: 6px 12px;">
                                    Xóa Toàn Bộ
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($leads)): ?>
                        <div style="text-align: center; padding: 40px; color: #94a3b8;">
                            <span class="dashicons dashicons-phone" style="font-size: 40px; width: 40px; height: 40px; margin-bottom: 8px;"></span>
                            <p style="font-size: 15px; font-weight: 600; margin: 0 0 4px; color: #475569;">Chưa có yêu cầu hỗ trợ nào</p>
                            <p style="font-size: 13px; margin: 0;">Khi khách gửi số điện thoại từ widget, thông tin sẽ được lưu đầy đủ ở đây.</p>
                        </div>
                    <?php else: ?>
                        <table class="doc-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Thời Gian</th>
                                    <th style="width: 25%;">Số Điện Thoại</th>
                                    <th style="width: 60%;">Nội Dung Khách Đã Hỏi (Ngoài Tài Liệu)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($leads) as $lead): ?>
                                    <tr>
                                        <td style="color: #64748b; font-size: 12px;"><?php echo esc_html($lead['time'] ?? ''); ?></td>
                                        <td>
                                            <a href="tel:<?php echo esc_attr($lead['contact'] ?? ''); ?>" style="color: #059669; font-weight: 700; text-decoration: none;">
                                                📞 <?php echo esc_html($lead['contact'] ?? ''); ?>
                                            </a>
                                        </td>
                                        <td style="color: #334155; line-height: 1.45;">"<?php echo esc_html($lead['question'] ?? ''); ?>"</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

            <?php elseif ($tab === 'logs'): ?>
                <div class="doc-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <div>
                            <h2 style="margin: 0; font-size: 17px; font-weight: 700; color: #064e3b;">Nhật Ký Hội Thoại (Document & AI)</h2>
                            <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                                Theo dõi câu hỏi của khách hàng, phân loại nguồn câu trả lời (Tài liệu hoặc AI) và phản hồi thực tế trong 50 lượt gần nhất.
                            </p>
                        </div>
                        <?php if (!empty($logs)): ?>
                            <form method="post" action="" onsubmit="return confirm('Bạn có chắc muốn làm trống toàn bộ nhật ký này?');">
                                <?php wp_nonce_field('doc_ai_clear_logs_nonce'); ?>
                                <input type="hidden" name="doc_ai_action" value="clear_logs">
                                <button type="submit" class="doc-btn doc-btn-danger" style="font-size: 12px; padding: 6px 14px;">
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
                        <table class="doc-table">
                            <thead>
                                <tr>
                                    <th style="width: 14%;">Thời Gian</th>
                                    <th style="width: 30%;">Câu Hỏi Của Khách</th>
                                    <th style="width: 20%;">Nguồn Trả Lời</th>
                                    <th style="width: 36%;">Câu Trả Lời Của Chatbot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($logs) as $log): 
                                    $is_ai = (isset($log['source']) && $log['source'] === 'ai');
                                ?>
                                    <tr>
                                        <td style="color: #64748b; font-size: 12px; white-space: nowrap;">
                                            <?php echo esc_html($log['time'] ?? ''); ?>
                                        </td>
                                        <td style="font-weight: 600; color: #1e293b;">
                                            <?php echo esc_html($log['user'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <?php if ($is_ai): ?>
                                                <span class="doc-source-badge ai">✨ Trí tuệ AI</span>
                                            <?php else: ?>
                                                <span class="doc-source-badge doc">📖 Tài liệu có sẵn</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #334155; line-height: 1.5; font-size: 13px;">
                                            <?php echo nl2br(esc_html($log['ai'] ?? '')); ?>
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
     * Render Giao diện Widget Frontend
     */
    public function render_frontend_widget() {
        $settings = self::get_settings();
        if ($settings['enable_widget'] !== '1') {
            return;
        }

        $primary = esc_attr($settings['primary_color']);
        $position = $settings['bot_position'] === 'right' ? 'right' : 'left';
        $offset_bottom = absint($settings['offset_bottom']);
        $nonce = wp_create_nonce('doc_ai_widget_nonce');
        $ajax_url = admin_url('admin-ajax.php');
        ?>
        <!-- CHATBOT DOCUMENT AI WIDGET -->
        <style>
            :root {
                --doc-primary: <?php echo $primary; ?>;
                --doc-glow: rgba(5, 150, 105, 0.35);
            }

            #cb-doc-widget {
                position: fixed;
                bottom: <?php echo $offset_bottom; ?>px;
                <?php echo $position; ?>: 24px;
                z-index: 999997;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-size: 14px;
                line-height: 1.5;
            }

            #cb-doc-launcher {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--doc-primary), #10b981);
                color: #ffffff;
                box-shadow: 0 6px 18px var(--doc-glow);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
                border: none;
                outline: none;
                position: relative;
            }

            #cb-doc-launcher:hover {
                transform: scale(1.1);
                box-shadow: 0 8px 24px var(--doc-glow);
            }

            .cb-doc-badge-doc {
                position: absolute;
                top: -2px;
                right: -2px;
                background: #f59e0b;
                color: #ffffff;
                font-size: 10px;
                font-weight: 800;
                padding: 2px 5px;
                border-radius: 999px;
                border: 2px solid #ffffff;
            }

            #cb-doc-box {
                position: fixed;
                bottom: 24px;
                right: 96px;
                width: 375px;
                max-width: calc(100vw - 110px);
                height: 540px;
                max-height: calc(100vh - 48px);
                background: #ffffff;
                border-radius: 18px;
                box-shadow: 0 16px 36px rgba(0,0,0,0.12), 0 0 0 1px rgba(5,150,105,0.1);
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
                #cb-doc-box {
                    right: 12px !important;
                    left: 12px !important;
                    bottom: 12px !important;
                    width: auto !important;
                    max-width: none !important;
                    height: calc(100vh - 24px) !important;
                }
            }

            #cb-doc-widget.active #cb-doc-box {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }

            .cb-doc-header {
                background: linear-gradient(135deg, var(--doc-primary), #10b981);
                color: #ffffff;
                padding: 14px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }

            .cb-doc-header-info {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .cb-doc-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.22);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
            }

            .cb-doc-title { font-weight: 700; font-size: 14.5px; }
            .cb-doc-sub { font-size: 11px; opacity: 0.9; }

            .cb-doc-header-actions button {
                background: none;
                border: none;
                color: rgba(255,255,255,0.85);
                cursor: pointer;
                padding: 6px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .cb-doc-messages {
                flex: 1;
                padding: 16px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 12px;
                background: #f8fafc;
                scroll-behavior: smooth;
            }

            .cb-doc-msg {
                display: flex;
                flex-direction: column;
                max-width: 84%;
                animation: cbDocFade 0.25s ease-out;
            }

            @keyframes cbDocFade {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .cb-doc-msg.bot { align-self: flex-start; }
            .cb-doc-msg.user { align-self: flex-end; }

            .cb-doc-bubble {
                padding: 11px 15px;
                border-radius: 16px;
                font-size: 13.5px;
                line-height: 1.5;
                word-break: break-word;
            }

            .cb-doc-msg.bot .cb-doc-bubble {
                background: #ffffff;
                color: #1e293b;
                border: 1px solid #e2e8f0;
                border-bottom-left-radius: 4px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            }

            .cb-doc-msg.user .cb-doc-bubble {
                background: linear-gradient(135deg, var(--doc-primary), #10b981);
                color: #ffffff;
                border-bottom-right-radius: 4px;
                box-shadow: 0 2px 8px var(--doc-glow);
            }

            .cb-doc-time { font-size: 10px; color: #94a3b8; margin-top: 4px; }
            .cb-doc-msg.user .cb-doc-time { text-align: right; }

            .cb-doc-source-badge {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                font-size: 11px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 999px;
                margin-bottom: 6px;
                line-height: 1.4;
                align-self: flex-start;
                animation: cbDocFade 0.2s ease-out;
            }
            .cb-doc-source-badge.doc {
                background: #ecfdf5;
                color: #047857;
                border: 1px solid #a7f3d0;
            }
            .cb-doc-source-badge.ai {
                background: #f5f3ff;
                color: #6d28d9;
                border: 1px solid #ddd6fe;
            }

            /* Lead capture box */
            .cb-doc-lead-box {
                margin-top: 8px;
                background: #ecfdf5;
                border: 1px solid #a7f3d0;
                border-radius: 10px;
                padding: 10px;
                font-size: 12.5px;
            }

            .cb-doc-lead-box p { margin: 0 0 8px; color: #065f46; font-weight: 600; }

            .cb-doc-lead-wrap { display: flex; gap: 6px; }
            .cb-doc-lead-wrap input {
                flex: 1;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                padding: 6px 10px;
                font-size: 12px;
                background: #ffffff;
            }
            .cb-doc-lead-wrap button {
                background: var(--doc-primary);
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
            }

            /* Typing */
            .cb-doc-typing {
                display: none;
                align-self: flex-start;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                padding: 10px 14px;
                border-radius: 14px;
                font-size: 12px;
                color: #059669;
                align-items: center;
                gap: 6px;
            }
            .cb-doc-typing.show { display: flex; }

            /* Input */
            .cb-doc-input-area {
                padding: 12px;
                background: #ffffff;
                border-top: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .cb-doc-input {
                flex: 1;
                border: 1px solid #cbd5e1;
                border-radius: 20px;
                padding: 9px 15px;
                font-size: 13.5px;
                outline: none;
                font-family: inherit;
            }
            .cb-doc-input:focus {
                border-color: var(--doc-primary);
                box-shadow: 0 0 0 3px rgba(5,150,105,0.15);
            }

            .cb-doc-send {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--doc-primary), #10b981);
                color: #ffffff;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }

            /* Suggestions chips */
            .cb-doc-suggestions {
                display: flex;
                flex-direction: column;
                gap: 6px;
                margin-top: 4px;
                align-self: flex-start;
                width: 100%;
            }
            .cb-doc-suggestions-title {
                font-size: 11px;
                font-weight: 700;
                color: #059669;
                display: flex;
                align-items: center;
                gap: 4px;
            }
            .cb-doc-chips {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            .cb-doc-chip {
                background: #ffffff;
                color: #047857;
                border: 1px solid #a7f3d0;
                border-radius: 999px;
                padding: 5px 12px;
                font-size: 12px;
                cursor: pointer;
                transition: all 0.18s ease;
                text-align: left;
                line-height: 1.35;
                box-shadow: 0 1px 3px rgba(5, 150, 105, 0.08);
            }
            .cb-doc-chip:hover {
                background: #ecfdf5;
                border-color: #059669;
                color: #064e3b;
                transform: translateY(-1px);
                box-shadow: 0 3px 6px rgba(5, 150, 105, 0.18);
            }

            /* Blinking cursor for typewriter */
            .cb-doc-cursor {
                display: inline-block;
                width: 2px;
                height: 13px;
                background: #059669;
                margin-left: 2px;
                vertical-align: middle;
                animation: cbDocCursorBlink 0.7s infinite;
            }
            @keyframes cbDocCursorBlink {
                0%, 100% { opacity: 1; }
                50% { opacity: 0; }
            }
        </style>

        <?php
        $suggestions_raw = $settings['suggestions'] ?? '';
        $suggestions_list = array_filter(array_map('trim', explode("\n", $suggestions_raw)));
        ?>

        <div id="cb-doc-widget">
            <button id="cb-doc-launcher" aria-label="Mở Trợ Lý Tài Liệu AI" title="3. Chatbot Tài Liệu AI (Đọc hiểu 1 document) 📖">
                <span class="cb-doc-badge-doc">DOC</span>
                <span style="font-size: 24px;">📖</span>
            </button>

            <div id="cb-doc-box">
                <div class="cb-doc-header">
                    <div class="cb-doc-header-info">
                        <div class="cb-doc-avatar">📖</div>
                        <div>
                            <div class="cb-doc-title"><?php echo esc_html($settings['bot_name']); ?></div>
                            <div class="cb-doc-sub">Đọc hiểu tài liệu & Trả lời tự nhiên</div>
                        </div>
                    </div>
                    <div class="cb-doc-header-actions" style="display: flex; gap: 4px; align-items: center;">
                        <button id="cb-doc-reset-btn" title="Xóa ngữ cảnh & bắt đầu lại">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                        </button>
                        <button id="cb-doc-close" title="Thu nhỏ">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="cb-doc-messages" id="cb-doc-messages">
                    <div class="cb-doc-msg bot">
                        <div class="cb-doc-bubble"><?php echo nl2br(esc_html($settings['welcome_msg'])); ?></div>
                        <div class="cb-doc-time"><?php echo date_i18n('H:i'); ?></div>
                    </div>

                    <?php if (!empty($suggestions_list)): ?>
                        <div class="cb-doc-suggestions" id="cb-doc-suggestions-box">
                            <div class="cb-doc-suggestions-title">💡 Gợi ý câu hỏi nhanh:</div>
                            <div class="cb-doc-chips">
                                <?php foreach ($suggestions_list as $sug): ?>
                                    <button type="button" class="cb-doc-chip" data-question="<?php echo esc_attr($sug); ?>">
                                        <?php echo esc_html($sug); ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="cb-doc-typing" id="cb-doc-typing">
                        <span>📖 AI đang đọc tài liệu và phản hồi...</span>
                    </div>
                </div>

                <form class="cb-doc-input-area" id="cb-doc-form">
                    <input type="text" class="cb-doc-input" id="cb-doc-input" placeholder="Hỏi thông tin về cửa hàng..." autocomplete="off">
                    <button type="submit" class="cb-doc-send" id="cb-doc-send" aria-label="Gửi">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </form>
            </div>
        </div>

        <script>
        (function() {
            var widget = document.getElementById('cb-doc-widget');
            var launcher = document.getElementById('cb-doc-launcher');
            var closeBtn = document.getElementById('cb-doc-close');
            var resetBtn = document.getElementById('cb-doc-reset-btn');
            var messagesList = document.getElementById('cb-doc-messages');
            var form = document.getElementById('cb-doc-form');
            var input = document.getElementById('cb-doc-input');
            var sendBtn = document.getElementById('cb-doc-send');
            var typing = document.getElementById('cb-doc-typing');

            var ajaxUrl = <?php echo json_encode($ajax_url); ?>;
            var nonce = <?php echo json_encode($nonce); ?>;
            var maxHistory = <?php echo absint($settings['max_history_turns'] ?? 8); ?>;
            var history = [];
            var lastQuery = '';

            // Lưu nội dung ban đầu để phục hồi khi reset
            var initialMessagesHtml = messagesList.innerHTML;

            function scrollToBottom() {
                setTimeout(function() {
                    messagesList.scrollTop = messagesList.scrollHeight;
                }, 40);
            }

            launcher.addEventListener('click', function() {
                var wasActive = widget.classList.contains('active');
                document.querySelectorAll('#cb-widget-container, #cb-ai-widget').forEach(function(el) {
                    el.classList.remove('active');
                });
                if (!wasActive) {
                    widget.classList.add('active');
                    setTimeout(function() { input.focus(); }, 150);
                    scrollToBottom();
                } else {
                    widget.classList.remove('active');
                }
            });

            closeBtn.addEventListener('click', function() {
                widget.classList.remove('active');
            });

            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    if (confirm('Bắt đầu cuộc trò chuyện mới và xóa ngữ cảnh cũ?')) {
                        history = [];
                        messagesList.innerHTML = initialMessagesHtml;
                        typing = document.getElementById('cb-doc-typing');
                        scrollToBottom();
                    }
                });
            }

            // Bấm gợi ý câu hỏi nhanh
            messagesList.addEventListener('click', function(e) {
                var chip = e.target.closest('.cb-doc-chip');
                if (!chip || input.disabled) return;
                var q = chip.getAttribute('data-question') || chip.innerText.trim();
                if (q) {
                    input.value = q;
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                    var sugBox = document.getElementById('cb-doc-suggestions-box');
                    if (sugBox) sugBox.style.display = 'none';
                }
            });

            function getTime() {
                var d = new Date();
                return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2);
            }

            function escapeHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            function appendUserMsg(text) {
                var div = document.createElement('div');
                div.className = 'cb-doc-msg user';
                div.innerHTML = '<div class="cb-doc-bubble">' + escapeHtml(text) + '</div><div class="cb-doc-time">' + getTime() + '</div>';
                messagesList.insertBefore(div, typing);
                scrollToBottom();
            }

            function attachLeadCapture(botDiv, queryText) {
                if (botDiv.querySelector('.cb-doc-lead-box')) return;
                var leadBox = document.createElement('div');
                leadBox.className = 'cb-doc-lead-box';
                leadBox.innerHTML =
                    '<p>📞 Để lại số điện thoại để nhân viên hỗ trợ bạn ngay:</p>' +
                    '<div class="cb-doc-lead-wrap">' +
                    '<input type="text" placeholder="Số điện thoại của bạn..." class="cb-doc-lead-phone">' +
                    '<button type="button" class="cb-doc-lead-submit">Gửi</button>' +
                    '</div>' +
                    '<div class="cb-doc-lead-res" style="margin-top:6px; font-size:11px; display:none;"></div>';

                var timeEl = botDiv.querySelector('.cb-doc-time');
                if (timeEl) {
                    botDiv.insertBefore(leadBox, timeEl);
                } else {
                    botDiv.appendChild(leadBox);
                }

                var lBtn = leadBox.querySelector('.cb-doc-lead-submit');
                var lPhone = leadBox.querySelector('.cb-doc-lead-phone');
                var lRes = leadBox.querySelector('.cb-doc-lead-res');

                lBtn.addEventListener('click', function() {
                    var p = lPhone.value.trim();
                    if (!p) { lPhone.focus(); return; }
                    lBtn.disabled = true;
                    lBtn.innerText = 'Đang gửi...';

                    var fd = new FormData();
                    fd.append('action', 'doc_ai_save_lead');
                    fd.append('nonce', nonce);
                    fd.append('contact', p);
                    fd.append('question', queryText);

                    fetch(ajaxUrl, { method: 'POST', body: fd })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        lRes.style.display = 'block';
                        if (d.success) {
                            lRes.style.color = '#065f46';
                            lRes.innerText = '✓ ' + d.data.message;
                            lPhone.style.display = 'none';
                            lBtn.style.display = 'none';
                        } else {
                            lRes.style.color = '#b91c1c';
                            lRes.innerText = d.data.message || 'Lỗi khi gửi.';
                            lBtn.disabled = false;
                            lBtn.innerText = 'Thử lại';
                        }
                    });
                });

                scrollToBottom();
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var q = input.value.trim();
                if (!q) return;

                lastQuery = q;
                var sugBox = document.getElementById('cb-doc-suggestions-box');
                if (sugBox) sugBox.style.display = 'none';

                appendUserMsg(q);
                input.value = '';
                input.disabled = true;
                sendBtn.disabled = true;

                typing.classList.add('show');
                scrollToBottom();

                var fd = new FormData();
                fd.append('action', 'doc_ai_stream_message');
                fd.append('nonce', nonce);
                fd.append('message', q);
                fd.append('history', JSON.stringify(history));

                var rawAccumulated = '';
                var targetCleanText = '';
                var displayedText = '';
                var typingTimer = null;
                var streamDone = false;
                var finalFullText = '';
                var streamDoneInfo = null;
                var botDiv = null;
                var badgeDiv = null;
                var bubbleDiv = null;
                var timeDiv = null;

                function updateBadge(sourceType) {
                    ensureBotDiv();
                    if (!badgeDiv) return;
                    if (sourceType === 'ai') {
                        badgeDiv.className = 'cb-doc-source-badge ai';
                        badgeDiv.innerHTML = '✨ Câu trả lời từ AI';
                    } else {
                        badgeDiv.className = 'cb-doc-source-badge doc';
                        badgeDiv.innerHTML = '📖 Câu trả lời từ tài liệu có sẵn';
                    }
                }

                function ensureBotDiv() {
                    if (!botDiv) {
                        typing.classList.remove('show');
                        botDiv = document.createElement('div');
                        botDiv.className = 'cb-doc-msg bot';

                        badgeDiv = document.createElement('div');
                        botDiv.appendChild(badgeDiv);

                        bubbleDiv = document.createElement('div');
                        bubbleDiv.className = 'cb-doc-bubble';
                        botDiv.appendChild(bubbleDiv);

                        timeDiv = document.createElement('div');
                        timeDiv.className = 'cb-doc-time';
                        timeDiv.innerText = getTime();
                        botDiv.appendChild(timeDiv);

                        messagesList.insertBefore(botDiv, typing);
                        scrollToBottom();
                    }
                }

                function startTypewriter() {
                    if (typingTimer) return;
                    typingTimer = setInterval(function() {
                        if (displayedText.length < targetCleanText.length) {
                            ensureBotDiv();
                            var backlog = targetCleanText.length - displayedText.length;
                            var step = backlog > 120 ? 4 : (backlog > 60 ? 2 : 1);
                            displayedText = targetCleanText.slice(0, displayedText.length + step);
                            bubbleDiv.innerHTML = escapeHtml(displayedText).replace(/\n/g, '<br>') + '<span class="cb-doc-cursor"></span>';
                            scrollToBottom();
                        } else if (streamDone) {
                            clearInterval(typingTimer);
                            typingTimer = null;
                            var cleanFinal = finalFullText || targetCleanText;
                            if (bubbleDiv) {
                                bubbleDiv.innerHTML = escapeHtml(cleanFinal).replace(/\n/g, '<br>');
                            }
                            typing.classList.remove('show');
                            input.disabled = false;
                            sendBtn.disabled = false;
                            input.focus();

                            if (streamDoneInfo && streamDoneInfo.is_fallback && streamDoneInfo.enable_lead_capture && botDiv) {
                                attachLeadCapture(botDiv, q);
                            }

                            if (cleanFinal) {
                                history.push({ role: 'user', text: q });
                                history.push({ role: 'model', text: cleanFinal });
                                if (history.length > maxHistory * 2) {
                                    history = history.slice(-maxHistory * 2);
                                }
                            }

                            // Gợi ý câu hỏi tiếp theo
                            if (botDiv && !botDiv.querySelector('.cb-doc-followup-box')) {
                                var fBox = document.createElement('div');
                                fBox.className = 'cb-doc-followup-box';
                                fBox.style.cssText = 'margin-top: 8px; width: 100%;';
                                fBox.innerHTML = '<div class="cb-doc-suggestions-title" style="margin-bottom: 4px;">💡 Gợi ý câu hỏi tiếp theo:</div>';
                                var fChips = document.createElement('div');
                                fChips.className = 'cb-doc-chips';
                                var fashionFollowUps = [
                                    'Bảng chọn size chi tiết cho nam và nữ 📏',
                                    'Chính sách đổi trả trong vòng 7 ngày thế nào? 🔄',
                                    'Đơn hàng bao nhiêu thì được miễn phí giao hàng? 🚚',
                                    'Cửa hàng mở cửa từ mấy giờ và ở đâu? ⏰',
                                    'Cách giặt và bảo quản áo lụa / linen bền đẹp 🧺',
                                    'Gợi ý set đồ công sở thanh lịch tôn dáng ✨'
                                ];
                                var count = 0;
                                fashionFollowUps.forEach(function(sug) {
                                    if (count < 3 && q.indexOf(sug.slice(0, 6)) === -1) {
                                        var btn = document.createElement('button');
                                        btn.type = 'button';
                                        btn.className = 'cb-doc-chip';
                                        btn.setAttribute('data-question', sug);
                                        btn.innerText = '👉 ' + sug;
                                        fChips.appendChild(btn);
                                        count++;
                                    }
                                });
                                fBox.appendChild(fChips);
                                var timeEl = botDiv.querySelector('.cb-doc-time');
                                if (timeEl) {
                                    botDiv.insertBefore(fBox, timeEl);
                                } else {
                                    botDiv.appendChild(fBox);
                                }
                            }

                            scrollToBottom();
                        }
                    }, 22);
                }

                fetch(ajaxUrl, { method: 'POST', body: fd })
                .then(function(res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
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
                                            rawAccumulated += data.text;
                                            var detectedSource = (rawAccumulated.indexOf('[NGUON: AI]') !== -1) ? 'ai' : 'doc';
                                            updateBadge(detectedSource);

                                            targetCleanText = rawAccumulated.replace(/^\[[^\]]*\]?\s*/i, '').replace(/\[NGUON:\s*(TAI_LIEU|AI)\]/gi, '').trimStart();
                                            startTypewriter();
                                        }
                                        if (data.done) {
                                            streamDoneInfo = data;
                                            if (data.source) {
                                                updateBadge(data.source);
                                            }
                                            if (data.full_text) {
                                                finalFullText = data.full_text;
                                                targetCleanText = data.full_text;
                                            }
                                        }
                                        if (data.error) {
                                            if (typingTimer) {
                                                clearInterval(typingTimer);
                                                typingTimer = null;
                                            }
                                            ensureBotDiv();
                                            bubbleDiv.innerHTML = '<span style="color:#ef4444;">⚠️ ' + escapeHtml(data.error) + '</span>';
                                            scrollToBottom();
                                        }
                                    } catch (err) {}
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
                    var errDiv = document.createElement('div');
                    errDiv.className = 'cb-doc-msg bot';
                    errDiv.innerHTML = '<div class="cb-doc-bubble" style="color:#ef4444;">⚠️ Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại kết nối.</div><div class="cb-doc-time">' + getTime() + '</div>';
                    messagesList.insertBefore(errDiv, typing);
                    scrollToBottom();
                });
            });
        })();
        </script>
        <?php
    }
}

// Khởi tạo Plugin
function wp_document_ai_chatbot_init() {
    return WP_Document_AI_Chatbot::get_instance();
}
add_action('plugins_loaded', 'wp_document_ai_chatbot_init');
