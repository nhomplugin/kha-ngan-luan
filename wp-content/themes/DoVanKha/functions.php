<?php
/**
 * Theme Functions: DoVanKha
 * Sinh viên: Đỗ Văn Kha
 */

// Ngăn truy cập trực tiếp
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================
   YÊU CẦU 3: KHAI BÁO CÁC HẰNG SỐ TRONG THEME
   ========================================================== */
define( 'MYTHEME_VERSION', '1.0' );
define( 'MYTHEME_AUTHOR', 'Đỗ Văn Kha' ); // Hoặc 'Nguyễn Văn A' theo ví dụ đề bài
define( 'MYTHEME_CONTENT_WIDTH', 900 );

/* ==========================================================
   YÊU CẦU 4: THIẾT LẬP ĐỘ RỘNG NỘI DUNG 900PX
   ========================================================== */
if ( ! isset( $content_width ) ) {
    $content_width = MYTHEME_CONTENT_WIDTH;
}

/* ==========================================================
   THIẾT LẬP THEME & HỖ TRỢ NỀN TÙY BIẾN (YÊU CẦU 1, 5)
   ========================================================== */
function dovankha_setup() {
    // Yêu cầu 5: Nạp gói ngôn ngữ từ thư mục /languages
    load_theme_textdomain( 'dovankha', get_template_directory() . '/languages' );

    // Hỗ trợ title tag và thumbnail
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );

    // Tự động chèn liên kết RSS Feed vào phần <head> (Theo Slide 59 & 60)
    add_theme_support( 'automatic-feed-links' );

    // Yêu cầu 1: Thiết lập nền mặc định và hỗ trợ đổi màu/ảnh nền trong Tùy biến
    add_theme_support( 'custom-background', array(
        'default-color' => 'f0f0f0',
        'default-image' => '',
    ) );
}
add_action( 'after_setup_theme', 'dovankha_setup' );

// Nạp file style.css
function dovankha_scripts() {
    wp_enqueue_style( 'dovankha-style', get_stylesheet_uri(), array(), MYTHEME_VERSION );
}
add_action( 'wp_enqueue_scripts', 'dovankha_scripts' );

/* ==========================================================
   YÊU CẦU 2: TÙY CHỌN MÀU CHỮ VÀ FONT CHỮ TRONG CUSTOMIZER
   ========================================================== */
function dovankha_customize_register( $wp_customize ) {
    // 1. Tùy chọn Màu chữ
    $wp_customize->add_setting( 'dovankha_text_color', array(
        'default'           => '#333333',
        'sanitize_callback' => 'sanitize_hex_color',
    ) );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'dovankha_text_color', array(
        'label'    => 'Màu chữ nội dung',
        'section'  => 'colors',
        'settings' => 'dovankha_text_color',
    ) ) );

    // 2. Tùy chọn Font chữ
    $wp_customize->add_section( 'dovankha_font_section', array(
        'title'    => 'Tùy chọn Font chữ',
        'priority' => 30,
    ) );
    $wp_customize->add_setting( 'dovankha_font_choice', array(
        'default'           => 'Arial, sans-serif',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'dovankha_font_choice', array(
        'label'    => 'Chọn Font chữ',
        'section'  => 'dovankha_font_section',
        'type'     => 'select',
        'choices'  => array(
            'Arial, sans-serif'           => 'Arial',
            'Times New Roman, serif'      => 'Times New Roman',
            'Tahoma, sans-serif'          => 'Tahoma',
            'Verdana, sans-serif'         => 'Verdana',
            'Georgia, serif'              => 'Georgia',
        ),
    ) );
}
add_action( 'customize_register', 'dovankha_customize_register' );

// Xuất CSS tùy biến ra thẻ head
function dovankha_custom_css() {
    $color = get_theme_mod( 'dovankha_text_color', '#333333' );
    $font  = get_theme_mod( 'dovankha_font_choice', 'Arial, sans-serif' );
    ?>
    <style type="text/css">
        body, p, .post-excerpt {
            color: <?php echo esc_attr( $color ); ?>;
            font-family: <?php echo esc_attr( $font ); ?>;
        }
        .container {
            max-width: <?php echo MYTHEME_CONTENT_WIDTH; ?>px;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'dovankha_custom_css' );

/* ==========================================================
   BỘ LỌC CHUYỂN ĐỔI NGÔN NGỮ ĐỂ KIỂM THỬ (en_US / vi_VN)
   ========================================================== */
function dovankha_change_locale( $locale ) {
    if ( isset( $_GET['lang'] ) ) {
        if ( $_GET['lang'] === 'en' || $_GET['lang'] === 'en_US' ) {
            return 'en_US';
        }
        if ( $_GET['lang'] === 'vi' || $_GET['lang'] === 'vi_VN' ) {
            return 'vi_VN';
        }
    }
    return $locale;
}
add_filter( 'locale', 'dovankha_change_locale' );
add_filter( 'pre_determine_locale', 'dovankha_change_locale' );
