<?php
/**
 * File single.php hiển thị chi tiết một bài viết
 * Theme: DoVanKha
 */
get_header();
?>

<div class="content-area">
    <?php if ( have_posts() ) : ?>
        <?php while ( have_posts() ) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('single-post-item'); ?>>
                <h1 class="single-post-title"><?php the_title(); ?></h1>
                
                <div class="post-meta" style="margin-bottom: 20px; color: #666; font-size: 14px;">
                    <span><?php _e( 'Posted on', 'dovankha' ); ?> <?php echo get_the_date(); ?></span> | 
                    <span><?php _e( 'By', 'dovankha' ); ?> <?php the_author(); ?></span> |
                    <span><?php comments_number( '0 ' . __( 'comments', 'dovankha' ), '1 ' . __( 'comment', 'dovankha' ), '% ' . __( 'comments', 'dovankha' ) ); ?></span>
                </div>

                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="post-thumbnail" style="margin-bottom: 24px; text-align: center;">
                        <?php the_post_thumbnail('large', ['style' => 'max-width: 100%; height: auto; border-radius: 8px;']); ?>
                    </div>
                <?php endif; ?>

                <div class="entry-content">
                    <?php 
                    // the_content() sẽ tự động kích hoạt bộ đọc DVK Text-to-Speech Player
                    the_content(); 
                    ?>
                </div>

                <div class="post-navigation" style="margin-top: 36px; padding-top: 20px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between;">
                    <div class="nav-previous"><?php previous_post_link( '&laquo; %link' ); ?></div>
                    <div class="nav-next"><?php next_post_link( '%link &raquo;' ); ?></div>
                </div>
            </article>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<?php
get_footer();
