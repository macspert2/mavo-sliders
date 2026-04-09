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
					$src_jpeg = esc_url( $sources[0]['jpeg'] ); // largest as <img src> fallback
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
	 * Returns WebP + JPEG source URLs at three widths (960, 640, 480 px)
	 * derived from the attachment's registered size metadata.
	 *
	 * Naming conventions assumed:
	 *   Original JPEG  : name.jpeg
	 *   Resized JPEG   : name-{w}x{h}.jpeg   (WordPress standard)
	 *   WebP of any    : {jpeg-filename}.webp  (appended extension)
	 *
	 * @return array  [ ['w'=>960,'jpeg'=>url,'webp'=>url], [640…], [480…] ]
	 *                Ordered largest → smallest. Empty on failure.
	 */
	private static function picture_sources( int $thumb_id ): array {
		$full_url = wp_get_attachment_url( $thumb_id );
		if ( ! $full_url ) {
			return [];
		}

		$dir_url = trailingslashit( dirname( $full_url ) );
		$meta    = wp_get_attachment_metadata( $thumb_id );

		// Map width (px) → filename for the original + every registered size
		$by_width = [];
		if ( ! empty( $meta['width'] ) ) {
			$by_width[ (int) $meta['width'] ] = basename( $full_url );
		}
		foreach ( $meta['sizes'] ?? [] as $size ) {
			if ( ! empty( $size['file'] ) && ! empty( $size['width'] ) ) {
				$by_width[ (int) $size['width'] ] = $size['file'];
			}
		}

		if ( ! $by_width ) {
			return [];
		}

		krsort( $by_width ); // largest → smallest

		$sources = [];
		foreach ( [ 960, 640, 480 ] as $target_w ) {
			// Walk largest → smallest; keep overwriting while width >= target.
			// After the loop $file is the smallest file still at least $target_w wide.
			$file = null;
			foreach ( $by_width as $w => $f ) {
				if ( $w >= $target_w ) {
					$file = $f;
				}
			}
			if ( ! $file ) {
				// Nothing wide enough — use the largest available
				reset( $by_width );
				$file = current( $by_width );
			}

			$sources[] = [
				'w'    => $target_w,
				'jpeg' => $dir_url . $file,
				'webp' => $dir_url . $file . '.webp',
			];
		}

		return $sources;
	}
}
