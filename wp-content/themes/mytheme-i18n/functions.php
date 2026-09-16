<?php
function mytheme_setup() {
    // Nạp text domain
    load_theme_textdomain( 'mytheme', get_template_directory() . '/languages' );

    // Một số hỗ trợ theme
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'mytheme_setup' );
