<?php
defined( 'ABSPATH' ) || exit;

class Mavo_Hero_Slider {

	// Full-width logo (WebP, slide 1 background)
	private const LOGO_FULL = 'uploads/2026/03/3verres1bib_banner2.webp';
	// Smaller logo variants for srcset
	private const LOGO_360  = 'uploads/2026/03/3verres1bib_banner2-360x.webp';
	private const LOGO_480  = 'uploads/2026/03/3verres1bib_banner2-480x200.webp';
	private const LOGO_640  = 'uploads/2026/03/3verres1bib_banner2-640x267.webp';

	/** "Voyages avec enfants" — the site's main travel category. */
	private const HERO_CATEGORY = 1752;

	/** The sizes hint for the slide-1 logo, shared with the preload. */
	public const LOGO_SIZES = '(max-width: 480px) 480px, (max-width: 640px) 640px, 960px';

	/**
	 * Absolute URLs for the slide-1 logo, keyed by the width each represents.
	 *
	 * Public because the homepage preloads this image, and a preload is worth
	 * something only while it names exactly what the page then requests. These
	 * four paths were written out a second time as literals in the preload in
	 * mavo-sliders.php; had the two ever drifted, the browser would have
	 * fetched the preloaded file, then fetched the real one, and warned that
	 * the preload went unused — a slower homepage than no preload at all, for
	 * the largest element on the page.
	 *
	 * @return array{full:string,360:string,480:string,640:string}
	 */
	public static function logo_sources(): array {
		return [
			'full' => content_url( self::LOGO_FULL ),
			'360'  => content_url( self::LOGO_360 ),
			'480'  => content_url( self::LOGO_480 ),
			'640'  => content_url( self::LOGO_640 ),
		];
	}

	/** The srcset string, as both the <img> and the preload need it. */
	public static function logo_srcset(): string {
		$src = self::logo_sources();

		return $src['360'] . ' 360w, ' . $src['480'] . ' 480w, ' . $src['640'] . ' 640w, ' . $src['full'] . ' 960w';
	}

