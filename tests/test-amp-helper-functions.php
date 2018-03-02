<?php
/**
 * Test AMP helper functions.
 *
 * @package AMP
 */

/**
 * Class Test_AMP_Helper_Functions
 */
class Test_AMP_Helper_Functions extends WP_UnitTestCase {

	/**
	 * After a test method runs, reset any state in WordPress the test method might have changed.
	 */
	public function tearDown() {
		remove_theme_support( 'amp' );
		parent::tearDown();
	}

	/**
	 * Filter for amp_pre_get_permalink and amp_get_permalink.
	 *
	 * @param string $url     URL.
	 * @param int    $post_id Post ID.
	 * @return string URL.
	 */
	public function return_example_url( $url, $post_id ) {
		$current_filter = current_filter();
		return 'http://overridden.example.com/?' . build_query( compact( 'url', 'post_id', 'current_filter' ) );
	}

	/**
	 * Test amp_get_slug().
	 *
	 * @covers amp_get_slug()
	 */
	public function test_amp_get_slug() {
		$this->assertSame( 'amp', amp_get_slug() );
	}

	/**
	 * Test amp_get_permalink() without pretty permalinks.
	 *
	 * @covers \amp_get_permalink()
	 */
	public function test_amp_get_permalink_without_pretty_permalinks() {
		delete_option( 'permalink_structure' );
		flush_rewrite_rules();

		$drafted_post   = $this->factory()->post->create( array(
			'post_name'   => 'draft',
			'post_status' => 'draft',
			'post_type'   => 'post',
		) );
		$published_post = $this->factory()->post->create( array(
			'post_name'   => 'publish',
			'post_status' => 'publish',
			'post_type'   => 'post',
		) );
		$published_page = $this->factory()->post->create( array(
			'post_name'   => 'publish',
			'post_status' => 'publish',
			'post_type'   => 'page',
		) );

		$this->assertStringEndsWith( '&amp', amp_get_permalink( $published_post ) );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $drafted_post ) );
		$this->assertStringEndsWith( '&amp', amp_get_permalink( $published_page ) );

		add_filter( 'amp_pre_get_permalink', array( $this, 'return_example_url' ), 10, 2 );
		add_filter( 'amp_get_permalink', array( $this, 'return_example_url' ), 10, 2 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_pre_get_permalink', $url );
		$this->assertContains( 'url=0', $url );

		remove_filter( 'amp_pre_get_permalink', array( $this, 'return_example_url' ), 10 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_get_permalink', $url );
	}

	/**
	 * Test amp_get_permalink() with pretty permalinks.
	 *
	 * @covers \amp_get_permalink()
	 */
	public function test_amp_get_permalink_with_pretty_permalinks() {
		global $wp_rewrite;
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
		$wp_rewrite->use_trailing_slashes = true;
		$wp_rewrite->init();
		$wp_rewrite->flush_rules();

		$drafted_post   = $this->factory()->post->create( array(
			'post_name'   => 'draft',
			'post_status' => 'draft',
		) );
		$published_post = $this->factory()->post->create( array(
			'post_name'   => 'publish',
			'post_status' => 'publish',
		) );
		$published_page = $this->factory()->post->create( array(
			'post_name'   => 'publish',
			'post_status' => 'publish',
			'post_type'   => 'page',
		) );

		$this->assertStringEndsWith( '&amp', amp_get_permalink( $drafted_post ) );
		$this->assertStringEndsWith( '/amp/', amp_get_permalink( $published_post ) );
		$this->assertStringEndsWith( '?amp', amp_get_permalink( $published_page ) );

		add_filter( 'amp_pre_get_permalink', array( $this, 'return_example_url' ), 10, 2 );
		add_filter( 'amp_get_permalink', array( $this, 'return_example_url' ), 10, 2 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_pre_get_permalink', $url );
		$this->assertContains( 'url=0', $url );

		remove_filter( 'amp_pre_get_permalink', array( $this, 'return_example_url' ), 10 );
		$url = amp_get_permalink( $published_post );
		$this->assertContains( 'current_filter=amp_get_permalink', $url );
	}

	/**
	 * Filter calls.
	 *
	 * @var array
	 */
	protected $last_filter_call;

	/**
	 * Capture filter call.
	 *
	 * @param mixed $value Value.
	 * @return mixed Value.
	 */
	public function capture_filter_call( $value ) {
		$this->last_filter_call = array(
			'current_filter' => current_filter(),
			'args'           => func_get_args(),
		);
		return $value;
	}

	/**
	 * Test amp_get_content_embed_handlers().
	 *
	 * @covers amp_get_content_embed_handlers()
	 */
	public function test_amp_get_content_embed_handlers() {
		$post = $this->factory()->post->create_and_get();
		add_filter( 'amp_content_embed_handlers', array( $this, 'capture_filter_call' ), 10, 2 );

		$this->last_filter_call = null;
		add_theme_support( 'amp' );
		$handlers = amp_get_content_embed_handlers();
		$this->assertArrayHasKey( 'AMP_SoundCloud_Embed_Handler', $handlers );
		$this->assertEquals( 'amp_content_embed_handlers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$this->assertNull( $this->last_filter_call['args'][1] );

		$this->last_filter_call = null;
		remove_theme_support( 'amp' );
		$handlers = amp_get_content_embed_handlers( $post );
		$this->assertArrayHasKey( 'AMP_SoundCloud_Embed_Handler', $handlers );
		$this->assertEquals( 'amp_content_embed_handlers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$this->assertEquals( $post, $this->last_filter_call['args'][1] );
	}

	/**
	 * Test deprecated $post param for amp_get_content_embed_handlers().
	 *
	 * @covers amp_get_content_embed_handlers()
	 */
	public function test_amp_get_content_embed_handlers_deprecated_param() {
		$post = $this->factory()->post->create_and_get();
		$this->setExpectedDeprecated( 'amp_get_content_embed_handlers' );
		add_theme_support( 'amp' );
		amp_get_content_embed_handlers( $post );
	}

	/**
	 * Test amp_get_content_sanitizers().
	 *
	 * @covers amp_get_content_sanitizers()
	 */
	public function test_amp_get_content_sanitizers() {
		$post = $this->factory()->post->create_and_get();
		add_filter( 'amp_content_sanitizers', array( $this, 'capture_filter_call' ), 10, 2 );

		$this->last_filter_call = null;
		add_theme_support( 'amp' );
		$handlers = amp_get_content_sanitizers();
		$this->assertArrayHasKey( 'AMP_Style_Sanitizer', $handlers );
		$this->assertEquals( 'amp_content_sanitizers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$handler_classes = array_keys( $handlers );
		$this->assertNull( $this->last_filter_call['args'][1] );
		$this->assertEquals( 'AMP_Tag_And_Attribute_Sanitizer', end( $handler_classes ) );

		$this->last_filter_call = null;
		remove_theme_support( 'amp' );
		$handlers = amp_get_content_sanitizers( $post );
		$this->assertArrayHasKey( 'AMP_Style_Sanitizer', $handlers );
		$this->assertEquals( 'amp_content_sanitizers', $this->last_filter_call['current_filter'] );
		$this->assertEquals( $handlers, $this->last_filter_call['args'][0] );
		$this->assertEquals( $post, $this->last_filter_call['args'][1] );

		// Make sure the style and whitelist sanitizers are always at the end, even after filtering.
		add_filter( 'amp_content_sanitizers', function( $classes ) {
			$classes['Even_After_Whitelist_Sanitizer'] = array();
			return $classes;
		} );
		$orderd_sanitizers = array_keys( amp_get_content_sanitizers() );
		$this->assertEquals( 'Even_After_Whitelist_Sanitizer', $orderd_sanitizers[ count( $orderd_sanitizers ) - 3 ] );
		$this->assertEquals( 'AMP_Style_Sanitizer', $orderd_sanitizers[ count( $orderd_sanitizers ) - 2 ] );
		$this->assertEquals( 'AMP_Tag_And_Attribute_Sanitizer', $orderd_sanitizers[ count( $orderd_sanitizers ) - 1 ] );
	}

	/**
	 * Test deprecated $post param for amp_get_content_sanitizers().
	 *
	 * @covers amp_get_content_sanitizers()
	 */
	public function test_amp_get_content_sanitizers_deprecated_param() {
		$post = $this->factory()->post->create_and_get();
		$this->setExpectedDeprecated( 'amp_get_content_sanitizers' );
		add_theme_support( 'amp' );
		amp_get_content_sanitizers( $post );
	}

	/**
	 * Test post_supports_amp().
	 *
	 * @covers \post_supports_amp()
	 */
	public function test_post_supports_amp() {
		add_post_type_support( 'page', amp_get_slug() );

		// Test disabled by default for page for posts and show on front.
		update_option( 'show_on_front', 'page' );
		$post = $this->factory()->post->create_and_get( array( 'post_type' => 'page' ) );
		$this->assertTrue( post_supports_amp( $post ) );
		update_option( 'show_on_front', 'page' );
		$this->assertTrue( post_supports_amp( $post ) );
		update_option( 'page_for_posts', $post->ID );
		$this->assertFalse( post_supports_amp( $post ) );
		update_option( 'page_for_posts', '' );
		update_option( 'page_on_front', $post->ID );
		$this->assertFalse( post_supports_amp( $post ) );
		update_option( 'show_on_front', 'posts' );
		$this->assertTrue( post_supports_amp( $post ) );

		// Test disabled by default for page templates.
		update_post_meta( $post->ID, '_wp_page_template', 'foo.php' );
		$this->assertFalse( post_supports_amp( $post ) );

		// Reset.
		remove_post_type_support( 'page', amp_get_slug() );
	}

	/**
	 * Test amp_get_post_image_metadata()
	 *
	 * @covers \amp_get_post_image_metadata()
	 */
	public function test_amp_get_post_image_metadata() {
		$post_id = $this->factory()->post->create();
		$this->assertFalse( amp_get_post_image_metadata( $post_id ) );

		$first_test_image = '/tmp/test-image.jpg';
		copy( DIR_TESTDATA . '/images/test-image.jpg', $first_test_image );
		$attachment_id = self::factory()->attachment->create_object( array(
			'file'           => $first_test_image,
			'post_parent'    => 0,
			'post_mime_type' => 'image/jpeg',
			'post_title'     => 'Test Image',
		) );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $first_test_image ) );

		set_post_thumbnail( $post_id, $attachment_id );
		$metadata = amp_get_post_image_metadata( $post_id );
		$this->assertEquals( 'ImageObject', $metadata['@type'] );
		$this->assertEquals( 50, $metadata['width'] );
		$this->assertEquals( 50, $metadata['height'] );
		$this->assertStringEndsWith( 'test-image.jpg', $metadata['url'] );

		delete_post_thumbnail( $post_id );
		$this->assertFalse( amp_get_post_image_metadata( $post_id ) );
		wp_update_post( array(
			'ID'          => $attachment_id,
			'post_parent' => $post_id,
		) );
		$metadata = amp_get_post_image_metadata( $post_id );
		$this->assertStringEndsWith( 'test-image.jpg', $metadata['url'] );
	}

	/**
	 * Test amp_get_schemaorg_metadata().
	 *
	 * @covers \amp_get_schemaorg_metadata()
	 */
	public function test_amp_get_schemaorg_metadata() {
		update_option( 'blogname', 'Foo' );
		$expected_publisher = array(
			'@type' => 'Organization',
			'name'  => 'Foo',
		);

		$user_id = $this->factory()->user->create( array(
			'first_name' => 'John',
			'last_name'  => 'Smith',
		) );
		$page_id = $this->factory()->post->create( array(
			'post_type'   => 'page',
			'post_title'  => 'Example Page',
			'post_author' => $user_id,
		) );
		$post_id = $this->factory()->post->create( array(
			'post_type'   => 'post',
			'post_title'  => 'Example Post',
			'post_author' => $user_id,
		) );

		// Test non-singular.
		$this->go_to( home_url() );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( 'http://schema.org', $metadata['@context'] );
		$this->assertArrayNotHasKey( '@type', $metadata );
		$this->assertArrayHasKey( 'publisher', $metadata );
		$this->assertEquals( $expected_publisher, $metadata['publisher'] );

		// Test page.
		$this->go_to( get_permalink( $page_id ) );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( 'http://schema.org', $metadata['@context'] );
		$this->assertEquals( $expected_publisher, $metadata['publisher'] );
		$this->assertEquals( 'WebPage', $metadata['@type'] );
		$this->assertArrayHasKey( 'author', $metadata );
		$this->assertEquals( get_permalink( $page_id ), $metadata['mainEntityOfPage'] );
		$this->assertEquals( get_the_title( $page_id ), $metadata['headline'] );
		$this->assertArrayHasKey( 'datePublished', $metadata );
		$this->assertArrayHasKey( 'dateModified', $metadata );

		// Test post.
		$this->go_to( get_permalink( $post_id ) );
		$metadata = amp_get_schemaorg_metadata();
		$this->assertEquals( 'http://schema.org', $metadata['@context'] );
		$this->assertEquals( $expected_publisher, $metadata['publisher'] );
		$this->assertEquals( 'BlogPosting', $metadata['@type'] );
		$this->assertEquals( get_permalink( $post_id ), $metadata['mainEntityOfPage'] );
		$this->assertEquals( get_the_title( $post_id ), $metadata['headline'] );
		$this->assertArrayHasKey( 'datePublished', $metadata );
		$this->assertArrayHasKey( 'dateModified', $metadata );
		$this->assertEquals(
			array(
				'@type' => 'Person',
				'name'  => 'John Smith',
			),
			$metadata['author']
		);

		// Test override.
		$this->go_to( get_permalink( $post_id ) );
		$self = $this;
		add_filter( 'amp_post_template_metadata', function( $meta, $post ) use ( $self, $post_id ) {
			$self->assertEquals( $post_id, $post->ID );
			$meta['did_amp_post_template_metadata'] = true;
			$self->assertArrayNotHasKey( 'amp_schemaorg_metadata', $meta );
			return $meta;
		}, 10, 2 );
		add_filter( 'amp_schemaorg_metadata', function( $meta ) use ( $self ) {
			$meta['did_amp_schemaorg_metadata'] = true;
			$self->assertArrayHasKey( 'did_amp_post_template_metadata', $meta );
			$meta['author']['name'] = 'George';
			return $meta;
		} );

		$metadata = amp_get_schemaorg_metadata();
		$this->assertArrayHasKey( 'did_amp_post_template_metadata', $metadata );
		$this->assertArrayHasKey( 'did_amp_schemaorg_metadata', $metadata );
		$this->assertEquals( 'George', $metadata['author']['name'] );
	}
}
