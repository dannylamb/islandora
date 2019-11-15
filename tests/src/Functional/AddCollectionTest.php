<?php

namespace Drupal\Tests\islandora\Functional;

/**
 * Tests the AddCollectionController.
 *
 * @group islandora
 */
class AddCollectionTest extends IslandoraFunctionalTestBase {

  /**
   * Term to belong to the media.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $collectionTerm;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->collectionTerm = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->create([
      'name' => 'Collection',
      'vid' => $this->testVocabulary->id(),
      'field_external_uri' => [['uri' => "http://purl.org/dc/dcmitype/Collection"]],
    ]);
    $this->collectionTerm->save();
  }

  /**
   * @covers \Drupal\islandora\Controller\AddCollectionController::addCollectionPage
   * @covers \Drupal\islandora\Controller\AddCollectionController::access
   * @covers \Drupal\islandora\IslandoraUtils::isIslandoraType
   */
  public function testAddCollection() {
    $account = $this->drupalCreateUser([
      'bypass node access',
    ]);
    $this->drupalLogin($account);

    // Visit the add collection page.
    $this->drupalGet('/collection/add');

    // Assert the test type is in the list of available types.
    $this->assertSession()->pageTextContains($this->testType->label());

    // Click the test type and make sure you get sent to the right form.
    $this->clickLink($this->testType->label());
    $url = $this->getUrl();

    // Assert that the link creates the correct prepopulate query param.
    $substring = 'node/add/test_type?edit%5Bfield_model%5D%5Bwidget%5D=1';
    $this->assertTrue(
      strpos($url, 'node/add/test_type?edit%5Bfield_model%5D%5Bwidget%5D=1') !== FALSE,
      "Malformed URL, could not find $substring in $url."
    );
  }

}
