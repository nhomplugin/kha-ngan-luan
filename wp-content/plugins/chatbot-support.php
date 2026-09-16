<?php
/**
 * Plugin Name: Chatbot Hỗ Trợ Khách Hàng (Customer Support Chatbot)
 * Description: Plugin hỗ trợ khách hàng tự động 24/7 thông qua chatbot. Phía admin cho phép nhập danh sách câu hỏi & câu trả lời linh hoạt. Nếu khách hỏi câu chưa có trong dữ liệu, hệ thống tự động thông báo "Nhân viên sẽ liên lạc với bạn sớm" và lưu lại thông tin liên hệ.
 * Version: 1.0.0
 * Author: Antigravity
 * Text Domain: chatbot-support
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WP_Customer_Support_Chatbot {

    const VERSION = '1.0.0';
    const OPTION_QA = 'wp_chatbot_qa_list';
    const OPTION_SETTINGS = 'wp_chatbot_settings';
    const OPTION_LEADS = 'wp_chatbot_leads';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Activation & Deactivation
        register_activation_hook(__FILE__, [__CLASS__, 'on_activate']);

        // Admin
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_admin_actions']);

        // Frontend Widget
        add_action('wp_footer', [$this, 'render_frontend_widget']);

        // Tự động khởi tạo dữ liệu mẫu nếu chưa có
        if (get_option(self::OPTION_QA) === false || !get_option(self::OPTION_SETTINGS)) {
            self::on_activate();
        }

        // AJAX endpoints
        add_action('wp_ajax_chatbot_send_message', [$this, 'ajax_handle_message']);
        add_action('wp_ajax_nopriv_chatbot_send_message', [$this, 'ajax_handle_message']);

        add_action('wp_ajax_chatbot_save_lead', [$this, 'ajax_save_lead']);
        add_action('wp_ajax_nopriv_chatbot_save_lead', [$this, 'ajax_save_lead']);
    }

    /**
    /**
     * Danh sách Q&A mẫu dành riêng cho Shop Quần Áo / Thời Trang
     */
    public static function get_default_qa() {
        return [
            [
                'id' => 'qa_fashion_1',
                'question' => 'Shop có những dòng quần áo và sản phẩm nào nổi bật?',
                'keywords' => 'sản phẩm, quần áo, shop bán gì, có đồ gì, bộ sưu tập, mẫu mới, áo thun, sơ mi, đầm, váy, quần jean, áo khoác, hoodie',
                'answer' => "Dạ, LTTheme Fashion Store chuyên các dòng thời trang nam nữ cao cấp gồm:\n• Áo thun 100% Cotton Compact 250gsm thoáng mát, form rộng unisex.\n• Áo sơ mi Oxford, sơ mi lụa dạo phố và công sở thanh lịch.\n• Quần Jean, quần tây âu, quần short và quần kaki tôn dáng.\n• Váy đầm nữ thiết kế: đầm hoa nhí, đầm dự tiệc, chân váy chữ A.\n• Áo khoác dù chống nước nhẹ, blazer và hoodie nỉ bông ấm áp ạ!"
            ],
            [
                'id' => 'qa_fashion_2',
                'question' => 'Cách chọn size quần áo theo chiều cao và cân nặng?',
                'keywords' => 'chọn size, bảng size, vừa size nào, bảng số đo, chiều cao, cân nặng, size s, size m, size l, size xl, size xxl, tư vấn size',
                'answer' => "Dạ bảng size chuẩn tại LTTheme Fashion như sau ạ:\n• Size S: 45 - 53kg (Cao 1m50 - 1m60)\n• Size M: 54 - 62kg (Cao 1m60 - 1m68)\n• Size L: 63 - 72kg (Cao 1m68 - 1m75)\n• Size XL: 73 - 82kg (Cao 1m75 - 1m82)\n• Size XXL: Trên 82kg (Cao trên 1m80)\n💡 Mẹo: Nếu bạn thích phong cách mặc rộng thoải mái (Oversize) thì có thể tăng 1 size nhé!"
            ],
            [
                'id' => 'qa_fashion_3',
                'question' => 'Chính sách đổi size và đổi trả hàng như thế nào?',
                'keywords' => 'đổi trả, đổi size, không vừa, chật quá, rộng quá, trả hàng, lỗi đường may, bảo hành, hoàn tiền',
                'answer' => "Dạ shop hỗ trợ đổi hàng/đổi size MIỄN PHÍ trong vòng 7 ngày kể từ khi nhận hàng ạ!\n• Điều kiện: Quần áo còn nguyên tem mác, chưa qua giặt ủi và không dính vết bẩn lạ.\n• Shop có hỗ trợ shipper mang size mới đến tận nhà đổi cho bạn vô cùng tiện lợi nhé!"
            ],
            [
                'id' => 'qa_fashion_4',
                'question' => 'Phí vận chuyển và thời gian giao hàng bao lâu?',
                'keywords' => 'freeship, phí ship, tiền ship, giao hàng, vận chuyển, bao lâu nhận, ship tỉnh, phí giao, kiểm tra hàng, cod',
                'answer' => "Dạ chính sách giao hàng của shop:\n• Miễn phí vận chuyển (Freeship) toàn quốc cho đơn hàng từ 300.000đ trở lên.\n• Đơn dưới 300.000đ phí ship đồng giá 25.000đ toàn quốc.\n• Thời gian giao: Nội thành 1-2 ngày, ngoại thành và các tỉnh 2-4 ngày.\n• Bạn luôn được quyền mở gói hàng kiểm tra trước khi thanh toán (COD) ạ!"
            ],
            [
                'id' => 'qa_fashion_5',
                'question' => 'Địa chỉ showroom và giờ mở cửa thế nào?',
                'keywords' => 'giờ làm việc, giờ mở cửa, thời gian, cuối tuần, thứ 7, chủ nhật, mấy giờ đóng cửa, địa chỉ, showroom, cửa hàng ở đâu',
                'answer' => "Dạ showroom LTTheme Fashion mở cửa từ 8h30 đến 22h00 tất cả các ngày trong tuần (kể cả Thứ Bảy, Chủ Nhật và ngày Lễ) ạ:\n📍 Showroom 1: 123 Đường Thời Trang, Quận 1, TP. Hồ Chí Minh.\n📍 Showroom 2: 456 Phố Phong Cách, Quận Hoàn Kiếm, Hà Nội."
            ],
            [
                'id' => 'qa_fashion_6',
                'question' => 'Làm sao để bảo quản và giặt quần áo không bị phai màu?',
                'keywords' => 'bảo quản, giặt áo, giặt ủi, phai màu, xù lông, ủi đồ, giặt máy, cách giặt',
                'answer' => "Dạ để quần áo luôn bền đẹp như mới, shop gợi ý bạn:\n1. Lộn trái quần áo khi giặt và phơi để giữ màu vải và hình in.\n2. Không sử dụng nước tẩy javel mạnh; giặt riêng đồ trắng và đồ tối màu.\n3. Phơi ở nơi thoáng mát, tránh ánh nắng gắt chiếu trực tiếp.\n4. Ủi/là ở nhiệt độ trung bình dưới 150°C ạ."
            ],
            [
                'id' => 'qa_fashion_7',
                'question' => 'Shop có ưu đãi gì cho khách hàng mới và thành viên không?',
                'keywords' => 'khuyến mãi, giảm giá, voucher, ưu đãi, mã giảm, quà tặng, thành viên, tích điểm',
                'answer' => "Dạ shop đang có chương trình ưu đãi rất hấp dẫn:\n• Giảm ngay 10% cho đơn hàng đầu tiên khi bấm Theo Dõi shop.\n• Tặng voucher 50.000đ cho đơn hàng từ 500.000đ.\n• Tích điểm thành viên 5% giá trị mỗi đơn để giảm giá cho các lần mua sắm tiếp theo ạ!"
            ],
            [
                'id' => 'qa_fashion_8',
                'question' => 'Làm sao để liên hệ trực tiếp với nhân viên tư vấn?',
                'keywords' => 'hotline, số điện thoại, gặp nhân viên, tư vấn viên, liên hệ, gọi điện, tổng đài, sđt, zalo',
                'answer' => "Dạ bạn có thể gọi hotline trực tiếp hoặc nhắn Zalo qua số 0988.123.456 (hỗ trợ 8h30 - 22h00 hằng ngày) hoặc để lại số điện thoại tại khung chat này, nhân viên shop sẽ liên hệ tư vấn ngay nhé!"
            ]
        ];
    }

    /**
     * Khởi tạo dữ liệu mẫu khi kích hoạt plugin
     */
    public static function on_activate() {
        if (!get_option(self::OPTION_SETTINGS)) {
            $default_settings = [
                'bot_name' => 'Trợ Lý Shop Quần Áo LTTheme 👗',
                'welcome_msg' => 'Xin chào! Tôi là Trợ lý Shop Quần Áo LTTheme Fashion. Bạn cần hỗ trợ chọn size, xem mẫu mới hay tư vấn chính sách gì ạ?',
                'fallback_msg' => 'Nhân viên sẽ liên lạc với bạn sớm',
                'enable_lead_capture' => '1',
                'primary_color' => '#2563eb',
                'bot_position' => 'right',
                'show_quick_questions' => '1'
            ];
            update_option(self::OPTION_SETTINGS, $default_settings);
        }

        if (get_option(self::OPTION_QA) === false) {
            update_option(self::OPTION_QA, self::get_default_qa());
        }

        if (get_option(self::OPTION_LEADS) === false) {
            update_option(self::OPTION_LEADS, []);
        }
    }

    /**
     * Lấy cài đặt
     */
    public static function get_settings() {
        $defaults = [
            'bot_name' => 'Trợ Lý Shop Quần Áo LTTheme 👗',
            'welcome_msg' => 'Xin chào! Tôi là Trợ lý Shop Quần Áo LTTheme Fashion. Bạn cần hỗ trợ chọn size, xem mẫu mới hay tư vấn chính sách gì ạ?',
            'fallback_msg' => 'Nhân viên sẽ liên lạc với bạn sớm',
            'enable_lead_capture' => '1',
            'primary_color' => '#2563eb',
            'bot_position' => 'right',
            'show_quick_questions' => '1'
        ];
        $settings = get_option(self::OPTION_SETTINGS, []);
        $parsed = wp_parse_args($settings, $defaults);
        if (isset($parsed['bot_name']) && ($parsed['bot_name'] === 'Trợ Lý Khách Hàng 24/7' || empty($parsed['bot_name']))) {
            $parsed['bot_name'] = $defaults['bot_name'];
        }
        if (isset($parsed['welcome_msg']) && (mb_stripos($parsed['welcome_msg'], 'trợ lý ảo hỗ trợ 24/7') !== false || empty($parsed['welcome_msg']))) {
            $parsed['welcome_msg'] = $defaults['welcome_msg'];
        }
        return $parsed;
    }

    /**
     * Lấy danh sách Q&A
     */
    public static function get_qa_list() {
        $list = get_option(self::OPTION_QA, []);
        if (empty($list) || (count($list) <= 4 && isset($list[0]['id']) && $list[0]['id'] === 'qa_1')) {
            $list = self::get_default_qa();
            update_option(self::OPTION_QA, $list);
        }
        return is_array($list) ? $list : [];
    }

    /**
     * Đăng ký Menu Admin
     */
    public function register_admin_menu() {
        add_menu_page(
            'Chatbot Hỗ Trợ',
            'Chatbot Hỗ Trợ',
            'manage_options',
            'chatbot-support',
            [$this, 'render_admin_page'],
            'dashicons-format-chat',
            26
        );
    }

    /**
     * Xử lý lưu form Admin (Thêm/Sửa/Xóa Q&A, Cài đặt, Xóa Lead)
     */
    public function handle_admin_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'chatbot-support') {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Xử lý thêm hoặc cập nhật câu hỏi
        if (isset($_POST['chatbot_action']) && $_POST['chatbot_action'] === 'save_qa') {
            check_admin_referer('chatbot_save_qa_nonce');

            $qa_id = sanitize_text_field($_POST['qa_id'] ?? '');
            $question = sanitize_text_field($_POST['question'] ?? '');
            $keywords = sanitize_text_field($_POST['keywords'] ?? '');
            $answer = wp_kses_post(wp_unslash($_POST['answer'] ?? ''));

            if (!empty($question) && !empty($answer)) {
                $qa_list = self::get_qa_list();
                if (!empty($qa_id)) {
                    // Update
                    foreach ($qa_list as &$item) {
                        if ($item['id'] === $qa_id) {
                            $item['question'] = $question;
                            $item['keywords'] = $keywords;
                            $item['answer'] = $answer;
                            break;
                        }
                    }
                } else {
                    // Insert new
                    $qa_list[] = [
                        'id' => 'qa_' . time() . '_' . wp_rand(100, 999),
                        'question' => $question,
                        'keywords' => $keywords,
                        'answer' => $answer
                    ];
                }
                update_option(self::OPTION_QA, $qa_list);
                wp_safe_redirect(admin_url('admin.php?page=chatbot-support&tab=qa&msg=saved'));
                exit;
            }
        }

        // Xóa câu hỏi
        if (isset($_GET['action']) && $_GET['action'] === 'delete_qa' && !empty($_GET['qa_id'])) {
            check_admin_referer('chatbot_delete_qa_' . $_GET['qa_id']);
            $qa_id = sanitize_text_field($_GET['qa_id']);
            $qa_list = self::get_qa_list();
            $new_list = array_filter($qa_list, function($item) use ($qa_id) {
                return $item['id'] !== $qa_id;
            });
            update_option(self::OPTION_QA, array_values($new_list));
            wp_safe_redirect(admin_url('admin.php?page=chatbot-support&tab=qa&msg=deleted'));
            exit;
        }

        // Lưu Cài đặt
        if (isset($_POST['chatbot_action']) && $_POST['chatbot_action'] === 'save_settings') {
            check_admin_referer('chatbot_save_settings_nonce');

            $settings = [
                'bot_name' => sanitize_text_field($_POST['bot_name'] ?? 'Trợ Lý Khách Hàng 24/7'),
                'welcome_msg' => sanitize_textarea_field($_POST['welcome_msg'] ?? ''),
                'fallback_msg' => sanitize_text_field($_POST['fallback_msg'] ?? 'Nhân viên sẽ liên lạc với bạn sớm'),
                'enable_lead_capture' => isset($_POST['enable_lead_capture']) ? '1' : '0',
                'primary_color' => sanitize_hex_color($_POST['primary_color'] ?? '#2563eb'),
                'bot_position' => in_array($_POST['bot_position'] ?? '', ['right', 'left']) ? $_POST['bot_position'] : 'right',
                'show_quick_questions' => isset($_POST['show_quick_questions']) ? '1' : '0'
            ];
            update_option(self::OPTION_SETTINGS, $settings);
            wp_safe_redirect(admin_url('admin.php?page=chatbot-support&tab=settings&msg=settings_saved'));
            exit;
        }

        // Xóa Lead
        if (isset($_GET['action']) && $_GET['action'] === 'delete_lead' && !empty($_GET['lead_id'])) {
            check_admin_referer('chatbot_delete_lead_' . $_GET['lead_id']);
            $lead_id = sanitize_text_field($_GET['lead_id']);
            $leads = get_option(self::OPTION_LEADS, []);
            $new_leads = array_filter($leads, function($item) use ($lead_id) {
                return ($item['id'] ?? '') !== $lead_id;
            });
            update_option(self::OPTION_LEADS, array_values($new_leads));
            wp_safe_redirect(admin_url('admin.php?page=chatbot-support&tab=leads&msg=lead_deleted'));
            exit;
        }

        // Xóa tất cả Leads
        if (isset($_POST['chatbot_action']) && $_POST['chatbot_action'] === 'clear_all_leads') {
            check_admin_referer('chatbot_clear_leads_nonce');
            update_option(self::OPTION_LEADS, []);
            wp_safe_redirect(admin_url('admin.php?page=chatbot-support&tab=leads&msg=all_leads_cleared'));
            exit;
        }
    }

    /**
     * Giao diện Quản trị
     */
    public function render_admin_page() {
        $tab = sanitize_key($_GET['tab'] ?? 'qa');
        $qa_list = self::get_qa_list();
        $settings = self::get_settings();
        $leads = get_option(self::OPTION_LEADS, []);
        if (!is_array($leads)) $leads = [];

        // Thông tin câu hỏi cần sửa nếu có
        $edit_item = null;
        if ($tab === 'qa' && isset($_GET['edit'])) {
            $edit_id = sanitize_text_field($_GET['edit']);
            foreach ($qa_list as $item) {
                if ($item['id'] === $edit_id) {
                    $edit_item = $item;
                    break;
                }
            }
        }
        ?>
        <style>
            .cb-wrap { margin-top: 20px; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; }
            .cb-header { display: flex; align-items: center; gap: 15px; margin-bottom: 25px; }
            .cb-header h1 { margin: 0; font-size: 24px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 10px; }
            .cb-header .cb-badge { background: #2563eb; color: #fff; font-size: 12px; font-weight: 600; padding: 3px 10px; border-radius: 9999px; }
            .cb-tabs { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; margin-bottom: 24px; }
            .cb-tab-item { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; text-decoration: none; font-weight: 600; font-size: 14px; color: #64748b; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
            .cb-tab-item:hover { color: #2563eb; }
            .cb-tab-item.active { color: #2563eb; border-bottom-color: #2563eb; }
            .cb-card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); padding: 24px; margin-bottom: 24px; }
            .cb-grid { display: grid; grid-template-columns: 1fr 380px; gap: 24px; }
            @media (max-width: 960px) { .cb-grid { grid-template-columns: 1fr; } }
            .cb-form-group { margin-bottom: 18px; }
            .cb-form-group label { display: block; font-weight: 600; color: #334155; margin-bottom: 6px; font-size: 13px; }
            .cb-form-group input[type="text"], .cb-form-group input[type="email"], .cb-form-group textarea, .cb-form-group select { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 14px; box-sizing: border-box; }
            .cb-form-group input:focus, .cb-form-group textarea:focus { border-color: #2563eb; outline: none; box-shadow: 0 0 0 3px rgba(37,99,235,0.15); }
            .cb-hint { font-size: 12px; color: #64748b; margin-top: 4px; line-height: 1.4; }
            .cb-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 18px; font-weight: 600; font-size: 13px; border-radius: 6px; border: none; cursor: pointer; text-decoration: none; transition: all 0.15s ease; }
            .cb-btn-primary { background: #2563eb; color: #fff; }
            .cb-btn-primary:hover { background: #1d4ed8; color: #fff; }
            .cb-btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
            .cb-btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
            .cb-btn-danger { background: #ef4444; color: #fff; }
            .cb-btn-danger:hover { background: #dc2626; color: #fff; }
            .cb-table { width: 100%; border-collapse: collapse; text-align: left; }
            .cb-table th { background: #f8fafc; color: #475569; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; }
            .cb-table td { padding: 14px; border-bottom: 1px solid #f1f5f9; font-size: 13px; color: #334155; vertical-align: top; }
            .cb-table tr:hover td { background: #f8fafc; }
            .cb-tag { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin: 2px 4px 2px 0; }
            .cb-notice { background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 10px 16px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-size: 14px; }
            .cb-empty-state { text-align: center; padding: 40px 20px; color: #64748b; }
            .cb-empty-state span.dashicons { font-size: 48px; width: 48px; height: 48px; color: #94a3b8; margin-bottom: 12px; }
        </style>

        <div class="wrap cb-wrap">
            <div class="cb-header">
                <h1>
                    <span class="dashicons dashicons-format-chat" style="font-size: 30px; width: 30px; height: 30px; color: #2563eb;"></span>
                    Quản Lý Chatbot Hỗ Trợ Khách Hàng
                </h1>
                <span class="cb-badge">v<?php echo esc_html(self::VERSION); ?></span>
            </div>

            <?php if (isset($_GET['msg'])): ?>
                <div class="cb-notice">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php
                    switch ($_GET['msg']) {
                        case 'saved': echo 'Đã lưu câu hỏi & câu trả lời thành công!'; break;
                        case 'deleted': echo 'Đã xóa câu hỏi khỏi hệ thống!'; break;
                        case 'settings_saved': echo 'Đã cập nhật cài đặt chatbot thành công!'; break;
                        case 'lead_deleted': echo 'Đã xóa yêu cầu của khách hàng!'; break;
                        case 'all_leads_cleared': echo 'Đã làm trống toàn bộ danh sách khách cần hỗ trợ!'; break;
                        default: echo 'Thao tác thành công!';
                    }
                    ?>
                </div>
            <?php endif; ?>

            <div class="cb-tabs">
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-support&tab=qa')); ?>" class="cb-tab-item <?php echo $tab === 'qa' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-editor-help"></span> Bộ Câu Hỏi & Trả Lời (<?php echo count($qa_list); ?>)
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-support&tab=settings')); ?>" class="cb-tab-item <?php echo $tab === 'settings' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-admin-generic"></span> Cài Đặt Chatbot
                </a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-support&tab=leads')); ?>" class="cb-tab-item <?php echo $tab === 'leads' ? 'active' : ''; ?>">
                    <span class="dashicons dashicons-phone"></span> Khách Cần Hỗ Trợ (<?php echo count($leads); ?>)
                </a>
            </div>

            <?php if ($tab === 'qa'): ?>
                <div class="cb-grid">
                    <!-- Danh sách Q&A bên trái -->
                    <div class="cb-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                            <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">Danh Sách Câu Hỏi Đã Cấu Hình</h2>
                            <span style="font-size: 12px; color: #64748b;">Tổng cộng: <strong><?php echo count($qa_list); ?></strong> câu</span>
                        </div>

                        <?php if (empty($qa_list)): ?>
                            <div class="cb-empty-state">
                                <span class="dashicons dashicons-format-chat"></span>
                                <p style="font-size: 15px; font-weight: 600; margin: 0 0 6px;">Chưa có câu hỏi nào trong tập dữ liệu</p>
                                <p style="font-size: 13px; margin: 0;">Hãy thêm câu hỏi đầu tiên ở cột bên phải để chatbot bắt đầu tự động trả lời.</p>
                            </div>
                        <?php else: ?>
                            <table class="cb-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;">STT</th>
                                        <th style="width: 32%;">Câu Hỏi & Từ Khóa</th>
                                        <th style="width: 48%;">Nội Dung Trả Lời</th>
                                        <th style="width: 15%; text-align: right;">Thao Tác</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($qa_list as $index => $item): ?>
                                        <tr>
                                            <td style="color: #94a3b8; font-weight: 600;"><?php echo $index + 1; ?></td>
                                            <td>
                                                <div style="font-weight: 600; color: #1e293b; margin-bottom: 6px;"><?php echo esc_html($item['question']); ?></div>
                                                <?php if (!empty($item['keywords'])): ?>
                                                    <div>
                                                        <?php
                                                        $keywords = explode(',', $item['keywords']);
                                                        foreach ($keywords as $kw):
                                                            $kw_clean = trim($kw);
                                                            if (!empty($kw_clean)):
                                                        ?>
                                                            <span class="cb-tag"><?php echo esc_html($kw_clean); ?></span>
                                                        <?php endif; endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="line-height: 1.5; color: #475569;">
                                                <?php echo nl2br(esc_html($item['answer'])); ?>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-support&tab=qa&edit=' . $item['id'])); ?>" class="cb-btn cb-btn-secondary" style="padding: 4px 8px; font-size: 12px; margin-right: 4px;">
                                                    <span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px;"></span> Sửa
                                                </a>
                                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=chatbot-support&action=delete_qa&qa_id=' . $item['id']), 'chatbot_delete_qa_' . $item['id'])); ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa câu hỏi này không?');" class="cb-btn cb-btn-danger" style="padding: 4px 8px; font-size: 12px;">
                                                    <span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <!-- Form Thêm/Sửa bên phải -->
                    <div class="cb-card" style="height: fit-content;">
                        <h2 style="margin: 0 0 16px 0; font-size: 16px; font-weight: 700; color: #1e293b;">
                            <?php echo $edit_item ? 'Chỉnh Sửa Câu Hỏi' : 'Thêm Câu Hỏi Mới'; ?>
                        </h2>

                        <form method="post" action="">
                            <?php wp_nonce_field('chatbot_save_qa_nonce'); ?>
                            <input type="hidden" name="chatbot_action" value="save_qa">
                            <input type="hidden" name="qa_id" value="<?php echo esc_attr($edit_item['id'] ?? ''); ?>">

                            <div class="cb-form-group">
                                <label>Câu hỏi chính / Tiêu đề câu hỏi <span style="color: #ef4444;">*</span></label>
                                <input type="text" name="question" required placeholder="Ví dụ: Shop có ship hàng toàn quốc không?" value="<?php echo esc_attr($edit_item['question'] ?? ''); ?>">
                                <div class="cb-hint">Câu hỏi chuẩn sẽ hiển thị trong danh sách gợi ý nhanh cho khách hàng.</div>
                            </div>

                            <div class="cb-form-group">
                                <label>Từ khóa / Cách hỏi tương đương (Tùy chọn)</label>
                                <input type="text" name="keywords" placeholder="phí ship, tiền ship, giao hàng toàn quốc, ship cod" value="<?php echo esc_attr($edit_item['keywords'] ?? ''); ?>">
                                <div class="cb-hint">Ngăn cách bởi dấu phẩy. Giúp chatbot nhận diện chính xác khi khách dùng nhiều cách diễn đạt khác nhau.</div>
                            </div>

                            <div class="cb-form-group">
                                <label>Nội dung câu trả lời của Bot <span style="color: #ef4444;">*</span></label>
                                <textarea name="answer" rows="6" required placeholder="Nhập câu trả lời chi tiết và lịch sự mà bot sẽ phản hồi cho khách..."><?php echo esc_textarea($edit_item['answer'] ?? ''); ?></textarea>
                                <div class="cb-hint">Hỗ trợ xuống dòng, đường link hoặc số điện thoại liên hệ.</div>
                            </div>

                            <div style="display: flex; gap: 8px; margin-top: 20px;">
                                <button type="submit" class="cb-btn cb-btn-primary" style="flex: 1;">
                                    <span class="dashicons dashicons-saved"></span> <?php echo $edit_item ? 'Cập Nhật Câu Hỏi' : 'Lưu Câu Hỏi'; ?>
                                </button>
                                <?php if ($edit_item): ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=chatbot-support&tab=qa')); ?>" class="cb-btn cb-btn-secondary">Hủy Bỏ</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($tab === 'settings'): ?>
                <div style="max-width: 750px;">
                    <div class="cb-card">
                        <h2 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 700; color: #1e293b; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px;">
                            Cấu Hình Widget Chatbot Khách Hàng
                        </h2>

                        <form method="post" action="">
                            <?php wp_nonce_field('chatbot_save_settings_nonce'); ?>
                            <input type="hidden" name="chatbot_action" value="save_settings">

                            <div class="cb-form-group">
                                <label>Tên Trợ Lý / Chatbot hiển thị trên Widget</label>
                                <input type="text" name="bot_name" value="<?php echo esc_attr($settings['bot_name']); ?>" required>
                                <div class="cb-hint">Ví dụ: Trợ Lý Khách Hàng 24/7, CSKH Online...</div>
                            </div>

                            <div class="cb-form-group">
                                <label>Lời chào mở đầu khi khách mở khung chat</label>
                                <textarea name="welcome_msg" rows="3" required><?php echo esc_textarea($settings['welcome_msg']); ?></textarea>
                                <div class="cb-hint">Tin nhắn tự động xuất hiện đầu tiên để chào đón khách.</div>
                            </div>

                            <div class="cb-form-group" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px;">
                                <label style="color: #0f172a; font-weight: 700;">
                                    Câu phản hồi khi KH hỏi ngoài dữ liệu (Fallback Message) <span style="color: #ef4444;">*</span>
                                </label>
                                <input type="text" name="fallback_msg" value="<?php echo esc_attr($settings['fallback_msg']); ?>" required style="font-weight: 600; color: #2563eb; background: #fff;">
                                <div class="cb-hint" style="color: #475569;">
                                    <strong>Theo yêu cầu:</strong> Mặc định là <code>Nhân viên sẽ liên lạc với bạn sớm</code> khi bot không tìm thấy câu trả lời trong dữ liệu.
                                </div>
                            </div>

                            <div class="cb-form-group">
                                <label>
                                    <input type="checkbox" name="enable_lead_capture" value="1" <?php checked($settings['enable_lead_capture'], '1'); ?>>
                                    Cho phép khách để lại Số Điện Thoại / Lời Nhắn khi gặp câu fallback
                                </label>
                                <div class="cb-hint">Khi bot báo "Nhân viên sẽ liên lạc với bạn sớm", khung chat sẽ hiện form tiện ích để khách gửi số điện thoại, giúp nhân viên chủ động liên hệ.</div>
                            </div>

                            <div class="cb-form-group">
                                <label>
                                    <input type="checkbox" name="show_quick_questions" value="1" <?php checked($settings['show_quick_questions'], '1'); ?>>
                                    Hiển thị nút gợi ý câu hỏi nhanh (Quick Suggestion Chips)
                                </label>
                                <div class="cb-hint">Hiển thị các câu hỏi thường gặp dưới dạng nút bấm để khách chỉ cần chạm nhẹ là có câu trả lời ngay.</div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="cb-form-group">
                                    <label>Màu sắc chủ đạo (Primary Color)</label>
                                    <input type="color" name="primary_color" value="<?php echo esc_attr($settings['primary_color']); ?>" style="height: 40px; padding: 2px 6px; cursor: pointer;">
                                </div>
                                <div class="cb-form-group">
                                    <label>Vị trí hiển thị nút chat</label>
                                    <select name="bot_position">
                                        <option value="right" <?php selected($settings['bot_position'], 'right'); ?>>Góc Dưới Bên Phải (Khuyên dùng)</option>
                                        <option value="left" <?php selected($settings['bot_position'], 'left'); ?>>Góc Dưới Bên Trái</option>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top: 24px;">
                                <button type="submit" class="cb-btn cb-btn-primary" style="padding: 10px 24px;">
                                    <span class="dashicons dashicons-saved"></span> Lưu Cài Đặt Chatbot
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            <?php elseif ($tab === 'leads'): ?>
                <div class="cb-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                        <div>
                            <h2 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b;">Danh Sách Khách Hàng Cần Nhân Viên Liên Hệ</h2>
                            <p style="margin: 4px 0 0; font-size: 13px; color: #64748b;">
                                Ghi nhận tự động khi khách hỏi nội dung chưa có trong dữ liệu và gửi thông tin liên lạc.
                            </p>
                        </div>
                        <?php if (!empty($leads)): ?>
                            <form method="post" action="" onsubmit="return confirm('Bạn có chắc chắn muốn xóa toàn bộ danh sách này?');">
                                <?php wp_nonce_field('chatbot_clear_leads_nonce'); ?>
                                <input type="hidden" name="chatbot_action" value="clear_all_leads">
                                <button type="submit" class="cb-btn cb-btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                    <span class="dashicons dashicons-trash"></span> Xóa Tất Cả
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($leads)): ?>
                        <div class="cb-empty-state">
                            <span class="dashicons dashicons-phone"></span>
                            <p style="font-size: 15px; font-weight: 600; margin: 0 0 6px;">Chưa có yêu cầu liên hệ nào</p>
                            <p style="font-size: 13px; margin: 0;">Khi khách hàng để lại số điện thoại/lời nhắn từ widget chatbot, thông tin sẽ được lưu đầy đủ tại đây.</p>
                        </div>
                    <?php else: ?>
                        <table class="cb-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;">Thời Gian</th>
                                    <th style="width: 25%;">Số ĐT / Thông Tin Khách</th>
                                    <th style="width: 50%;">Nội Dung Khách Đã Hỏi</th>
                                    <th style="width: 10%; text-align: right;">Thao Tác</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($leads) as $item): ?>
                                    <tr>
                                        <td style="color: #64748b; font-size: 12px;">
                                            <?php echo esc_html($item['time'] ?? 'Vừa xong'); ?>
                                        </td>
                                        <td>
                                            <strong style="color: #2563eb; font-size: 14px;">
                                                <a href="tel:<?php echo esc_attr($item['contact'] ?? ''); ?>" style="text-decoration: none; color: inherit;">
                                                    📞 <?php echo esc_html($item['contact'] ?? 'Chưa cung cấp'); ?>
                                                </a>
                                            </strong>
                                            <?php if (!empty($item['note'])): ?>
                                                <div style="font-size: 12px; color: #64748b; margin-top: 3px;">
                                                    Ghi chú: <?php echo esc_html($item['note']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #334155; line-height: 1.4;">
                                            "<?php echo esc_html($item['question'] ?? 'Hỏi ngoài danh mục'); ?>"
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=chatbot-support&tab=leads&action=delete_lead&lead_id=' . ($item['id'] ?? '')), 'chatbot_delete_lead_' . ($item['id'] ?? ''))); ?>" onclick="return confirm('Xóa yêu cầu này?');" class="cb-btn cb-btn-danger" style="padding: 4px 8px; font-size: 12px;">
                                                <span class="dashicons dashicons-trash"></span>
                                            </a>
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
     * Chuẩn hóa tiếng Việt phục vụ so khớp thông minh
     */
    private static function normalize_text($str) {
        $str = mb_strtolower(trim($str), 'UTF-8');
        // Loại bỏ ký tự đặc biệt, giữ lại chữ và số
        $str = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    private static function remove_accents($str) {
        $accents = [
            'a' => ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ'],
            'd' => ['đ'],
            'e' => ['é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ'],
            'i' => ['í','ì','ỉ','ĩ','ị'],
            'o' => ['ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ'],
            'u' => ['ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự'],
            'y' => ['ý','ỳ','ỷ','ỹ','ỵ']
        ];
        $res = mb_strtolower($str, 'UTF-8');
        foreach ($accents as $non_accent => $accent_list) {
            $res = str_replace($accent_list, $non_accent, $res);
        }
        return $res;
    }

    /**
     * Tìm câu trả lời phù hợp nhất từ tập dữ liệu Q&A
     */
    private static function find_best_answer($query) {
        $qa_list = self::get_qa_list();
        if (empty($qa_list)) {
            return null;
        }

        $query_norm = self::normalize_text($query);
        $query_noacc = self::remove_accents($query_norm);
        $query_words = array_filter(explode(' ', $query_norm));
        $query_words_noacc = array_filter(explode(' ', $query_noacc));

        $best_match = null;
        $highest_score = 0;

        foreach ($qa_list as $item) {
            $score = 0;
            $question_norm = self::normalize_text($item['question']);
            $question_noacc = self::remove_accents($question_norm);

            // 1. So khớp chính xác 100%
            if ($query_norm === $question_norm || $query_noacc === $question_noacc) {
                $score += 100;
            }

            // 2. Chứa toàn bộ câu hỏi hoặc ngược lại
            if (mb_strpos($query_norm, $question_norm) !== false || mb_strpos($question_norm, $query_norm) !== false) {
                $score += 80;
            } elseif (mb_strpos($query_noacc, $question_noacc) !== false || mb_strpos($question_noacc, $query_noacc) !== false) {
                $score += 70;
            }

            // 3. So khớp từ khóa / câu hỏi tương đương
            if (!empty($item['keywords'])) {
                $keywords = explode(',', $item['keywords']);
                foreach ($keywords as $kw) {
                    $kw_norm = self::normalize_text($kw);
                    $kw_noacc = self::remove_accents($kw_norm);
                    if (empty($kw_norm)) continue;

                    if ($query_norm === $kw_norm || $query_noacc === $kw_noacc) {
                        $score += 90;
                    } elseif (mb_strpos($query_norm, $kw_norm) !== false || mb_strpos($query_noacc, $kw_noacc) !== false) {
                        $score += 65;
                    }
                }
            }

            // 4. So khớp số lượng từ khóa trùng lặp (Token Overlap)
            $q_words = array_filter(explode(' ', $question_norm));
            $common_words = array_intersect($query_words, $q_words);
            if (count($query_words) > 0 && count($common_words) > 0) {
                $overlap_ratio = count($common_words) / max(count($query_words), count($q_words));
                $score += ($overlap_ratio * 40);
            }

            // So khớp không dấu cho token
            $q_words_noacc = array_filter(explode(' ', $question_noacc));
            $common_words_noacc = array_intersect($query_words_noacc, $q_words_noacc);
            if (count($query_words_noacc) > 0 && count($common_words_noacc) > 0) {
                $overlap_ratio_noacc = count($common_words_noacc) / max(count($query_words_noacc), count($q_words_noacc));
                $score += ($overlap_ratio_noacc * 35);
            }

            if ($score > $highest_score) {
                $highest_score = $score;
                $best_match = $item;
            }
        }

        // Ngưỡng điểm tối thiểu để được xem là khớp câu hỏi
        if ($highest_score >= 35 && $best_match) {
            return [
                'matched' => true,
                'score' => $highest_score,
                'answer' => $best_match['answer'],
                'question' => $best_match['question']
            ];
        }

        return null;
    }

    /**
     * AJAX: Xử lý tin nhắn khách gửi lên
     */
    public function ajax_handle_message() {
        check_ajax_referer('chatbot_widget_nonce', 'nonce');

        $message = sanitize_text_field($_POST['message'] ?? '');
        if (empty($message)) {
            wp_send_json_error(['message' => 'Vui lòng nhập nội dung tin nhắn']);
        }

        $settings = self::get_settings();
        $fallback_msg = !empty($settings['fallback_msg']) ? $settings['fallback_msg'] : 'Nhân viên sẽ liên lạc với bạn sớm';

        $match_result = self::find_best_answer($message);

        $follow_up_pool = [
            'Cách chọn size quần áo theo chiều cao và cân nặng? 📏',
            'Chính sách đổi size và đổi trả hàng như thế nào? 🔄',
            'Phí vận chuyển và thời gian giao hàng bao lâu? 🚚',
            'Shop có những dòng quần áo nào nổi bật? 👗',
            'Địa chỉ showroom và giờ mở cửa thế nào? ⏰',
            'Làm sao để bảo quản và giặt quần áo không bị phai màu? 🧺'
        ];
        $suggestions = [];
        $matched_q = $match_result['question'] ?? '';
        foreach ($follow_up_pool as $sq) {
            if (empty($matched_q) || mb_strpos($sq, mb_substr($matched_q, 0, 10)) === false) {
                $suggestions[] = $sq;
            }
            if (count($suggestions) >= 3) break;
        }

        if ($match_result && !empty($match_result['answer'])) {
            wp_send_json_success([
                'is_fallback' => false,
                'answer' => nl2br(esc_html($match_result['answer'])),
                'matched_question' => $match_result['question'],
                'suggestions' => $suggestions
            ]);
        } else {
            // Không có trong tập dữ liệu -> Trả về thông báo fallback
            wp_send_json_success([
                'is_fallback' => true,
                'answer' => esc_html($fallback_msg),
                'enable_lead_capture' => ($settings['enable_lead_capture'] === '1'),
                'suggestions' => $suggestions
            ]);
        }
    }

    /**
     * AJAX: Lưu số điện thoại/thông tin liên hệ của khách khi fallback
     */
    public function ajax_save_lead() {
        check_ajax_referer('chatbot_widget_nonce', 'nonce');

        $contact = sanitize_text_field($_POST['contact'] ?? '');
        $question = sanitize_text_field($_POST['question'] ?? '');

        if (empty($contact)) {
            wp_send_json_error(['message' => 'Vui lòng nhập số điện thoại hoặc email']);
        }

        $leads = get_option(self::OPTION_LEADS, []);
        if (!is_array($leads)) $leads = [];

        $leads[] = [
            'id' => 'lead_' . time() . '_' . wp_rand(100, 999),
            'time' => current_time('d/m/Y H:i'),
            'contact' => $contact,
            'question' => $question,
            'status' => 'pending'
        ];

        update_option(self::OPTION_LEADS, $leads);

        wp_send_json_success([
            'message' => 'Cảm ơn bạn! Thông tin đã được gửi đến bộ phận hỗ trợ, nhân viên sẽ liên hệ với bạn trong thời gian sớm nhất.'
        ]);
    }

    /**
     * Render Giao diện Frontend Widget (HTML, CSS, JS)
     */
    public function render_frontend_widget() {
        $settings = self::get_settings();
        $qa_list = self::get_qa_list();
        $primary_color = esc_attr($settings['primary_color']);
        $position = $settings['bot_position'] === 'left' ? 'left' : 'right';
        $nonce = wp_create_nonce('chatbot_widget_nonce');
        $ajax_url = admin_url('admin-ajax.php');

        // Lấy 3-4 câu hỏi mẫu cho thanh Quick Question Chips
        $quick_chips = [];
        if ($settings['show_quick_questions'] === '1' && !empty($qa_list)) {
            $quick_chips = array_slice($qa_list, 0, 4);
        }
        ?>
        <!-- WP CUSTOMER SUPPORT CHATBOT WIDGET -->
        <style>
            :root {
                --cb-primary: <?php echo $primary_color; ?>;
                --cb-primary-dark: #1d4ed8;
                --cb-bg-bot: #f1f5f9;
                --cb-text-bot: #1e293b;
                --cb-text-user: #ffffff;
                --cb-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            }

            #cb-widget-container {
                position: fixed;
                bottom: 24px;
                <?php echo $position; ?>: 24px;
                z-index: 999999;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                font-size: 14px;
                line-height: 1.5;
            }

            /* Toggle Launcher Button */
            #cb-launcher {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: linear-gradient(135deg, var(--cb-primary), #3b82f6);
                color: #ffffff;
                box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.25s ease;
                border: none;
                outline: none;
                position: relative;
            }

            #cb-launcher:hover {
                transform: scale(1.08);
                box-shadow: 0 6px 20px rgba(37, 99, 235, 0.5);
            }

            #cb-launcher .cb-launcher-icon,
            #cb-launcher .cb-close-icon {
                position: absolute;
                transition: all 0.25s ease;
            }

            #cb-launcher .cb-close-icon {
                opacity: 0;
                transform: rotate(-90deg) scale(0.6);
            }

            #cb-widget-container.active #cb-launcher .cb-launcher-icon {
                opacity: 0;
                transform: rotate(90deg) scale(0.6);
            }

            #cb-widget-container.active #cb-launcher .cb-close-icon {
                opacity: 1;
                transform: rotate(0deg) scale(1);
            }

            /* Badge Pulse */
            .cb-launcher-pulse {
                position: absolute;
                top: 0;
                right: 0;
                width: 14px;
                height: 14px;
                border-radius: 50%;
                background: #22c55e;
                border: 2px solid #ffffff;
            }

            /* Chat Window */
            #cb-chat-box {
                position: fixed;
                bottom: 24px;
                right: 96px;
                width: 360px;
                max-width: calc(100vw - 110px);
                height: 520px;
                max-height: calc(100vh - 48px);
                background: #ffffff;
                border-radius: 16px;
                box-shadow: var(--cb-shadow), 0 0 0 1px rgba(0,0,0,0.06);
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
                #cb-chat-box {
                    right: 12px !important;
                    left: 12px !important;
                    bottom: 12px !important;
                    width: auto !important;
                    max-width: none !important;
                    height: calc(100vh - 24px) !important;
                }
            }

            #cb-widget-container.active #cb-chat-box {
                opacity: 1;
                visibility: visible;
                transform: translateY(0) scale(1);
            }

            /* Header */
            .cb-chat-header {
                background: linear-gradient(135deg, var(--cb-primary), #1d4ed8);
                color: #ffffff;
                padding: 14px 18px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            }

            .cb-header-info {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .cb-avatar {
                width: 38px;
                height: 38px;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }

            .cb-online-dot {
                position: absolute;
                bottom: 0;
                right: 0;
                width: 10px;
                height: 10px;
                background: #22c55e;
                border: 2px solid #ffffff;
                border-radius: 50%;
            }

            .cb-header-title {
                font-weight: 700;
                font-size: 15px;
                line-height: 1.2;
            }

            .cb-header-status {
                font-size: 11px;
                opacity: 0.9;
                display: flex;
                align-items: center;
                gap: 4px;
            }

            .cb-header-actions button {
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.85);
                cursor: pointer;
                padding: 6px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.15s;
            }

            .cb-header-actions button:hover {
                background: rgba(255, 255, 255, 0.2);
                color: #ffffff;
            }

            /* Chat Messages Area */
            .cb-chat-messages {
                flex: 1;
                padding: 16px;
                overflow-y: auto;
                display: flex;
                flex-direction: column;
                gap: 12px;
                background: #f8fafc;
                scroll-behavior: smooth;
            }

            .cb-msg {
                display: flex;
                flex-direction: column;
                max-width: 82%;
                animation: cbFadeIn 0.25s ease-out;
            }

            @keyframes cbFadeIn {
                from { opacity: 0; transform: translateY(6px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .cb-msg.bot {
                align-self: flex-start;
            }

            .cb-msg.user {
                align-self: flex-end;
            }

            .cb-bubble {
                padding: 10px 14px;
                border-radius: 14px;
                font-size: 13.5px;
                line-height: 1.45;
                word-break: break-word;
            }

            .cb-msg.bot .cb-bubble {
                background: #ffffff;
                color: var(--cb-text-bot);
                border: 1px solid #e2e8f0;
                border-bottom-left-radius: 3px;
                box-shadow: 0 1px 2px rgba(0,0,0,0.04);
            }

            .cb-msg.user .cb-bubble {
                background: var(--cb-primary);
                color: var(--cb-text-user);
                border-bottom-right-radius: 3px;
                box-shadow: 0 1px 3px rgba(37,99,235,0.25);
            }

            .cb-msg-time {
                font-size: 10px;
                color: #94a3b8;
                margin-top: 4px;
                padding: 0 4px;
            }

            .cb-msg.user .cb-msg-time {
                text-align: right;
            }

            /* Typing indicator */
            .cb-typing {
                display: none;
                align-self: flex-start;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                padding: 10px 14px;
                border-radius: 14px;
                border-bottom-left-radius: 3px;
                gap: 4px;
                align-items: center;
            }

            .cb-typing.show {
                display: flex;
            }

            .cb-dot {
                width: 6px;
                height: 6px;
                background: #94a3b8;
                border-radius: 50%;
                animation: cbBounce 1.2s infinite ease-in-out;
            }

            .cb-dot:nth-child(2) { animation-delay: 0.2s; }
            .cb-dot:nth-child(3) { animation-delay: 0.4s; }

            @keyframes cbBounce {
                0%, 80%, 100% { transform: translateY(0); }
                40% { transform: translateY(-5px); }
            }

            /* Quick Suggestion Chips */
            .cb-quick-chips {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                margin-top: 6px;
            }

            .cb-chip-btn {
                background: #ffffff;
                border: 1px solid #cbd5e1;
                color: #2563eb;
                font-size: 12px;
                padding: 5px 10px;
                border-radius: 16px;
                cursor: pointer;
                text-align: left;
                transition: all 0.15s ease;
                line-height: 1.3;
            }

            .cb-chip-btn:hover {
                background: #eff6ff;
                border-color: #93c5fd;
            }

            /* Lead Capture Form */
            .cb-lead-box {
                margin-top: 8px;
                background: #eff6ff;
                border: 1px solid #bfdbfe;
                border-radius: 10px;
                padding: 10px;
                font-size: 12.5px;
            }

            .cb-lead-box p {
                margin: 0 0 8px;
                color: #1e40af;
                font-weight: 600;
            }

            .cb-lead-input-wrap {
                display: flex;
                gap: 6px;
            }

            .cb-lead-input-wrap input {
                flex: 1;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                padding: 6px 10px;
                font-size: 12px;
                background: #ffffff;
            }

            .cb-lead-input-wrap input:focus {
                outline: none;
                border-color: #2563eb;
            }

            .cb-lead-input-wrap button {
                background: #2563eb;
                color: #ffffff;
                border: none;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 12px;
                font-weight: 600;
                cursor: pointer;
                white-space: nowrap;
            }

            .cb-lead-input-wrap button:hover {
                background: #1d4ed8;
            }

            /* Input Area */
            .cb-chat-input-area {
                padding: 12px;
                background: #ffffff;
                border-top: 1px solid #e2e8f0;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .cb-input-field {
                flex: 1;
                border: 1px solid #cbd5e1;
                border-radius: 20px;
                padding: 8px 14px;
                font-size: 13.5px;
                outline: none;
                transition: border-color 0.15s, box-shadow 0.15s;
                box-sizing: border-box;
                font-family: inherit;
            }

            .cb-input-field:focus {
                border-color: var(--cb-primary);
                box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
            }

            .cb-send-btn {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: var(--cb-primary);
                color: #ffffff;
                border: none;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.15s, transform 0.1s;
                flex-shrink: 0;
            }

            .cb-send-btn:hover {
                background: var(--cb-primary-dark);
                transform: scale(1.05);
            }

            .cb-send-btn:disabled {
                background: #cbd5e1;
                cursor: not-allowed;
                transform: none;
            }
        </style>

        <div id="cb-widget-container">
            <!-- Launcher Button -->
            <button id="cb-launcher" aria-label="Mở cửa sổ hỗ trợ trực tuyến" title="1. Chatbot FAQ (Dữ liệu có sẵn) 💬">
                <span class="cb-launcher-pulse"></span>
                <!-- Chat SVG Icon -->
                <svg class="cb-launcher-icon" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <!-- Close SVG Icon -->
                <svg class="cb-close-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- Chat Window -->
            <div id="cb-chat-box">
                <!-- Header -->
                <div class="cb-chat-header">
                    <div class="cb-header-info">
                        <div class="cb-avatar">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                            <span class="cb-online-dot"></span>
                        </div>
                        <div>
                            <div class="cb-header-title"><?php echo esc_html($settings['bot_name']); ?></div>
                            <div class="cb-header-status">
                                <span>●</span> Đang trực tuyến
                            </div>
                        </div>
                    </div>
                    <div class="cb-header-actions">
                        <button id="cb-close-btn" title="Thu nhỏ">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Messages Body -->
                <div class="cb-chat-messages" id="cb-messages-list">
                    <!-- Bot Welcome Message -->
                    <div class="cb-msg bot">
                        <div class="cb-bubble">
                            <?php echo nl2br(esc_html($settings['welcome_msg'])); ?>
                        </div>
                        <div class="cb-msg-time"><?php echo date_i18n('H:i'); ?></div>
                    </div>

                    <!-- Quick suggestions -->
                    <?php if (!empty($quick_chips)): ?>
                        <div class="cb-quick-chips" id="cb-quick-chips">
                            <?php foreach ($quick_chips as $chip): ?>
                                <button type="button" class="cb-chip-btn" data-question="<?php echo esc_attr($chip['question']); ?>">
                                    💡 <?php echo esc_html($chip['question']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Typing Indicator -->
                    <div class="cb-typing" id="cb-typing-indicator">
                        <span class="cb-dot"></span>
                        <span class="cb-dot"></span>
                        <span class="cb-dot"></span>
                    </div>
                </div>

                <!-- Chat Input Form -->
                <form class="cb-chat-input-area" id="cb-chat-form">
                    <input type="text" class="cb-input-field" id="cb-input" placeholder="Nhập câu hỏi của bạn..." autocomplete="off">
                    <button type="submit" class="cb-send-btn" id="cb-send" aria-label="Gửi tin nhắn">
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
            var container = document.getElementById('cb-widget-container');
            var launcher = document.getElementById('cb-launcher');
            var closeBtn = document.getElementById('cb-close-btn');
            var messagesList = document.getElementById('cb-messages-list');
            var form = document.getElementById('cb-chat-form');
            var input = document.getElementById('cb-input');
            var sendBtn = document.getElementById('cb-send');
            var typing = document.getElementById('cb-typing-indicator');
            var quickChips = document.getElementById('cb-quick-chips');

            var ajaxUrl = <?php echo json_encode($ajax_url); ?>;
            var nonce = <?php echo json_encode($nonce); ?>;
            var lastUnansweredQuestion = '';

            function scrollToBottom() {
                setTimeout(function() {
                    messagesList.scrollTop = messagesList.scrollHeight;
                }, 50);
            }

            // Toggle Open/Close
            function toggleChat() {
                var wasActive = container.classList.contains('active');
                document.querySelectorAll('#cb-ai-widget, #cb-doc-widget').forEach(function(el) {
                    el.classList.remove('active');
                });
                if (!wasActive) {
                    container.classList.add('active');
                    setTimeout(function() { input.focus(); }, 150);
                    scrollToBottom();
                } else {
                    container.classList.remove('active');
                }
            }

            launcher.addEventListener('click', toggleChat);
            closeBtn.addEventListener('click', toggleChat);

            function getCurrentTime() {
                var d = new Date();
                var h = ('0' + d.getHours()).slice(-2);
                var m = ('0' + d.getMinutes()).slice(-2);
                return h + ':' + m;
            }

            // Append User Message
            function appendUserMessage(text) {
                var msgDiv = document.createElement('div');
                msgDiv.className = 'cb-msg user';
                msgDiv.innerHTML = '<div class="cb-bubble">' + escapeHtml(text) + '</div><div class="cb-msg-time">' + getCurrentTime() + '</div>';
                messagesList.insertBefore(msgDiv, typing);
                scrollToBottom();
            }

            // Append Bot Message
            function appendBotMessage(htmlContent, isFallback, suggestions) {
                var msgDiv = document.createElement('div');
                msgDiv.className = 'cb-msg bot';
                
                var content = '<div class="cb-bubble">' + htmlContent + '</div>';

                // Nếu là phản hồi ngoài tập dữ liệu, hiển thị thêm form để lại số điện thoại hỗ trợ
                if (isFallback) {
                    content += '<div class="cb-lead-box">' +
                        '<p>📞 Để lại số điện thoại để nhân viên hỗ trợ bạn ngay:</p>' +
                        '<div class="cb-lead-input-wrap">' +
                        '<input type="text" placeholder="Số điện thoại của bạn..." class="cb-lead-phone">' +
                        '<button type="button" class="cb-lead-submit">Gửi ngay</button>' +
                        '</div>' +
                        '<div class="cb-lead-result" style="margin-top:6px; font-size:11px; display:none;"></div>' +
                        '</div>';
                }

                // Gợi ý câu hỏi tiếp theo
                if (suggestions && suggestions.length > 0) {
                    content += '<div class="cb-followup-box" style="margin-top: 6px; width: 100%;">' +
                        '<div style="font-size: 11px; font-weight: 700; color: var(--cb-primary); display: flex; align-items: center; gap: 4px; margin-bottom: 4px;">💡 Gợi ý câu hỏi tiếp theo:</div>' +
                        '<div class="cb-quick-chips" style="margin-top: 0;">';
                    suggestions.forEach(function(sug) {
                        content += '<button type="button" class="cb-chip-btn" data-question="' + escapeHtml(sug) + '">👉 ' + escapeHtml(sug) + '</button>';
                    });
                    content += '</div></div>';
                }

                content += '<div class="cb-msg-time">' + getCurrentTime() + '</div>';
                msgDiv.innerHTML = content;
                messagesList.insertBefore(msgDiv, typing);

                // Gắn sự kiện cho lead button nếu có
                if (isFallback) {
                    var leadBtn = msgDiv.querySelector('.cb-lead-submit');
                    var leadPhoneInput = msgDiv.querySelector('.cb-lead-phone');
                    var leadResult = msgDiv.querySelector('.cb-lead-result');

                    if (leadBtn && leadPhoneInput) {
                        leadBtn.addEventListener('click', function() {
                            var phone = leadPhoneInput.value.trim();
                            if (!phone) {
                                leadPhoneInput.focus();
                                return;
                            }
                            leadBtn.disabled = true;
                            leadBtn.innerText = 'Đang gửi...';

                            var formData = new FormData();
                            formData.append('action', 'chatbot_save_lead');
                            formData.append('nonce', nonce);
                            formData.append('contact', phone);
                            formData.append('question', lastUnansweredQuestion);

                            fetch(ajaxUrl, {
                                method: 'POST',
                                body: formData
                            })
                            .then(function(res) { return res.json(); })
                            .then(function(data) {
                                if (data.success) {
                                    leadResult.style.display = 'block';
                                    leadResult.style.color = '#15803d';
                                    leadResult.innerText = '✓ ' + (data.data.message || 'Đã gửi thành công!');
                                    leadPhoneInput.style.display = 'none';
                                    leadBtn.style.display = 'none';
                                } else {
                                    leadResult.style.display = 'block';
                                    leadResult.style.color = '#b91c1c';
                                    leadResult.innerText = data.data.message || 'Lỗi khi gửi thông tin.';
                                    leadBtn.disabled = false;
                                    leadBtn.innerText = 'Thử lại';
                                }
                            })
                            .catch(function() {
                                leadResult.style.display = 'block';
                                leadResult.style.color = '#b91c1c';
                                leadResult.innerText = 'Không thể kết nối máy chủ.';
                                leadBtn.disabled = false;
                                leadBtn.innerText = 'Thử lại';
                            });
                        });
                    }
                }

                scrollToBottom();
            }

            function escapeHtml(str) {
                var div = document.createElement('div');
                div.appendChild(document.createTextNode(str));
                return div.innerHTML;
            }

            // Gửi tin nhắn
            function sendMessage(text) {
                var query = text.trim();
                if (!query) return;

                // Ẩn quick chips mở đầu sau khi đã hỏi
                if (quickChips) {
                    quickChips.style.display = 'none';
                }

                appendUserMessage(query);
                input.value = '';
                input.disabled = true;
                sendBtn.disabled = true;

                // Hiển thị typing
                typing.classList.add('show');
                scrollToBottom();

                var formData = new FormData();
                formData.append('action', 'chatbot_send_message');
                formData.append('nonce', nonce);
                formData.append('message', query);

                // Giả lập độ trễ tự nhiên (400ms) để người dùng thấy bot đang xử lý
                setTimeout(function() {
                    fetch(ajaxUrl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(data) {
                        typing.classList.remove('show');
                        input.disabled = false;
                        sendBtn.disabled = false;
                        input.focus();

                        if (data.success) {
                            if (data.data.is_fallback) {
                                lastUnansweredQuestion = query;
                            }
                            appendBotMessage(data.data.answer, data.data.is_fallback && data.data.enable_lead_capture, data.data.suggestions);
                        } else {
                            appendBotMessage(data.data.message || 'Có lỗi xảy ra, vui lòng thử lại.', false, []);
                        }
                    })
                    .catch(function() {
                        typing.classList.remove('show');
                        input.disabled = false;
                        sendBtn.disabled = false;
                        input.focus();
                        appendBotMessage('Không thể kết nối đến máy chủ. Vui lòng kiểm tra lại.', false, []);
                    });
                }, 400);
            }

            // Form Submit
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                sendMessage(input.value);
            });

            // Bấm chọn câu hỏi gợi ý (Cả câu hỏi mở đầu và câu hỏi tiếp theo)
            messagesList.addEventListener('click', function(e) {
                var chip = e.target.closest('.cb-chip-btn');
                if (chip && !input.disabled) {
                    var question = chip.getAttribute('data-question') || chip.innerText.replace(/^👉\s*/, '').replace(/^💡\s*/, '').trim();
                    if (question) {
                        sendMessage(question);
                    }
                }
            });
        })();
        </script>
        <?php
    }
}

// Khởi tạo Plugin
function wp_customer_support_chatbot_init() {
    return WP_Customer_Support_Chatbot::get_instance();
}
add_action('plugins_loaded', 'wp_customer_support_chatbot_init');
