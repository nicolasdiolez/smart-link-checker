<?php
/**
 * Load Test Data Generator for sentinel-link-checker.
 *
 * Generates 5000+ posts with various types of links for scale testing.
 *
 * Usage: wp eval-file tests/load-test-generator.php [count]
 * Example: wp eval-file tests/load-test-generator.php 5000
 */

if ( ! defined( 'ABSPATH' ) ) {
	// Try to find WordPress root.
	$current_dir = __DIR__;
	while ( ! file_exists( $current_dir . '/wp-load.php' ) && '/' !== $current_dir ) {
		$current_dir = dirname( $current_dir );
	}

	if ( file_exists( $current_dir . '/wp-load.php' ) ) {
		require_once $current_dir . '/wp-load.php';
	} else {
		die( "Error: Could not find wp-load.php\n" );
	}
}

$count = isset( $argv[1] ) ? (int) $argv[1] : ( isset( $args[0] ) ? (int) $args[0] : 1000 );
printf( "Generating %d posts...\n", $count );

$link_templates = [
	'https://example.com/internal-%d',
	'https://www.amazon.com/dp/B08N5WRWNW?tag=mysite-%d',
	'https://other-site.com/external-%d',
	'https://amzn.to/3xyz%d',
	'#local-anchor-%d',
	'mailto:user%d@example.com',
];

for ( $i = 1; $i <= $count; $i++ ) {
	$content = sprintf( "<!-- wp:paragraph --><p>This is post number %d.</p><!-- /wp:paragraph -->\n", $i );

	// Add 3-5 random links per post.
	$num_links = rand( 3, 5 );
	for ( $j = 0; $j < $num_links; $j++ ) {
		$tpl  = $link_templates[ array_rand( $link_templates ) ];
		$url  = sprintf( $tpl, $i * 10 + $j );
		$text = "Link " . ( $j + 1 );
		$content .= sprintf( "<!-- wp:paragraph --><p><a href=\"%s\">%s</a></p><!-- /wp:paragraph -->\n", esc_url( $url ), esc_html( $text ) );
	}

	$post_id = wp_insert_post( [
		'post_title'   => "Load Test Post $i",
		'post_content' => $content,
		'post_status'  => 'publish',
		'post_author'  => 1,
	] );

	if ( $i % 100 === 0 ) {
		printf( "Created %d posts...\n", $i );
	}
}

echo "Done! Generated $count posts.\n";
