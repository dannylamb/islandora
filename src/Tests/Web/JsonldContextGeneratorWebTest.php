<?php

namespace Drupal\islandora\Tests\Web;

use Drupal\Core\Url;
use Drupal\islandora\Entity\FedoraResourceType;
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

    // Create a bundle to test.
    $rdf_source = FedoraResourceType::create([
      'id' => 'rdf_source',
      'label' => 'RdfSource',
    ]);
    $rdf_source->save();

    // Give it a basic rdf mapping
    $rdf_source_mapping = RdfMapping::create([
      'id' => 'fedora_resourcce.rdf_source',
      'targetEntityType' => 'fedora_resource',
      'bundle' => 'rdf_source',
      'types' => ['schema:Thing'],
      'fieldMappings' => [
        'name' => [
          'properties' => ['dc11:title'],
        ],
      ],
    ]);
    $rdf_source_mapping->save();

    $this->user = $this->drupalCreateUser([
      'administer site configuration',
      'view published fedora resource entities',
      'access content',
    ]
    );
    // Login.
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the Context Response Page can be reached.
   */
  public function testJsonldcontextPageExists() {
    $url = Url::fromRoute('entity.fedora_resource_type.jsonldcontext', ['bundle' => 'rdf_source']);
    $this->drupalGet($url);
    $this->assertResponse(200);
  }

  /**
   * Tests that the response is in fact application/ld+json.
   */
  public function testJsonldcontextContentypeheaderResponseIsValid() {
    $url = Url::fromRoute('entity.fedora_resource_type.jsonldcontext', ['bundle' => 'rdf_source']);
    $this->drupalGet($url);
    $this->assertEqual($this->drupalGetHeader('Content-Type'), 'application/ld+json', 'Correct JSON-LD mime type was returned');
  }

  /**
   * Tests that the Context received has the basic structural needs.
   */
  public function testJsonldcontextResponseIsValid() {
    $url = Url::fromRoute('entity.fedora_resource_type.jsonldcontext', ['bundle' => 'rdf_source']);
    $this->drupalGet($url);
    $jsonldarray = json_decode($this->getRawContent(), TRUE);
    // Check if the only key is "@context".
    $this->assertTrue(count(array_keys($jsonldarray)) == 1 && (key($jsonldarray) == '@context'), "JSON-LD to array encoded response has just one key and that key is @context");
  }

}
