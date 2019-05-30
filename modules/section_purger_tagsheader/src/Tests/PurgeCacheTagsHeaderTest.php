<?php

namespace Drupal\section_purger_tagsheader\Tests;

use Symfony\Component\HttpFoundation\Request;
use Drupal\purge\Tests\KernelTestBase;

/**
 * Tests \Drupal\section_purger_tagsheader\Plugin\Purge\TagsHeader\PurgeCacheTagsHeader.
 *
 * @group section_purger_tagsheader
 */
class PurgeCacheTagsHeaderTest extends KernelTestBase {
  public static $modules = ['system', 'section_purger_tagsheader'];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('system', ['router']);
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Test that the header value is exactly as expected (space separated).
   */
  public function testHeaderValue() {
    $request = Request::create('/system/401');
    $response = $this->container->get('http_kernel')->handle($request);
    $this->assertEqual(200, $response->getStatusCode());
    $this->assertEqual($response->headers->get('Purge-Cache-Tags'), 'config:user.role.anonymous rendered');
  }

}
