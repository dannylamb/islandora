<?php

namespace Drupal\Tests\islandora\Functional;

/**
 * Tests the MediaLinkHeader event subscriber.
 *
 * @group islandora
 */
class MediaLinkHeaderTest extends IslandoraFunctionalTestBase {

  /**
   * @covers \Drupal\islandora\EventSubscriber\MediaLinkHeaderSubscriber
   */
  public function testMediaLinkHeaders() {
    // Create a test user.
    $account = $this->drupalCreateUser([
      'view media',
      'create media',
      'update media',
    ]);
    $this->drupalLogin($account);

    $urls = $this->createThumbnailWithFile();

    $this->drupalGet($urls['media'], [], ['Cache-Control: no-cache']);

    $this->assertTrue(
      $this->validateLinkHeaderWithUrl('describes', $urls['file']['file'], '', 'image/png') == 1,
      "Malformed 'describes' link header"
    );
    $this->assertTrue(
      $this->validateLinkHeaderWithUrl('edit-media', $urls['file']['rest'], '', '') == 1,
      "Malformed 'edit-media' link header"
    );

    // Check for links to REST endpoints for metadata.
    $this->assertTrue(
      $this->validateLinkHeaderWithUrl('alternate', $urls['media'] . "?_format=json", NULL, 'application/json') == 1,
      "Media must have link header pointing to json REST endpoint."
    );
    $this->assertTrue(
      $this->validateLinkHeaderWithUrl('alternate', $urls['media'] . "?_format=jsonld", NULL, 'application/ld+json') == 1,
      "Media must have link header pointing to jsonld REST endpoint."
    );
    $this->assertTrue(
      $this->validateLinkHeaderWithUrl('alternate', $urls['media'] . "?_format=xml", NULL, 'application/xml') == 0,
      "Media must not have link header pointing to disabled xml REST endpoint."
    );

  }

}
