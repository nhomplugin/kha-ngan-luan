<?php
/*
Plugin Name: AI Voice & Smart Chatbox
Description: Icon nổi đọc bài viết kèm khung chatbox AI tra cứu bôi đen văn bản bằng Google AI Studio.
Version: 6.2
Author: IT Student
*/

if ( ! defined( 'ABSPATH' ) ) exit;

// -----------------------------------------------------------------------------
// 1. TẠO MENU CÀI ĐẶT API KEY TRONG TRANG QUẢN TRỊ (ADMIN MENU)
// -----------------------------------------------------------------------------
function aivr_add_admin_menu() {
    add_menu_page(
        'AI Chatbox Settings',
        'AI Chatbox',
        'manage_options',
        'aivr-settings',
        'aivr_admin_settings_page',
        'dashicons-format-chat',
        80
    );
}
add_action( 'admin_menu', 'aivr_add_admin_menu' );

function aivr_register_settings() {
    register_setting( 'aivr_settings_group', 'aivr_gemini_api_key' );
}
add_action( 'admin_init', 'aivr_register_settings' );

function aivr_admin_settings_page() {
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-format-chat"></span> Cấu hình Google AI Studio (Gemini)</h1>
        <p>Cấu hình API Key để kích hoạt tính năng Chatbox AI giải đáp và tra cứu thông tin trực tuyến khi người dùng bôi đen từ khóa.</p>
        
        <form method="post" action="options.php">
            <?php
            settings_fields( 'aivr_settings_group' );
            do_settings_sections( 'aivr_settings_group' );
            $api_key = get_option( 'aivr_gemini_api_key', '' );
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Google AI Studio API Key:</th>
                    <td>
                        <input type="password" name="aivr_gemini_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" placeholder="AIzaSy..." />
                        <p class="description">
                            Lấy API Key miễn phí tại: <a href="https://aistudio.google.com/" target="_blank">Google AI Studio</a>.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Lưu cấu hình' ); ?>
        </form>
    </div>
    <?php
}

// Trả về CSS cho widget đọc bài và chatbox AI bôi đen
function ai_voice_reader_css() {
    return <<<'CSS'
.aivr-widget, .aivr-widget *, .aivr-widget *::before, .aivr-widget *::after {
    box-sizing: border-box;
}
.aivr-widget {
    --aivr-primary: #5B4FE9;
    --aivr-primary-dark: #4038B8;
    --aivr-primary-light: #EFEDFF;
    --aivr-accent: #17B8A6;
    --aivr-accent-light: #E3FBF7;
    --aivr-danger: #E15B6B;
    --aivr-ink: #1C1930;
    --aivr-muted: #726E86;
    --aivr-surface: #FFFFFF;
    --aivr-border: #E7E5F3;
    --aivr-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-family: var(--aivr-font);
    line-height: 1.4;
    color: var(--aivr-ink);
}

/* Nút nổi đọc bài */
.aivr-fab {
    position: fixed;
    right: 24px;
    bottom: 24px;
    width: 58px;
    height: 58px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--aivr-primary), var(--aivr-primary-dark));
    color: #fff;
    box-shadow: 0 8px 20px rgba(91,79,233,0.38), 0 2px 6px rgba(0,0,0,0.12);
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    z-index: 2147483000;
}
.aivr-fab:hover { transform: translateY(-2px) scale(1.05); box-shadow: 0 12px 26px rgba(91,79,233,0.45), 0 3px 8px rgba(0,0,0,0.14); }
.aivr-fab:active { transform: scale(0.96); }
.aivr-fab__icon { display: flex; }
.aivr-fab__icon--close { display: none; }
.aivr-fab.is-open .aivr-fab__icon--idle { display: none; }
.aivr-fab.is-open .aivr-fab__icon--close { display: flex; }
.aivr-fab.is-speaking { animation: aivr-fab-pulse 1.6s ease-in-out infinite; }
@keyframes aivr-fab-pulse {
    0%, 100% { box-shadow: 0 8px 20px rgba(91,79,233,0.38), 0 0 0 0 rgba(91,79,233,0.35); }
    50% { box-shadow: 0 8px 20px rgba(91,79,233,0.38), 0 0 0 10px rgba(91,79,233,0); }
}

