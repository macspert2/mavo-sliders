<?php
defined( 'ABSPATH' ) || exit;

class Mavo_Hero_Slider {

	// Full-width logo (WebP, slide 1 background)
	private const LOGO_FULL = 'uploads/2026/03/3verres1bib_banner2.webp';
	// Smaller logo variants for srcset
	private const LOGO_360  = 'uploads/2026/03/3verres1bib_banner2-360x.webp';
	private const LOGO_480  = 'uploads/2026/03/3verres1bib_banner2-480x200.webp';
	private const LOGO_640  = 'uploads/2026/03/3verres1bib_banner2-640x267.webp';

	public static function render(): string {
		$home_url  = mavo_home_url();
    	$heading_tag = is_home() ? 'h1' : 'p';
		$logo_full = content_url( self::LOGO_FULL );
		$logo_360  = content_url( self::LOGO_360 );
		$logo_480  = content_url( self::LOGO_480 );
		$logo_640  = content_url( self::LOGO_640 );

		// 4 random published posts; suppress_filters=false lets Polylang
		// restrict results to the current language automatically.
		$posts = get_posts( [
			'numberposts'      => 4,
			'post_status'      => 'publish',
			'category'         => 1752,
			'orderby'          => 'rand',
			'suppress_filters' => false,
			'date_query'       => [ [ 'after' => '2015-12-31', 'inclusive' => false ] ],
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
						             <?php echo esc_attr( $logo_480 ); ?> 480w,
						             <?php echo esc_attr( $logo_640 ); ?> 640w,
						             <?php echo esc_attr( $logo_full ); ?> 960w"
						     sizes="(max-width: 480px) 480px, (max-width: 640px) 640px, 960px"
						     loading="eager"
						     fetchpriority="high"
						     decoding="async"
						     data-swift-skip-lazy="true"
						     width="960" height="400"
						     alt="Maman Voyage logo">
						<div class="mavo-slide__overlay">
							<div class="mavo-slide__overlay-inner">
								<<?php echo $heading_tag; ?> class="mavo-slide__heading">Maman Voyage</<?php echo $heading_tag; ?>>
							</div>
						</div>
					</a>
				</div>

				<?php foreach ( $posts as $post ) : ?>
					<?php
					$thumb_id = (int) get_post_thumbnail_id( $post->ID );
					if ( ! $thumb_id ) {
						continue; // skip posts without a featured image
					}
					$sources = self::webp_sources( $thumb_id );
					if ( ! $sources ) {
						continue;
					}
					$post_url = get_permalink( $post->ID );
					$title    = get_the_title( $post->ID );

					// Build srcset attribute string (ordered 960w → 640w → 480w, WebP only)
					$srcset_webp = implode( ', ', array_map(
						static function ( $s ) { return esc_attr( $s['webp'] ) . ' ' . $s['w'] . 'w'; },
						$sources
					) );
					$smallest = end( $sources );                  // 480w entry
					$src_webp = esc_url( $smallest['webp'] );     // smallest (480w) as src
					$img_w    = $smallest['w'];                    // 480
					$img_h    = $smallest['h'];                    // proportional height at 480w
					?>
					<div class="mavo-slider__slide">
						<a href="<?php echo esc_url( $post_url ); ?>" class="mavo-slide__link">
							<img class="mavo-slide__bg"
							     src="<?php echo $src_webp; ?>"
							     srcset="<?php echo $srcset_webp; ?>"
							     sizes="100vw"
							     loading="lazy"
							     decoding="async"
								 data-swift-skip-lazy="true"
							     width="<?php echo $img_w; ?>"
							     height="<?php echo $img_h; ?>"
							     alt="<?php echo esc_attr( $title ); ?>">
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

	/**
	 * Returns WebP source URLs at three widths (960, 640, 480 px).
	 *
	 * Filenames are constructed directly from the original's pixel dimensions,
	 * using WordPress's own (int) truncation to match the names WP writes to disk:
	 *   name-640x480.jpg.webp
	 *
	 * @return array  [ ['w'=>960,'webp'=>url,'h'=>int], [640…], [480…] ]
	 *                Ordered largest → smallest. Empty on failure.
	 */
	private static function webp_sources( int $thumb_id ): array {
		$full_url = wp_get_attachment_url( $thumb_id );
		if ( ! $full_url ) {
			return [];
		}

		$meta   = wp_get_attachment_metadata( $thumb_id );
		if ( ! is_array( $meta ) ) {
			return [];
		}
		$orig_w = (int) ( $meta['width']  ?? 0 );
		$orig_h = (int) ( $meta['height'] ?? 0 );

		$dir_url = trailingslashit( dirname( $full_url ) );
		$file    = basename( $full_url );                  // e.g. IMG_2831.jpg
		$ext     = pathinfo( $file, PATHINFO_EXTENSION ); // jpg / jpeg
		$name    = pathinfo( $file, PATHINFO_FILENAME );  // IMG_2831

		$sources = [];
		foreach ( [ 960, 640, 480 ] as $target_w ) {
			if ( ! $orig_w || $target_w >= $orig_w ) {
				// Original is at or below the target width — serve as-is (no upscaling)
				$sized_file = $file;
				$sized_h    = $orig_h;
			} else {
				// Construct the WordPress-standard resized filename.
				// WordPress uses (int) truncation in wp_constrain_dimensions(),
				// e.g. a 960×720 image at 640 w → 640×480  (720 * 640/960 = 480.0)
				//                              at 480 w → 480×360  (720 * 480/960 = 360.0)
				$sized_h    = (int) ( $orig_h * $target_w / $orig_w );
				$sized_file = "{$name}-{$target_w}x{$sized_h}.{$ext}";
			}

			$sources[] = [
				'w'    => $target_w,
				'h'    => $sized_h,
				'webp' => $dir_url . $sized_file . '.webp',
			];
		}

		return $sources;
	}
}
