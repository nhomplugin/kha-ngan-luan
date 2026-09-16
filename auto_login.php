<?php
require_once 'C:/xampp/htdocs/lttheme/wp-load.php';
$user = get_user_by('login', 'admin');
if ($user) {
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, true);
    do_action('wp_login', $user->user_login, $user);
    wp_redirect(admin_url('themes.php'));
    exit;
}
echo "Admin user not found";