/* Bảng điều khiển đọc bài */
.aivr-panel {
    position: fixed;
    right: 24px;
    bottom: 94px;
    width: 320px;
    max-width: calc(100vw - 32px);
    background: var(--aivr-surface);
    border: 1px solid var(--aivr-border);
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(28,25,48,0.18), 0 4px 12px rgba(28,25,48,0.08);
    padding: 18px 18px 20px;
    opacity: 0;
    transform: translateY(12px) scale(0.96);
    pointer-events: none;
    transform-origin: bottom right;
    transition: opacity 0.22s ease, transform 0.22s ease;
    z-index: 2147482999;
}
.aivr-panel.show {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

.aivr-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}
.aivr-panel__title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 600;
}
.aivr-panel__dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: var(--aivr-accent);
    flex-shrink: 0;
}

.aivr-icon-btn {
    border: none;
    background: transparent;
    color: var(--aivr-muted);
    cursor: pointer;
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 8px;
    transition: background 0.15s ease, color 0.15s ease;
}
.aivr-icon-btn:hover { background: var(--aivr-primary-light); color: var(--aivr-primary); }

.aivr-status {
    font-size: 13px;
    color: var(--aivr-muted);
    background: var(--aivr-primary-light);
    border-radius: 10px;
    padding: 8px 10px;
    margin-bottom: 14px;
    min-height: 34px;
    display: flex;
    align-items: center;
}

.aivr-eq { display: none; align-items: flex-end; gap: 2px; height: 14px; margin-right: 8px; flex-shrink: 0; }
.aivr-status.is-speaking .aivr-eq { display: inline-flex; }
.aivr-eq span { width: 3px; background: var(--aivr-primary); border-radius: 2px; height: 4px; display: block; }
.aivr-status.is-speaking .aivr-eq span { animation: aivr-eq-bounce 0.9s ease-in-out infinite; }
.aivr-status.is-speaking .aivr-eq span:nth-child(1) { animation-delay: 0s; }
.aivr-status.is-speaking .aivr-eq span:nth-child(2) { animation-delay: 0.15s; }
.aivr-status.is-speaking .aivr-eq span:nth-child(3) { animation-delay: 0.3s; }
.aivr-status.is-speaking .aivr-eq span:nth-child(4) { animation-delay: 0.45s; }
@keyframes aivr-eq-bounce {
    0%, 100% { height: 4px; }
    50% { height: 14px; }
}

.aivr-primary-btn {
    width: 100%;
    border: none;
    cursor: pointer;
    border-radius: 14px;
    padding: 12px 16px;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    background: linear-gradient(135deg, var(--aivr-primary), var(--aivr-primary-dark));
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    transition: transform 0.12s ease, box-shadow 0.15s ease;
    box-shadow: 0 6px 14px rgba(91,79,233,0.28);
}
.aivr-primary-btn:hover { box-shadow: 0 8px 18px rgba(91,79,233,0.36); }
.aivr-primary-btn:active { transform: scale(0.98); }

