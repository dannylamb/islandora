<?php

namespace Drupal\Tests\islandora\Functional;

use Drupal\Core\Url;

/**
 * Tests updating Media source File with PUT.
 *
 * @group islandora
 */
class MediaSourceControllerTest extends IslandoraFunctionalTestBase {

  public function setUp() {
    parent::setUp();

    $media_rest_resource = $this->container->get('entity_type.manager')->getStorage('rest_resource_config')->create([
      'id' => 'entity.media',
      'granularity' => 'resource',
      'configuration' => [
        'methods' => ['GET'],
        'authentication' => ['basic_auth'],
        'formats' => ['json'],
      ],
      'status' => TRUE,
    ]);
    $media_rest_resource->save(TRUE);

    $this->container->get('router.builder')->rebuildIfNeeded();
  }

  /**
   * @covers \Drupal\islandora\Controller\MediaSourceController::put
   */
  public function testMediaSourceUpdate() {
    $account = $this->drupalCreateUser([
      'view media',
      'create media',
      'update media',
    ]);
    $this->drupalLogin($account);

    // Make a media and give it a png.
    $url = $this->createThumbnailWithFile();

    // Hack out the guzzle client
    $client = $this->getSession()->getDriver()->getClient()->getClient();

    // GET the media to stash its original values for comparison later.
    $options = [
      'auth' => [$account->getUsername(), $account->pass_raw],
      'http_errors' => FALSE,
    ];
    $response = $client->request('GET', $url . '?_format=json', $options);
    $media = json_decode($response->getBody(), TRUE);

    $mid = $media['mid'][0]['value'];
    $original_mimetype = $media['field_mimetype'][0]['value'];
    $original_width = $media['field_width'][0]['value'];
    $original_height = $media['field_height'][0]['value'];
    $original_image = file_get_contents($media['field_image'][0]['url']);

    $media_update_url = Url::fromRoute('islandora.media_source_update', ['media' => $mid])
      ->setAbsolute()
      ->toString();

    // Update without Content-Type header should fail with 400.
    $response = $client->request('PUT', $media_update_url, $options);
    $this->assertTrue($response->getStatusCode() == 400, "Expected 400, received {$response->getStatusCode()}");

    // Update without Content-Disposition header should fail with 400.
    $options['headers'] = ['Content-Type' => 'image/jpeg']; 
    $response = $client->request('PUT', $media_update_url, $options);
    $this->assertTrue($response->getStatusCode() == 400, "Expected 400, received {$response->getStatusCode()}");

    // Update with malformed Content-Disposition header should fail with 400.
    $options['headers']['Content-Disposition'] = 'attachment; garbage="test.jpeg"'; 
    $response = $client->request('PUT', $media_update_url, $options);
    $this->assertTrue($response->getStatusCode() == 400, "Expected 400, received {$response->getStatusCode()}");

    // Update without body should fail with 400.
    $options['headers']['Content-Disposition'] = 'attachment; filename="test.jpeg"'; 
    $response = $client->request('PUT', $media_update_url, $options);
    $this->assertTrue($response->getStatusCode() == 400, "Expected 400, received {$response->getStatusCode()}");

    // Should be successful.
    $options['body'] = file_get_contents(__DIR__ . '/../../static/test.jpeg'); 
    $response = $client->request('PUT', $media_update_url, $options);
    $this->assertTrue($response->getStatusCode() == 204, "Expected 204, received {$response->getStatusCode()}");

    // GET the media again and compare image and metadata.
    $options = [
      'auth' => [$account->getUsername(), $account->pass_raw],
      'http_errors' => FALSE,
    ];
    $response = $client->request('GET', $url . '?_format=json', $options);
    $updated = json_decode($response->getBody(), TRUE);

    $updated_mimetype = $updated['field_mimetype'][0]['value'];
    $updated_width = $updated['field_width'][0]['value'];
    $updated_height = $updated['field_height'][0]['value'];
    $updated_image = file_get_contents($updated['field_image'][0]['url']);

    $this->assertTrue($original_mimetype != $updated_mimetype, "Mimetypes should be updated with media source update");
    $this->assertTrue($original_width != $updated_width, "Height should be updated with media source update");
    $this->assertTrue($original_height != $updated_height, "Width should be updated with media source update");
    $this->assertTrue($original_image != $updated_image, "Width should be updated with media source update");

    $this->assertTrue($updated_mimetype == "image/jpeg", "Invalid mimetype.  Expected image/jpeg, received $updated_mimetype");
    $this->assertTrue($updated_width == 295, "Invalid width.  Expected 295, received $updated_width");
    $this->assertTrue($updated_height == 70, "Invalid height.  Expected 70, received $updated_height");
    $this->assertTrue($updated_image == file_get_contents(__DIR__ . '/../../static/test.jpeg'), "Updated image not the same as PUT body.");
  }

}
