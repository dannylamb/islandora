<?php

namespace Drupal\Tests\islandora\Functional;

use Drupal\Core\Url;
use Drupal\field\Tests\EntityReference\EntityReferenceTestTrait;
use Drupal\Tests\media_entity\Functional\MediaEntityFunctionalTestTrait;

/**
 * Tests the RelatedLinkHeader view alter.
 *
 * @group islandora
 */
class AddMediaToNodeTest extends IslandoraFunctionalTestBase {

  use EntityReferenceTestTrait;

  /**
   * Node that has entity reference field.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $referencer;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a test content type with an entity reference field.
    $test_type_with_reference = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'test_type_with_reference',
      'label' => 'Test Type With Reference',
    ]);
    $test_type_with_reference->save();

    // Add two entity reference fields.
    // One for nodes and one for media.
    $this->createEntityReferenceField('node', 'test_type_with_reference', 'field_media', 'Media Entity', 'media', 'default', [], 2);

    $this->referencer = $this->container->get('entity_type.manager')->getStorage('node')->create([
      'type' => 'test_type_with_reference',
      'title' => 'Referencer',
    ]);
    $this->referencer->save();
  }

  public function testAddMediaToNode() {
    $account = $this->drupalCreateUser([
      'bypass node access',
      'view media',
      'create media',
      'update media',
    ]);
    $this->drupalLogin($account);
    
    // Hack out the guzzle client.
    $client = $this->getSession()->getDriver()->getClient()->getClient();

    $add_to_node_url = Url::fromRoute(
      'islandora.media_source_add_to_node',
      ['node' => $this->referencer->id(), 'field' => 'field_media', 'bundle' => 'tn']
    )
    ->setAbsolute()
    ->toString();

    $image = file_get_contents(__DIR__ . '/../../static/test.jpeg');

    $options = [
      'auth' => [$account->getUsername(), $account->pass_raw],
      'http_errors' => FALSE,
      'headers' => [
        'Content-Type' => 'image/jpeg',
        'Content-Disposition' => 'attachment; filename="test.jpeg"',
      ],
      'body' => $image,
    ];
    $response = $client->request('POST', $add_to_node_url, $options);
    $this->assertTrue($response->getStatusCode() == 201, "Unsuccessful");
  }
}

