<?php
/**
 * Exercises Mavo_Hero_Slider::webp_sources() against a real temporary uploads
 * directory, so the file_exists() checks that decide which srcset candidates
 * survive are run for real rather than stubbed.
 *
 * The cases mirror what a sweep of the live site found in Sept 2026: EXIF-rotated
 * originals whose intermediates live under the un-rotated base, and heights that
 * truncation put one pixel away from the file WordPress actually wrote.
 */
define( 'ABSPATH', true );

$GLOBALS['UP'] = sys_get_temp_dir() . '/mavo-hero-test-' . getmypid();
@mkdir( $GLOBALS['UP'] . '/2026/09', 0777, true );

$GLOBALS['ATT'] = [];   // id => [ 'url' => …, 'meta' => … ]

function wp_get_attachment_url( $id )      { return $GLOBALS['ATT'][ $id ]['url']  ?? false; }
function wp_get_attachment_metadata( $id ) { return $GLOBALS['ATT'][ $id ]['meta'] ?? false; }
function trailingslashit( $s )             { return rtrim( $s, '/' ) . '/'; }
function set_url_scheme( $u, $s )          { return preg_replace( '#^https?:#', $s . ':', $u ); }
function wp_upload_dir() {
	return [ 'baseurl' => 'https://example.com/wp-content/uploads',
	         'basedir' => $GLOBALS['UP'], 'error' => false ];
}
function content_url( $p = '' )   { return 'https://example.com/wp-content/' . $p; }
function add_action( ...$a ) {}
function add_shortcode( ...$a ) {}
function esc_url( $v )  { return $v; }
function esc_attr( $v ) { return $v; }

require_once __DIR__ . '/../includes/class-hero-slider.php';

$T = 0; $F = 0;
function is_same( $e, $a, $l ) {
	global $T, $F; $T++;
	if ( $e === $a ) { echo "  ok   $l\n"; return; }
	$F++; echo "  FAIL $l\n       expected: " . var_export( $e, true ) . "\n       actual:   " . var_export( $a, true ) . "\n";
}

function make( string $rel ): void { touch( $GLOBALS['UP'] . '/' . $rel ); }

function attachment( int $id, string $file, int $w, int $h, array $sizes = [] ): void {
	$GLOBALS['ATT'][ $id ] = [
		'url'  => 'https://example.com/wp-content/uploads/2026/09/' . $file,
		'meta' => [ 'width' => $w, 'height' => $h, 'sizes' => $sizes ],
	];
}

/** webp_sources() is private; the test calls it the way render() does. */
function sources( int $id ): array {
	$m = new ReflectionMethod( 'Mavo_Hero_Slider', 'webp_sources' );

	return array_map(
		static fn( $s ) => $s['w'] . ':' . basename( $s['webp'] ) . ':' . $s['h'],
		$m->invoke( null, $id )
	);
}

$sz = static fn( string $f, int $w, int $h ) => [ 'file' => $f, 'width' => $w, 'height' => $h ];

/* 1 — the ordinary 4:3 case must come out exactly as before ---------------- */
foreach ( [ 'IMG_1.jpg', 'IMG_1-640x480.jpg', 'IMG_1-480x360.jpg' ] as $f ) { make( "2026/09/$f.webp" ); }
attachment( 1, 'IMG_1.jpg', 960, 720, [ 'a' => $sz( 'IMG_1-640x480.jpg', 640, 480 ), 'b' => $sz( 'IMG_1-480x360.jpg', 480, 360 ) ] );
is_same(
	[ '960:IMG_1.jpg.webp:720', '640:IMG_1-640x480.jpg.webp:480', '480:IMG_1-480x360.jpg.webp:360' ],
	sources( 1 ), 'plain 4:3 attachment -> unchanged three candidates' );