	public static function render(): string {
		$home_url  = mavo_home_url();
    	$heading_tag = is_home() ? 'h1' : 'p';
		$logo      = self::logo_sources();
		$logo_full = $logo['full'];

		/*
		 * 4 random published posts. suppress_filters=false lets Polylang
		 * restrict results to the current language automatically.
		 *
		 * The two filters are deliberate, and both narrow the pool on purpose:
		 *
		 *   category 1752 — "voyages avec enfants", the main travel category.
		 *     Keeps anything non-travel out of the hero while leaving a pool
		 *     large enough for the rotation to stay varied.
		 *
		 *   _mavo_bpul_key != '' — only posts carrying a Booking pop-under
		 *     link. The hero is the most-seen placement on the site, so
		 *     spending it on posts that can earn is deliberate. A post with no
		 *     BPU can therefore never appear here, which is the intended
		 *     trade-off rather than an oversight.
		 *
		 * ORDER BY RAND() is the slow shape of this query, and page caching
		 * freezes the result until the cache regenerates — so in practice this
		 * runs rarely and every visitor of a cached page sees the same four.
		 */
		$posts = get_posts( [
			'numberposts'      => 4,
			'post_status'      => 'publish',
			'category'         => self::HERO_CATEGORY,
			'orderby'          => 'rand',
			'suppress_filters' => false,
			'date_query'       => [ [ 'after' => '2015-12-31', 'inclusive' => false ] ],
			'meta_query'       => [ [
				'key'     => '_mavo_bpul_key',
				'value'   => '',
				'compare' => '!=',
			] ],
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
						     srcset="<?php echo esc_attr( self::logo_srcset() ); ?>"
						     sizes="<?php echo esc_attr( self::LOGO_SIZES ); ?>"
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
					$excerpt  = get_the_excerpt( $post->ID );

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
							     alt="<?php echo esc_attr( $excerpt ); ?>">
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
	 * The filenames come from the attachment's own metadata wherever WordPress
	 * recorded an intermediate size at the width we want, because guessing them
	 * got this wrong twice on the live site:
	 *
	 *   rounding  wp_constrain_dimensions() ROUNDS, and this method truncated. A
	 *             960×679 photo yielded …-640x452 and …-480x339, both 404, where
	 *             the files WordPress wrote are …-640x453 and …-480x340. Since the
	 *             smallest entry is also used as src, such a slide did not paint.
	 *             mavo-img-srcset hit the same thing (commit "height round").
	 *
	 *   -rotated  an EXIF-rotated upload is stored as IMG_6585-rotated.jpeg, but
	 *             its intermediate sizes are named after the UN-rotated base, so
	 *             the file is IMG_6585-640x853.jpeg and never
	 *             IMG_6585-rotated-640x853.jpeg. Every portrait phone photo.
	 *
	 * Arithmetic remains the fallback for attachments whose metadata has no size
	 * at that width, now rounding and stripping a trailing -rotated. Anything the
	 * fallback produces is checked against the filesystem and dropped if absent,
	 * so a wrong guess costs one srcset entry instead of a broken image.
	 *
	 * Near-duplicate of Mavo_Img_Srcset::sized_webp(); the two plugins deploy
	 * separately, so there is nowhere safe to share it from yet.
	 *
	 * @return array  [ ['w'=>960,'webp'=>url,'h'=>int], [640…], [480…] ]
	 *                Ordered largest → smallest, missing entries omitted.
	 *                Empty on failure, which makes render() skip the slide.
	 */
	/**
	 * Intermediate sizes from attachment metadata, keyed by width.
	 *
	 * A width can be registered more than once — an uncropped size and a hard
	 * cropped one both 640 px wide, say — and a cropped thumbnail in a 100vw
	 * slide would be visibly wrong. The entry whose height is nearest the
	 * original's aspect ratio therefore wins, so a crop is only ever chosen when
	 * nothing else was recorded at that width.
	 *
	 * @return array<int,array{file:string,h:int}>
	 */
	private static function recorded_sizes( array $meta, int $orig_w, int $orig_h ): array {
		$out = [];

		if ( empty( $meta['sizes'] ) || ! is_array( $meta['sizes'] ) || $orig_w < 1 ) {
			return $out;
		}

		foreach ( $meta['sizes'] as $size ) {
			$w    = (int) ( $size['width'] ?? 0 );
			$h    = (int) ( $size['height'] ?? 0 );
			$name = (string) ( $size['file'] ?? '' );

			if ( $w < 1 || $h < 1 || $name === '' ) {
				continue;
			}

			$drift = abs( $h - ( $orig_h * $w / $orig_w ) );

			if ( ! isset( $out[ $w ] ) || $drift < $out[ $w ]['drift'] ) {
				$out[ $w ] = [ 'file' => $name, 'h' => $h, 'drift' => $drift ];
			}
		}

		foreach ( $out as $w => $entry ) {
			unset( $out[ $w ]['drift'] );
		}

		return $out;
	}

	/**
	 * Whether a derived URL resolves to a file in the uploads directory.
	 *
	 * A URL that cannot be mapped to a local path — a CDN, offloaded media, a
	 * rewritten domain — is reported present, so those installs keep exactly the
	 * behaviour they have now rather than losing every candidate.
	 */
	private static function webp_exists( string $url ): bool {
		static $cache = [];

		if ( isset( $cache[ $url ] ) ) {
			return $cache[ $url ];
		}

		$path = self::local_path( $url );

		return $cache[ $url ] = ( $path === null ) ? true : file_exists( $path );
	}

	/** Maps an uploads URL to its path on disk, or null if it is not one. */
	private static function local_path( string $url ): ?string {
		static $uploads = null;

		if ( $uploads === null ) {
			$uploads = wp_upload_dir();
		}

		$baseurl = $uploads['baseurl'] ?? '';
		$basedir = $uploads['basedir'] ?? '';

		if ( $baseurl === '' || $basedir === '' || ! empty( $uploads['error'] ) ) {
			return null;
		}

		// http/https and protocol-relative all name the same directory.
		foreach ( [ $baseurl, set_url_scheme( $baseurl, 'http' ), set_url_scheme( $baseurl, 'https' ), preg_replace( '#^https?:#', '', $baseurl ) ] as $prefix ) {
			if ( $prefix !== '' && str_starts_with( $url, $prefix ) ) {
				return $basedir . substr( $url, strlen( $prefix ) );
			}
		}

		return null;
	}

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

		// An EXIF-rotated original keeps the suffix; its intermediates do not.
		$base = preg_replace( '/-rotated$/', '', $name );

		$recorded = self::recorded_sizes( $meta, $orig_w, $orig_h );

		// The full size carries the whole slide: it is the 960w candidate and the
		// fallback src, so without it there is nothing safe to render.
		if ( ! self::webp_exists( $dir_url . $file . '.webp' ) ) {
			return [];
		}

		$sources = [];
		foreach ( [ 960, 640, 480 ] as $target_w ) {
			if ( ! $orig_w || $target_w >= $orig_w ) {
				// Original is at or below the target width — serve as-is (no upscaling)
				$sized_file = $file;
				$sized_h    = $orig_h;
			} elseif ( isset( $recorded[ $target_w ] ) ) {
				// What WordPress actually wrote, read rather than reconstructed.
				$sized_file = $recorded[ $target_w ]['file'];
				$sized_h    = $recorded[ $target_w ]['h'];
			} else {
				$sized_h    = (int) round( $orig_h * $target_w / $orig_w );
				$sized_file = "{$base}-{$target_w}x{$sized_h}.{$ext}";
			}

			$webp = $dir_url . $sized_file . '.webp';

			if ( ! self::webp_exists( $webp ) ) {
				continue;
			}

			$sources[] = [
				'w'    => $target_w,
				'h'    => $sized_h,
				'webp' => $webp,
			];
		}

		return $sources;
	}
}
