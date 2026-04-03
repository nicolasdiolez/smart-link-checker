<?php
/**
 * Unit tests for LinkClassifier.
 *
 * @package MuriLinkTracker\Tests\Unit
 */

declare( strict_types=1 );

namespace MuriLinkTracker\Tests\Unit;

use MuriLinkTracker\Models\Enums\LinkType;
use MuriLinkTracker\Scanner\LinkClassifier;
use PHPUnit\Framework\Attributes\CoversClass;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Unit tests for LinkClassifier.
 */
#[CoversClass(LinkClassifier::class)]
class LinkClassifierTest extends TestCase {

	private LinkClassifier $classifier;

	protected function setUp(): void {
		parent::setUp();
		$this->classifier = new LinkClassifier( 'https://example.com' );
	}

	// --- classify_type() ---

	public function test_classify_type_internal_absolute(): void {
		$result = $this->classifier->classify_type( 'https://example.com/page' );
		$this->assertSame( LinkType::Internal, $result );
	}

	public function test_classify_type_internal_relative(): void {
		$result = $this->classifier->classify_type( '/my-page/' );
		$this->assertSame( LinkType::Internal, $result );
	}

	public function test_classify_type_internal_subdomain(): void {
		$result = $this->classifier->classify_type( 'https://blog.example.com/post' );
		$this->assertSame( LinkType::Internal, $result );
	}

	public function test_classify_type_external(): void {
		$result = $this->classifier->classify_type( 'https://other-site.com/page' );
		$this->assertSame( LinkType::External, $result );
	}

	public function test_classify_type_external_similar_domain(): void {
		$result = $this->classifier->classify_type( 'https://notexample.com/page' );
		$this->assertSame( LinkType::External, $result );
	}

	public function test_classify_type_protocol_relative_internal(): void {
		$result = $this->classifier->classify_type( '//example.com/page' );
		$this->assertSame( LinkType::Internal, $result );
	}

	public function test_classify_type_protocol_relative_external(): void {
		$result = $this->classifier->classify_type( '//other.com/page' );
		$this->assertSame( LinkType::External, $result );
	}

	public function test_classify_type_no_host(): void {
		$result = $this->classifier->classify_type( 'page-name' );
		$this->assertSame( LinkType::Internal, $result );
	}

	// --- detect_affiliate() ---

	public function test_detect_affiliate_amazon_com(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.amazon.com/dp/B08N5WRWNW?tag=mysite-20' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'amazon', $result['network'] );
	}

	public function test_detect_affiliate_amzn_to(): void {
		$result = $this->classifier->detect_affiliate( 'https://amzn.to/3xyzABC' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'amazon', $result['network'] );
	}

	public function test_detect_affiliate_amazon_fr(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.amazon.fr/dp/B08N5WRWNW' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'amazon', $result['network'] );
	}

	public function test_detect_affiliate_shareasale(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.shareasale.com/r.cfm?b=123&u=456' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'shareasale', $result['network'] );
	}

	public function test_detect_affiliate_cj_domain(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.anrdoezrs.net/click-123-456' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'cj', $result['network'] );
	}

	public function test_detect_affiliate_impact_domain(): void {
		$result = $this->classifier->detect_affiliate( 'https://example.sjv.io/abc123' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'impact', $result['network'] );
	}

	public function test_detect_affiliate_awin(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.awin1.com/cread.php?s=123&v=456' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'awin', $result['network'] );
	}

