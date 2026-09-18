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

					$image = self::slide_image( $thumb_id, get_the_excerpt( $post->ID ) );
					if ( $image === '' ) {
						continue; // attachment gone or unreadable
					}
					?>
					<div class="mavo-slider__slide">
						<a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" class="mavo-slide__link">
							<?php echo $image; // escaped by wp_get_attachment_image() ?>
							<div class="mavo-slide__overlay">
								<div class="mavo-slide__overlay-inner">
									<p class="mavo-slide__heading"><?php echo esc_html( get_the_title( $post->ID ) ); ?></p>
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
	 * The <img> for one hero slide.
	 *
	 * Everything about which files exist and at what size now comes from
	 * WordPress. This method used to derive the 960/640/480 filenames itself and
	 * append .webp to each, which produced two kinds of URL that 404: an
	 * EXIF-rotated upload stores its intermediates under the un-rotated base, and
	 * heights computed here could land a pixel off the ones WordPress used in the
	 * filename. Since the hero used the smallest candidate as src, such a slide
	 * simply did not paint.
	 *
	 * wp_get_attachment_image() reads the real filenames from the attachment
	 * metadata, so neither mistake is possible any more.
	 *
	 * The WebP swap is not done here either. Mavo Img Srcset filters
	 * wp_calculate_image_srcset and wp_get_attachment_image_src, so the sidecars
	 * arrive through those — no call between the two plugins, and no copy of the
	 * lookup living in both. If that plugin is ever switched off the hero keeps
	 * working and serves JPEGs.
	 *
	 * sizes is forced to 100vw because the hero spans the viewport; core would
	 * otherwise size it against the content column.
	 */
	private static function slide_image( int $thumb_id, string $excerpt ): string {
		return (string) wp_get_attachment_image(
			$thumb_id,
			'full',
			false,
			[
				'class'    => 'mavo-slide__bg',
				'sizes'    => '100vw',
				'alt'      => $excerpt,
				'loading'  => 'lazy',
				'decoding' => 'async',
			]
		);
	}
}
