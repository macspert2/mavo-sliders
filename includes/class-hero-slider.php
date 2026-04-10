<?php
defined( 'ABSPATH' ) || exit;

class Mavo_Hero_Slider {

	// Full-width logo (WebP, slide 1 background)
	private const LOGO_FULL = 'uploads/2026/03/3verres1bib_banner2.webp';
	// Smaller logo variant for srcset
	private const LOGO_360  = 'uploads/2026/03/3verres1bib_banner2-360x.webp';

	public static function render(): string {
		$home_url  = mavo_home_url();
		$logo_full = content_url( self::LOGO_FULL );
		$logo_360  = content_url( self::LOGO_360 );

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
						<!-- Logo is natively WebP; no JPEG fallback needed -->
						<picture class="mavo-slide__pic">
							<source type="image/webp"
							        srcset="<?php echo esc_attr( $logo_360 ); ?> 360w,
							                <?php echo esc_attr( $logo_full ); ?> 960w"
							        sizes="100vw">
							<img class="mavo-slide__bg"
							     src="<?php echo esc_url( $logo_full ); ?>"
							     alt=""
							     width="960" height="400"
							     loading="eager"
							     fetchpriority="high"
							     decoding="async">
						</picture>
						<div class="mavo-slide__overlay">
							<div class="mavo-slide__overlay-inner">
								<h1 class="mavo-slide__heading">Maman Voyage</h1>
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
					$sources = self::picture_sources( $thumb_id );
					if ( ! $sources ) {
						continue;
					}
					$post_url = get_permalink( $post->ID );
					$title    = get_the_title( $post->ID );

					// Build srcset attribute strings (ordered 960w → 640w → 480w)
					$srcset_webp = implode( ', ', array_map(
						static fn( $s ) => esc_attr( $s['webp'] ) . ' ' . $s['w'] . 'w',
						$sources
					) );
					$srcset_jpeg = implode( ', ', array_map(
						static fn( $s ) => esc_attr( $s['jpeg'] ) . ' ' . $s['w'] . 'w',
						$sources
					) );
					$src_jpeg = esc_url( end( $sources )['jpeg'] ); // smallest (480w) as <img src> fallback
					?>
					<div class="mavo-slider__slide">
						<a href="<?php echo esc_url( $post_url ); ?>" class="mavo-slide__link">
							<picture class="mavo-slide__pic">
								<source type="image/webp"
								        srcset="<?php echo $srcset_webp; ?>"
								        sizes="100vw">
								<img class="mavo-slide__bg"
								     src="<?php echo $src_jpeg; ?>"
								     srcset="<?php echo $srcset_jpeg; ?>"
								     sizes="100vw"
								     alt="<?php echo esc_attr( $title ); ?>"
								     loading="lazy"
								     decoding="async">
							</picture>
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
	 * Returns WebP + JPEG source URLs at three widths (960, 640, 480 px).
	 *
	 * Filenames are constructed directly from the original's pixel dimensions,
	 * using WordPress's own (int) truncation to match the names WP writes to disk:
	 *   name-640x480.jpeg  →  name-640x480.jpeg.webp
	 *
	 * The previous metadata-lookup approach broke for images uploaded before
	 * WordPress 4.4 (no medium_large entry in DB) — those returned the full-
	 * resolution file for every srcset slot.
	 *
	 * @return array  [ ['w'=>960,'jpeg'=>url,'webp'=>url], [640…], [480…] ]
	 *                Ordered largest → smallest. Empty on failure.
	 */
	private static function picture_sources( int $thumb_id ): array {
		$full_url = wp_get_attachment_url( $thumb_id );
		if ( ! $full_url ) {
			return [];
		}

		$meta   = wp_get_attachment_metadata( $thumb_id );
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
			} else {
				// Construct the WordPress-standard resized filename.
				// WordPress uses (int) truncation in wp_constrain_dimensions(),
				// e.g. a 960×720 image at 640 w → 640×480  (720 * 640/960 = 480.0)
				//                              at 480 w → 480×360  (720 * 480/960 = 360.0)
				$target_h   = (int) ( $orig_h * $target_w / $orig_w );
				$sized_file = "{$name}-{$target_w}x{$target_h}.{$ext}";
			}

			$sources[] = [
				'w'    => $target_w,
				'jpeg' => $dir_url . $sized_file,
				'webp' => $dir_url . $sized_file . '.webp',
			];
		}

		return $sources;
	}
}
