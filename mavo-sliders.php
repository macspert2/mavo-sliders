<?php
/**
 * Plugin Name: Mavo Sliders
 * Plugin URI:  https://www.mamanvoyage.com/
 * Description: Lightweight header and hero sliders for Maman Voyage, replacing Smart Slider 3.
 * Version:     1.0.2
 * Author:      Maman Voyage
 * License:     GPL-2.0-or-later
 * Text Domain: mavo-sliders
 */

defined( 'ABSPATH' ) || exit;

define( 'MAVO_SLIDERS_VERSION', '1.0.2' );
define( 'MAVO_SLIDERS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'MAVO_SLIDERS_URL',     plugin_dir_url( __FILE__ ) );

require_once MAVO_SLIDERS_DIR . 'includes/class-header-slider.php';
require_once MAVO_SLIDERS_DIR . 'includes/class-hero-slider.php';

/* ── Assets ──────────────────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', static function () {
	// filemtime, not the plugin version: editing an asset without bumping the
	// constant used to leave stale files in Autoptimize/Cloudflare.
	$css = MAVO_SLIDERS_DIR . 'assets/css/mavo-sliders.css';
	$js  = MAVO_SLIDERS_DIR . 'assets/js/mavo-sliders.js';

	wp_enqueue_style(
		'mavo-sliders',
		MAVO_SLIDERS_URL . 'assets/css/mavo-sliders.css',
		[],
		file_exists( $css ) ? filemtime( $css ) : MAVO_SLIDERS_VERSION
	);
	wp_enqueue_script(
		'mavo-sliders',
		MAVO_SLIDERS_URL . 'assets/js/mavo-sliders.js',
		[],
		file_exists( $js ) ? filemtime( $js ) : MAVO_SLIDERS_VERSION,
		true   // load in footer
	);
} );

/* ── Preload first hero image on front page ──────────────────── */
add_action( 'wp_head', static function () {
	if ( ! is_front_page() ) {
		return;
	}
	// Asked of the slider itself, never spelled out again here. A preload earns
	// its request only while it names exactly the image the page goes on to
	// request; these four paths existed here as literals as well as in
	// Mavo_Hero_Slider, and the cost of the two drifting is the LCP image
	// downloaded twice plus a "preloaded but not used" warning — slower than
	// having no preload at all. See Mavo_Hero_Slider::logo_sources().
	$logo = Mavo_Hero_Slider::logo_sources();

	echo '<link rel="preload" as="image"'
		. ' href="' . esc_url( $logo['full'] ) . '"'
		. ' imagesrcset="' . esc_attr( Mavo_Hero_Slider::logo_srcset() ) . '"'
		. ' imagesizes="' . esc_attr( Mavo_Hero_Slider::LOGO_SIZES ) . '"'
		. ' fetchpriority="high">' . "\n";

}, 1 );

/* ── Shortcodes ──────────────────────────────────────────────── */
add_shortcode( 'mavo_header_slider', static function () {
	return Mavo_Header_Slider::render();
} );

add_shortcode( 'mavo_hero_slider', static function () {
	return Mavo_Hero_Slider::render();
} );

/* ── Helpers ─────────────────────────────────────────────────── */

/**
 * Returns the home URL for the current language.
 * Supports Polylang; falls back to home_url('/').
 */
function mavo_home_url(): string {
	if ( function_exists( 'pll_home_url' ) ) {
		return pll_home_url();
	}
	return home_url( '/' );
}

/**
 * Returns the current language code (e.g. 'fr', 'en', 'de').
 * Supports Polylang; falls back to 'fr'.
 */
function mavo_current_language(): string {
	if ( function_exists( 'pll_current_language' ) ) {
		return pll_current_language() ?: 'fr';
	}
	return 'fr';
}
