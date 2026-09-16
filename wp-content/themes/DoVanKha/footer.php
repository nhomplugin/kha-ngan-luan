    <!-- Footer hiển thị các hằng số theo Yêu cầu 3 -->
    <footer class="footer">
        <div class="constants-box">
            <h4><?php _e( 'Theme Constants Information (Footer)', 'dovankha' ); ?>:</h4>
            <p>
                <strong>MYTHEME_VERSION:</strong> 
                <code><?php echo esc_html( MYTHEME_VERSION ); ?></code> 
                (<?php _e( 'Phiên bản Theme', 'dovankha' ); ?>)
            </p>
            <p>
                <strong>MYTHEME_AUTHOR:</strong> 
                <code><?php echo esc_html( MYTHEME_AUTHOR ); ?></code> 
                (<?php _e( 'Tác giả phát triển', 'dovankha' ); ?>)
            </p>
            <p>
                <strong>MYTHEME_CONTENT_WIDTH:</strong> 
                <code><?php echo esc_html( MYTHEME_CONTENT_WIDTH ); ?>px</code> 
                (<?php _e( 'Giới hạn độ rộng nội dung', 'dovankha' ); ?>)
            </p>
        </div>

        <p class="copyright">
            &copy; <?php echo date( 'Y' ); ?> <?php bloginfo( 'name' ); ?>. 
            <?php _e( 'All rights reserved.', 'dovankha' ); ?> - 
            <?php _e( 'Theme developed by', 'dovankha' ); ?>: <strong><?php echo esc_html( MYTHEME_AUTHOR ); ?></strong>
        </p>
    </footer>
</div><!-- .container -->

<?php wp_footer(); ?>
</body>
</html>
