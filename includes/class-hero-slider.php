<?php
defined( 'ABSPATH' ) || exit;

class Mavo_Hero_Slider {

	// Full-width logo used as hero slide 1 background
	private const LOGO_FULL = 'uploads/2026/03/3verres1bib_banner2.webp';
	// Smaller logo variant for srcset
	private const LOGO_360  = 'uploads/2026/03/3verres1bib_banner2-360x.webp';

	// Hero images fill the full viewport width at every breakpoint
	private const IMG_SIZES = '100vw';

	public static function render(): string {
		$home_url  = mavo_home_url();
		$logo_full = content_url( self::LOGO_FULL );
		$logo_360  = content_url( self::LOGO_360 );

		// 4 random published posts; suppress_filters=false lets Polylang
		// restrict results to the current language automatically.
		$posts = get_posts( [
			'numberposts'      => 4,
			'post_status'      => 'publish',
            'category'		   => 1752,
			'orderby'          => 'rand',
			'suppress_filters' => false,
		] );

		ob_start();
		?>
		<div class="mavo-slider mavo-slider--hero" data-interval="5000"
		     role="region" aria-label="Maman Voyage">
			<div class="mavo-slider__track">

				<!-- Slide 1: Logo / Homepage -->
				<div class="mavo-slider__slide">
					<a href="<?php echo esc_url( $home_url ); ?>" class="mavo-slide__link">
						<img class="mavo-slide__bg"
						     src="<?php echo esc_url( $logo_full ); ?>"
						     srcset="<?php echo esc_attr( $logo_360 ); ?> 360w,
						             <?php echo esc_attr( $logo_full ); ?> 960w"
						     sizes="<?php echo esc_attr( self::IMG_SIZES ); ?>"
						     alt=""
						     width="960" height="400"
						     loading="eager"
						     fetchpriority="high"
						     decoding="async">
						<div class="mavo-slide__overlay">
							<div class="mavo-slide__overlay-inner">
								<h1 class="mavo-slide__heading">Maman Voyage</h1>
							</div>
						</div>
					</a>
				</div>

				<?php foreach ( $posts as $post ) : ?>
					<?php
					$thumb_id  = (int) get_post_thumbnail_id( $post->ID );
					if ( ! $thumb_id ) {
						continue; // skip posts without a featured image
					}
					$thumb_src = wp_get_attachment_image_src( $thumb_id, 'full' );
					$thumb_url = $thumb_src[0] ?? '';
					if ( ! $thumb_url ) {
						continue;
					}
					$srcset   = wp_get_attachment_image_srcset( $thumb_id, 'full' ) ?: '';
					$post_url = get_permalink( $post->ID );
					$title    = get_the_title( $post->ID );
					?>
					<div class="mavo-slider__slide">
						<a href="<?php echo esc_url( $post_url ); ?>" class="mavo-slide__link">
							<img class="mavo-slide__bg"
							     src="<?php echo esc_url( $thumb_url ); ?>"
							     <?php if ( $srcset ) : ?>
							     srcset="<?php echo esc_attr( $srcset ); ?>"
							     sizes="<?php echo esc_attr( self::IMG_SIZES ); ?>"
							     <?php endif; ?>
							     alt="<?php echo esc_attr( $title ); ?>"
							     loading="lazy"
							     decoding="async">
							<div class="mavo-slide__overlay">
								<div class="mavo-slide__overlay-inner">
									<p class="mavo-slide__heading"><?php echo esc_html( $title ); ?></p>
								</div>
							</div>
						</a>
					</div>
				<?php endforeach; ?>

			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
