<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="container">
    <header class="header">
        <div class="site-title">
            <h1><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a></h1>
            <div class="site-desc"><?php bloginfo( 'description' ); ?></div>
        </div>
        <div class="lang-switch">
            <a href="<?php echo esc_url( add_query_arg( 'lang', 'vi_VN' ) ); ?>">Tiếng Việt</a> | 
            <a href="<?php echo esc_url( add_query_arg( 'lang', 'en_US' ) ); ?>">English</a>
        </div>
    </header>

    <!-- Khối chào mừng dịch theo Yêu cầu 5 -->
    <div class="welcome-box">
        <h2><?php _e( 'Welcome to my website!', 'dovankha' ); ?></h2>
    </div>