/* 2 — EXIF-rotated: metadata names the intermediates without "-rotated" ---- */
foreach ( [ 'IMG_2-rotated.jpeg', 'IMG_2-640x853.jpeg', 'IMG_2-480x640.jpeg' ] as $f ) { make( "2026/09/$f.webp" ); }
attachment( 2, 'IMG_2-rotated.jpeg', 960, 1280, [ 'a' => $sz( 'IMG_2-640x853.jpeg', 640, 853 ), 'b' => $sz( 'IMG_2-480x640.jpeg', 480, 640 ) ] );
is_same(
	[ '960:IMG_2-rotated.jpeg.webp:1280', '640:IMG_2-640x853.jpeg.webp:853', '480:IMG_2-480x640.jpeg.webp:640' ],
	sources( 2 ), 'rotated original -> intermediates read from metadata, no -rotated' );

/* 3 — rotated with NO metadata sizes: the fallback must strip -rotated ----- */
foreach ( [ 'IMG_3-rotated.jpeg', 'IMG_3-640x853.jpeg', 'IMG_3-480x640.jpeg' ] as $f ) { make( "2026/09/$f.webp" ); }
attachment( 3, 'IMG_3-rotated.jpeg', 960, 1280, [] );
is_same(
	[ '960:IMG_3-rotated.jpeg.webp:1280', '640:IMG_3-640x853.jpeg.webp:853', '480:IMG_3-480x640.jpeg.webp:640' ],
	sources( 3 ), 'rotated with empty metadata -> arithmetic fallback strips -rotated' );

/* 4 — the live 960x679 case: truncation said 452/339, the files are 453/340 */
foreach ( [ 'IMG_4.jpeg', 'IMG_4-640x453.jpeg', 'IMG_4-480x340.jpeg' ] as $f ) { make( "2026/09/$f.webp" ); }
attachment( 4, 'IMG_4.jpeg', 960, 679, [] );
is_same(
	[ '960:IMG_4.jpeg.webp:679', '640:IMG_4-640x453.jpeg.webp:453', '480:IMG_4-480x340.jpeg.webp:340' ],
	sources( 4 ), '960x679 -> rounds to the filenames WordPress wrote' );

/* 5 — a cropped size sharing a width must not beat the proportional one ---- */
foreach ( [ 'IMG_5.jpg', 'IMG_5-640x640.jpg', 'IMG_5-640x480.jpg' ] as $f ) { make( "2026/09/$f.webp" ); }
attachment( 5, 'IMG_5.jpg', 960, 720, [
	'square' => $sz( 'IMG_5-640x640.jpg', 640, 640 ),
	'wide'   => $sz( 'IMG_5-640x480.jpg', 640, 480 ),
] );
is_same( '640:IMG_5-640x480.jpg.webp:480', sources( 5 )[1],
	'two sizes at 640 -> the proportional one wins over the hard crop' );

/* 6 — a missing intermediate is dropped, not emitted as a 404 -------------- */
make( '2026/09/IMG_6.jpg.webp' );
make( '2026/09/IMG_6-640x480.jpg.webp' );   // 480w sidecar never generated
attachment( 6, 'IMG_6.jpg', 960, 720, [] );
is_same( [ '960:IMG_6.jpg.webp:720', '640:IMG_6-640x480.jpg.webp:480' ], sources( 6 ),
	'missing 480w sidecar -> dropped, working candidates kept' );

/* 7 — no full-size webp at all: render() must skip the slide --------------- */
attachment( 7, 'IMG_7.jpg', 960, 720, [] );
is_same( [], sources( 7 ), 'no full-size webp -> empty, so the slide is skipped' );

/* 8 — an original narrower than a target is served as-is, not upscaled ----- */
make( '2026/09/IMG_8.jpg.webp' );
attachment( 8, 'IMG_8.jpg', 400, 300, [] );
is_same( [ '960:IMG_8.jpg.webp:300', '640:IMG_8.jpg.webp:300', '480:IMG_8.jpg.webp:300' ], sources( 8 ),
	'small original -> served as-is at every width, no upscaling' );

echo "\n$T assertions, $F failed\n";
exec( 'rm -rf ' . escapeshellarg( $GLOBALS['UP'] ) );
exit( $F ? 1 : 0 );
