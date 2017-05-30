<?php

namespace Drupal\islandora\Tests\Web;

use Drupal\Core\Url;
use Drupal\node\Entity\NodeType;
use Drupal\rdf\Entity\RdfMapping;

/**
 * Implements WEB tests for Context routing response in various scenarios.
 *
 * @group islandora
 */
class JsonldContextGeneratorWebTest extends IslandoraWebTestBase {

  /**
   * A user entity.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user;

  /**
   * Jwt does not define a config schema breaking this tests.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * Initial setup tasks that for every method method.
   */
  public function setUp() {
    parent::setUp();

    $test_type = NodeType::create([
      'type' => 'test_type',
      'label' => 'Test Type',
    ]);
    $test_type->save();

    // Give it a basic rdf mapping.
    $mapping = RdfMapping::create([
      'id' => 'node.test_type',
      'targetEntityType' => 'node',
      'bundle' => 'test_type',
      'types' => ['schema:Thing'],
      'fieldMappings' => [
        'title' => [
          'properties' => ['dc11:title'],
        ],
      ],
    ]);
    $mapping->save();

    $this->user = $this->drupalCreateUser([
      'administer site configuration',
      'administer nodes',
    ]);

    // Login.
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the Context Response Page can be reached.
   */
  public function testJsonldcontextPageExists() {
    $url = Url::fromRoute('islandora.jsonldcontext', ['entity_type' => 'node', 'bundle' => 'test_type']);
    $this->drupalGet($url);
    $this->assertResponse(200);
  }

  /**
   * Tests that the response is in fact application/ld+json.
   */
  public function testJsonldcontextContentypeheaderResponseIsValid() {
    $url = Url::fromRoute('islandora.jsonldcontext', ['entity_type' => 'node', 'bundle' => 'test_type']);
    $this->drupalGet($url);
    $this->assertEqual($this->drupalGetHeader('Content-Type'), 'application/ld+json', 'Correct JSON-LD mime type was returned');
  }

  /**
   * Tests that the Context received has the basic structural needs.
   */
  public function testJsonldcontextResponseIsValid() {
    $url = Url::fromRoute('islandora.jsonldcontext', ['entity_type' => 'node', 'bundle' => 'test_type']);
    $this->drupalGet($url);
    $jsonldarray = json_decode($this->getRawContent(), TRUE);
    // Check if the only key is "@context".
    $this->assertTrue(count(array_keys($jsonldarray)) == 1 && (key($jsonldarray) == '@context'), "JSON-LD to array encoded response has just one key and that key is @context");
  }

}