.aivr-controls {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}
.aivr-chip {
    flex: 1;
    border: 1px solid var(--aivr-border);
    background: #fff;
    color: var(--aivr-ink);
    border-radius: 12px;
    padding: 9px 6px;
    display: flex; flex-direction: column; align-items: center; gap: 4px;
    font-size: 11px;
    cursor: pointer;
    transition: border-color 0.15s ease, transform 0.12s ease, background 0.15s ease;
}
.aivr-chip:hover { border-color: var(--aivr-primary); background: var(--aivr-primary-light); }
.aivr-chip:active { transform: scale(0.96); }
.aivr-chip--danger:hover { border-color: var(--aivr-danger); background: #FDECEE; color: var(--aivr-danger); }

/* Style Icon Nổi Khi Bôi Đen Text */
#aivr-select-ai-btn {
    position: absolute;
    display: none;
    z-index: 2147483647;
    background: linear-gradient(135deg, #5B4FE9, #4038B8);
    color: #fff;
    border: none;
    border-radius: 20px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(91,79,233,0.4);
    align-items: center;
    gap: 6px;
    transition: transform 0.15s ease;
}
#aivr-select-ai-btn:hover { transform: scale(1.08); }

/* Khung Chatbox AI Floating Window */
.aivr-chatbox {
    position: fixed;
    right: 24px;
    bottom: 94px;
    width: 360px;
    height: 520px;
    max-width: calc(100vw - 32px);
    max-height: calc(100vh - 120px);
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.22);
    display: none;
    flex-direction: column;
    z-index: 2147483500;
    overflow: hidden;
    border: 1px solid #E7E5F3;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.aivr-chatbox.open { display: flex; }

.aivr-chatbox-header {
    background: linear-gradient(135deg, #5B4FE9, #4038B8);
    color: #fff;
    padding: 14px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 600;
    font-size: 15px;
}
.aivr-chatbox-close {
    background: transparent;
    border: none;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    line-height: 1;
}

.aivr-chatbox-body {
    flex: 1;
    padding: 14px;
    overflow-y: auto;
    background: #f8f9fc;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.aivr-msg {
    max-width: 85%;
    padding: 10px 14px;
    border-radius: 14px;
    font-size: 13px;
    line-height: 1.5;
    word-wrap: break-word;
}
.aivr-msg-user {
    align-self: flex-end;
    background: #5B4FE9;
    color: #fff;
    border-bottom-right-radius: 2px;
}
.aivr-msg-ai {
    align-self: flex-start;
    background: #ffffff;
    color: #1C1930;
    border: 1px solid #E7E5F3;
    border-bottom-left-radius: 2px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.04);
}
.aivr-msg-ai strong { color: #5B4FE9; }

.aivr-chatbox-footer {
    padding: 10px;
    background: #fff;
    border-top: 1px solid #E7E5F3;
    display: flex;
    gap: 8px;
}
.aivr-chatbox-input {
    flex: 1;
    border: 1px solid #E7E5F3;
    border-radius: 20px;
    padding: 8px 14px;
    font-size: 13px;
    outline: none;
}
.aivr-chatbox-input:focus { border-color: #5B4FE9; }
.aivr-chatbox-send {
    background: #5B4FE9;
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}
CSS;
}

// Tải thư viện, file JS và truyền biến AJAX
function ai_voice_reader_enqueue_scripts() {
    if ( ! is_single() ) return;

    wp_enqueue_script( 'responsive-voice', 'https://code.responsivevoice.org/responsivevoice.js', array(), null, true );
    wp_enqueue_script( 'ai-voice-reader-js', plugin_dir_url( __FILE__ ) . 'reader-script.js', array( 'responsive-voice', 'jquery' ), '6.2', true );

    // Truyền biến AJAX bảo mật sang Javascript để Chatbox hoạt động
    wp_localize_script( 'ai-voice-reader-js', 'aivr_ajax', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'aivr_chat_nonce' )
    ));

    wp_register_style( 'ai-voice-reader-style', false, array(), '6.2' );
    wp_enqueue_style( 'ai-voice-reader-style' );
    wp_add_inline_style( 'ai-voice-reader-style', ai_voice_reader_css() );
}
add_action( 'wp_enqueue_scripts', 'ai_voice_reader_enqueue_scripts' );

