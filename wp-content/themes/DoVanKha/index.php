<?php
/**
 * File index.php hiển thị danh sách bài viết
 */
get_header();
?>

<div class="content-area">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <div class="post-item">
                <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                <div class="post-meta">
                    <span><?php _e( 'Posted on', 'dovankha' ); ?> <?php echo get_the_date(); ?></span> | 
                    <span><?php _e( 'By', 'dovankha' ); ?> <?php the_author(); ?></span>
                </div>
                <div class="post-excerpt">
                    <?php the_excerpt(); ?>
                </div>
                <!-- Nút Read more dịch theo Yêu cầu 5 -->
                <a href="<?php the_permalink(); ?>" class="btn-readmore">
                    <?php _e( 'Read more', 'dovankha' ); ?>
                </a>
            </div>
        <?php endwhile; ?>
    <?php else : ?>
        <div class="post-item">
            <h3><a href="#"><?php _e( 'Sample Post: Welcome to DoVanKha Theme', 'dovankha' ); ?></a></h3>
            <p><?php _e( 'This is a demonstration post showcasing the DoVanKha custom theme layout. The content width is strictly limited to 900px according to the MYTHEME_CONTENT_WIDTH constant. Background color, custom background image, typography, and text color can all be customized in the WordPress Customizer.', 'dovankha' ); ?></p>
            <br>
            <a href="#" class="btn-readmore">
                <?php _e( 'Read more', 'dovankha' ); ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<?php
get_footer();