	public function test_detect_affiliate_by_path_go(): void {
		$result = $this->classifier->detect_affiliate( 'https://example.com/go/product-name' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_affiliate_by_path_recommends(): void {
		$result = $this->classifier->detect_affiliate( 'https://example.com/recommends/tool-name/' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_affiliate_by_query_param_tag(): void {
		$result = $this->classifier->detect_affiliate( 'https://shop.com/product?tag=mysite-20' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_affiliate_by_query_param_aff_id(): void {
		$result = $this->classifier->detect_affiliate( 'https://shop.com/product?aff_id=123' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_non_affiliate(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.wikipedia.org/article' );
		$this->assertFalse( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	// --- classify_rel() ---

	public function test_classify_rel_nofollow(): void {
		$result = $this->classifier->classify_rel( 'nofollow' );
		$this->assertTrue( $result['rel_nofollow'] );
		$this->assertFalse( $result['rel_sponsored'] );
		$this->assertFalse( $result['rel_ugc'] );
		$this->assertFalse( $result['is_dofollow'] );
	}

	public function test_classify_rel_sponsored(): void {
		$result = $this->classifier->classify_rel( 'sponsored' );
		$this->assertFalse( $result['rel_nofollow'] );
		$this->assertTrue( $result['rel_sponsored'] );
		$this->assertFalse( $result['rel_ugc'] );
		$this->assertFalse( $result['is_dofollow'] );
	}

	public function test_classify_rel_ugc(): void {
		$result = $this->classifier->classify_rel( 'ugc' );
		$this->assertFalse( $result['rel_nofollow'] );
		$this->assertFalse( $result['rel_sponsored'] );
		$this->assertTrue( $result['rel_ugc'] );
		$this->assertFalse( $result['is_dofollow'] );
	}

	public function test_classify_rel_combined(): void {
		$result = $this->classifier->classify_rel( 'nofollow sponsored noopener' );
		$this->assertTrue( $result['rel_nofollow'] );
		$this->assertTrue( $result['rel_sponsored'] );
		$this->assertFalse( $result['rel_ugc'] );
		$this->assertFalse( $result['is_dofollow'] );
	}

	public function test_classify_rel_dofollow_empty(): void {
		$result = $this->classifier->classify_rel( '' );
		$this->assertFalse( $result['rel_nofollow'] );
		$this->assertFalse( $result['rel_sponsored'] );
		$this->assertFalse( $result['rel_ugc'] );
		$this->assertTrue( $result['is_dofollow'] );
	}

	public function test_classify_rel_dofollow_noopener_only(): void {
		$result = $this->classifier->classify_rel( 'noopener noreferrer' );
		$this->assertFalse( $result['rel_nofollow'] );
		$this->assertFalse( $result['rel_sponsored'] );
		$this->assertFalse( $result['rel_ugc'] );
		$this->assertTrue( $result['is_dofollow'] );
	}

	// --- Phase 5: New affiliate networks ---

	public function test_detect_affiliate_amazon_it(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.amazon.it/dp/B09XYZ' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'amazon', $result['network'] );
	}

	public function test_detect_affiliate_amazon_com_au(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.amazon.com.au/dp/B09XYZ' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'amazon', $result['network'] );
	}

	public function test_detect_affiliate_tradedoubler(): void {
		$result = $this->classifier->detect_affiliate( 'https://clkde.tradedoubler.com/click?p=123&a=456' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'tradedoubler', $result['network'] );
	}

	public function test_detect_affiliate_skimlinks(): void {
		$result = $this->classifier->detect_affiliate( 'https://go.skimresources.com?id=123&url=https://shop.com' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'skimlinks', $result['network'] );
	}

	public function test_detect_affiliate_sovrn(): void {
		$result = $this->classifier->detect_affiliate( 'https://redirect.viglink.com?key=abc&u=https://shop.com' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'sovrn', $result['network'] );
	}

	public function test_detect_affiliate_flexoffers(): void {
		$result = $this->classifier->detect_affiliate( 'https://track.flexlinks.com/a.ashx?foid=123' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertSame( 'flexoffers', $result['network'] );
	}

	public function test_detect_affiliate_by_query_param_subid(): void {
		$result = $this->classifier->detect_affiliate( 'https://shop.com/product?subid=my-tracking' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_affiliate_by_query_param_clickid(): void {
		$result = $this->classifier->detect_affiliate( 'https://shop.com/product?clickid=abc123' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	// --- Phase 5: rel="sponsored" detection ---

	public function test_detect_affiliate_by_rel_sponsored(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.wikipedia.org/article', 'sponsored' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_affiliate_by_rel_sponsored_combined(): void {
		$result = $this->classifier->detect_affiliate( 'https://unknown-shop.com/product', 'nofollow sponsored noopener' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_non_affiliate_without_rel(): void {
		$result = $this->classifier->detect_affiliate( 'https://www.wikipedia.org/article', '' );
		$this->assertFalse( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	// --- Phase 5: Cloaking path patterns ---

	public function test_detect_affiliate_by_path_redirect(): void {
		$result = $this->classifier->detect_affiliate( 'https://example.com/redirect/product-name' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_affiliate_by_path_deal(): void {
		$result = $this->classifier->detect_affiliate( 'https://example.com/deal/black-friday/' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}

	public function test_detect_affiliate_by_path_visit(): void {
		$result = $this->classifier->detect_affiliate( 'https://example.com/visit/store-name' );
		$this->assertTrue( $result['is_affiliate'] );
		$this->assertNull( $result['network'] );
	}
}