// Tự động chèn Giao diện Đọc bài viết & Chatbox AI bôi đen vào bài viết
function ai_voice_reader_auto_insert( $content ) {
    if ( is_single() && is_main_query() ) {
        $text_to_read = wp_strip_all_tags( $content );

        ob_start();
        ?>
        <div class="aivr-widget">

            <!-- Nội dung ẩn để JS lấy text đọc bài -->
            <div id="ai-post-content" style="display: none;" aria-hidden="true"><?php echo esc_textarea( $text_to_read ); ?></div>

            <!-- Icon Nổi Đọc Bài -->
            <button type="button" id="ai-float-btn" class="aivr-fab" aria-label="Mở trợ lý đọc bài" aria-expanded="false">
                <span class="aivr-fab__icon aivr-fab__icon--idle">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18v-6a9 9 0 0 1 18 0v6"></path><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path></svg>
                </span>
                <span class="aivr-fab__icon aivr-fab__icon--close">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </span>
            </button>

            <!-- Bảng điều khiển Đọc bài nổi -->
            <div id="ai-float-panel" class="aivr-panel" role="dialog" aria-label="Trợ lý đọc bài">
                <div class="aivr-panel__head">
                    <div class="aivr-panel__title">
                        <span class="aivr-panel__dot"></span>
                        Trợ lý đọc bài
                    </div>
                    <button type="button" id="btn-close-panel" class="aivr-icon-btn" aria-label="Đóng bảng điều khiển">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                    </button>
                </div>

                <div class="aivr-status" id="ai-status-line">
                    <span class="aivr-eq" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
                    <span id="ai-status-text">Sẵn sàng đọc bài viết cho bạn.</span>
                </div>

                <button type="button" id="btn-play" class="aivr-primary-btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                    <span>Đọc bài viết</span>
                </button>

                <div class="aivr-controls">
                    <button type="button" id="btn-pause" class="aivr-chip">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><rect x="6" y="5" width="4" height="14"></rect><rect x="14" y="5" width="4" height="14"></rect></svg>
                        <span>Dừng</span>
                    </button>
                    <button type="button" id="btn-resume" class="aivr-chip">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M8 5v14l11-7z"></path></svg>
                        <span>Tiếp</span>
                    </button>
                    <button type="button" id="btn-stop" class="aivr-chip aivr-chip--danger">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><rect x="6" y="6" width="12" height="12" rx="2"></rect></svg>
                        <span>Hủy</span>
                    </button>
                </div>
            </div>

            <!-- Icon Nổi Khi Bôi Đen Chữ -->
            <button id="aivr-select-ai-btn" type="button">
                ✨ Hỏi AI từ này
            </button>

            <!-- Khung Chatbox AI Tra Cứu -->
            <div id="aivr-chatbox" class="aivr-chatbox">
                <div class="aivr-chatbox-header">
                    <span>🤖 Trợ Lý Trực Tuyến AI</span>
                    <button type="button" id="aivr-chatbox-close" class="aivr-chatbox-close">&times;</button>
                </div>
                <div id="aivr-chatbox-body" class="aivr-chatbox-body">
                    <div class="aivr-msg aivr-msg-ai">
                        Xin chào! Hãy bôi đen cụm từ bạn chưa hiểu trong bài viết hoặc nhập câu hỏi bên dưới, tôi sẽ giải đáp giúp bạn!
                    </div>
                </div>
                <div class="aivr-chatbox-footer">
                    <input type="text" id="aivr-chatbox-input" class="aivr-chatbox-input" placeholder="Nhập câu hỏi của bạn..." />
                    <button type="button" id="aivr-chatbox-send" class="aivr-chatbox-send">➤</button>
                </div>
            </div>

        </div>
        <?php
        $ui = ob_get_clean();
        return $content . $ui;
    }
    return $content;
}
add_filter( 'the_content', 'ai_voice_reader_auto_insert' );

// -----------------------------------------------------------------------------
// 5. XỬ LÝ GỌI API GOOGLE AI STUDIO (GEMINI 3.6 FLASH) QUA AJAX
// -----------------------------------------------------------------------------
function aivr_handle_gemini_chat() {
    check_ajax_referer( 'aivr_chat_nonce', 'security' );

    $user_message  = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $selected_text = isset($_POST['selected_text']) ? sanitize_text_field($_POST['selected_text']) : '';

    $api_key = get_option( 'aivr_gemini_api_key', '' );
    if ( empty( $api_key ) ) {
        wp_send_json_error( 'Chưa cấu hình Google AI Studio API Key trong Admin Menu.' );
    }

    $prompt = "";
    if ( ! empty( $selected_text ) ) {
        $prompt .= "Người đọc đang thắc mắc về từ/cụm từ sau trong bài viết: \"{$selected_text}\".\n";
    }
    $prompt .= "Câu hỏi: {$user_message}\n";
    $prompt .= "Hãy giải thích ngắn gọn, súc tích bằng tiếng Việt.";

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=' . $api_key;

    $body = array(
        'contents' => array(
            array(
                'parts' => array(
                    array( 'text' => $prompt )
                )
            )
        )
    );

    $response = wp_remote_post( $url, array(
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => json_encode( $body ),
        'timeout' => 25
    ));

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Lỗi kết nối Server AI: ' . $response->get_error_message() );
    }

    $response_body = wp_remote_retrieve_body( $response );
    $data = json_decode( $response_body, true );

    if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
        $ai_reply = $data['candidates'][0]['content']['parts'][0]['text'];
        wp_send_json_success( $ai_reply );
    } else {
        $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'Không nhận được phản hồi hợp lệ từ AI.';
        wp_send_json_error( 'Lỗi từ Google API: ' . $error_message );
    }
}
add_action( 'wp_ajax_aivr_gemini_chat', 'aivr_handle_gemini_chat' );
add_action( 'wp_ajax_nopriv_aivr_gemini_chat', 'aivr_handle_gemini_chat' );