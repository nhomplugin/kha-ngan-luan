<?php get_header(); ?>
<p><?php _e('This is the home page content.', 'mytheme'); ?></p>

<!-- Thử nghiệm hàm _n() dịch số ít / số nhiều -->
<div style="margin-top:20px; padding:15px; border:1px solid #ccc; border-radius:6px; background:#f9f9f9;">
    <h3><?php _e('Plural Translation Demo (_n):', 'mytheme'); ?></h3>
    <p>
        <strong>1 lượt bình luận: </strong>
        <?php
        $count = 1;
        printf( _n( '%s comment', '%s comments', $count, 'mytheme' ), $count );
        ?>
    </p>
    <p>
        <strong>5 lượt bình luận: </strong>
        <?php
        $count = 5;
        printf( _n( '%s comment', '%s comments', $count, 'mytheme' ), $count );
        ?>
    </p>
</div>
