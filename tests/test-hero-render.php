<?php
/**
 * The hero slider's rendered structure.
 *
 * webp_sources() and its metadata/filesystem helpers are gone: the slide image
 * now comes from wp_get_attachment_image(), and the .webp swap arrives through
 * the wp_calculate_image_srcset / wp_get_attachment_image_src filters that Mavo
 * Img Srcset registers. So there is no filename logic left here to test — what
 * is worth pinning is the slide markup, and that a post which cannot produce an
 * image is skipped rather than rendered broken.
 */
define( 'ABSPATH', true );

$GLOBALS['THUMBS']  = [];   // post id => attachment id
$GLOBALS['IMAGES']  = [];   // attachment id => html, or '' for "cannot render"
$GLOBALS['ATTR']    = [];   // the $attr wp_get_attachment_image() was called with

function add_action( ...$a ) {}
function add_shortcode( ...$a ) {}
function content_url( $p = '' ) { return 'https://example.com/wp-content/' . $p; }
function esc_url( $v )  { return $v; }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function is_home() { return false; }
function mavo_home_url() { return 'https://example.com/'; }
function mavo_current_language() { return 'fr'; }
function get_post_thumbnail_id( $id ) { return $GLOBALS['THUMBS'][ $id ] ?? 0; }
function get_permalink( $id )   { return "https://example.com/post-$id/"; }
function get_the_title( $id )   { return "Title $id"; }
function get_the_excerpt( $id ) { return "Excerpt $id"; }
function get_posts( $args ) { return $GLOBALS['POSTS'] ?? []; }

function wp_get_attachment_image( $id, $size, $icon, $attr ) {
	$GLOBALS['ATTR'][ $id ] = $attr;

	return $GLOBALS['IMAGES'][ $id ] ?? '';
}

require_once __DIR__ . '/../includes/class-hero-slider.php';

$T = 0; $F = 0;
function ok( bool $c, string $l ) { global $T,$F; $T++; echo $c ? "  ok   $l\n" : "  FAIL $l\n"; if(!$c){$F++;} }
function is_same( $e, $a, string $l ) {
	global $T,$F; $T++;
	if ( $e === $a ) { echo "  ok   $l\n"; return; }
	$F++; echo "  FAIL $l\n       expected " . var_export($e,true) . "\n       actual   " . var_export($a,true) . "\n";
}

function post( int $id ): object { return (object) [ 'ID' => $id ]; }
function render(): string { return preg_replace( '/\s+/', ' ', Mavo_Hero_Slider::render() ); }

/* Three posts: one fine, one with no featured image, one whose attachment is gone. */
$GLOBALS['POSTS']  = [ post( 1 ), post( 2 ), post( 3 ) ];
$GLOBALS['THUMBS'] = [ 1 => 11, 3 => 33 ];              // post 2 has none
$GLOBALS['IMAGES'] = [ 11 => '<img class="mavo-slide__bg" src="a.jpg.webp">', 33 => '' ];

$html = render();

is_same( 2, substr_count( $html, 'mavo-slider__slide' ),
	'one logo slide plus one usable post slide — the other two are skipped' );
ok( str_contains( $html, 'href="https://example.com/post-1/"' ), 'the usable slide links to its post' );
ok( ! str_contains( $html, 'post-2' ), 'a post with no featured image is skipped' );
ok( ! str_contains( $html, 'post-3' ), 'a post whose attachment cannot render is skipped, not rendered broken' );
ok( str_contains( $html, '<p class="mavo-slide__heading">Title 1</p>' ), 'the title overlay is rendered' );
ok( str_contains( $html, '<img class="mavo-slide__bg" src="a.jpg.webp">' ), 'the image comes through verbatim from core' );

/* The attributes handed to core */
is_same( '100vw', $GLOBALS['ATTR'][11]['sizes'],
	'sizes is forced to 100vw — the hero spans the viewport, not the content column' );
is_same( 'mavo-slide__bg', $GLOBALS['ATTR'][11]['class'], 'the slide class is passed through' );
is_same( 'Excerpt 1', $GLOBALS['ATTR'][11]['alt'], 'the excerpt becomes the alt text' );
is_same( 'lazy', $GLOBALS['ATTR'][11]['loading'], 'post slides stay lazy — only the logo is eager' );

/* No filename derivation may come back.
   Comments are stripped first: the docblocks quote the old broken filenames on
   purpose, and LOGO_640 legitimately contains "-640x" — it is a real file that
   was placed by hand, not a name this class works out. */
$src  = file_get_contents( __DIR__ . '/../includes/class-hero-slider.php' );
$code = '';
foreach ( token_get_all( $src ) as $t ) {
	$code .= is_array( $t ) ? ( in_array( $t[0], [ T_COMMENT, T_DOC_COMMENT ], true ) ? ' ' : $t[1] ) : $t;
}

foreach ( [
	'webp_sources'   => 'the filename builder',
	'sized_file'     => 'its constructed name',
	'round('         => 'the height arithmetic that was a pixel out',
	'pathinfo'       => 'taking a filename apart',
	'file_exists'    => 'checking derived paths — core knows what exists',
	'wp_get_attachment_metadata' => 'reading sizes by hand',
	'swift'          => 'Swift Performance leftovers',
] as $gone => $what ) {
	ok( stripos( $code, $gone ) === false, "gone: $what" );
}

echo "\n$T assertions, $F failed\n";
exit( $F ? 1 : 0 );
