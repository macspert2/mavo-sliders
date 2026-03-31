<?php
/**
 * Plugin Name: Mavo Sliders
 * Plugin URI:  https://www.mamanvoyage.com/
 * Description: Lightweight header and hero sliders for Maman Voyage, replacing Smart Slider 3.
 * Version:     1.0.0
 * Author:      Maman Voyage
 * License:     GPL-2.0-or-later
 * Text Domain: mavo-sliders
 */

defined( 'ABSPATH' ) || exit;

define( 'MAVO_SLIDERS_VERSION', '1.0.0' );
define( 'MAVO_SLIDERS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'MAVO_SLIDERS_URL',     plugin_dir_url( __FILE__ ) );

require_once MAVO_SLIDERS_DIR . 'includes/class-header-slider.php';
require_once MAVO_SLIDERS_DIR . 'includes/class-hero-slider.php';

/* ── Assets ──────────────────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', static function () {
	wp_enqueue_style(
		'mavo-sliders',
		MAVO_SLIDERS_URL . 'assets/css/mavo-sliders.css',
		[],
		MAVO_SLIDERS_VERSION
	);
	wp_enqueue_script(
		'mavo-sliders',
		MAVO_SLIDERS_URL . 'assets/js/mavo-sliders.js',
		[],
		MAVO_SLIDERS_VERSION,
		true   // load in footer
	);
} );

/* ── Preload first hero image on front page ──────────────────── */
add_action( 'wp_head', static function () {
	if ( ! is_front_page() ) {
		return;
	}
	$url = content_url( 'uploads/2026/03/3verres1bib_banner2.webp' );
	echo '<link rel="preload" as="image" href="' . esc_url( $url ) . '" fetchpriority="high">' . "\n";
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
