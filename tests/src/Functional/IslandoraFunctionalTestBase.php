<?php

namespace Drupal\Tests\islandora\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\TestFileCreationTrait;

/**
 * Base class for Functional tests.
 */
class IslandoraFunctionalTestBase extends BrowserTestBase {

  use TestFileCreationTrait;

  protected static $modules = ['context_ui', 'islandora'];

  protected static $configSchemaCheckerExclusions = [
    'jwt.config',
    'context.context.test',
    'context.context.node',
    'context.context.media',
    'context.context.file',
    'key.key.test',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Delete the context entities provided by the module.
    // This will get removed as we split apart contexts into different
    // solution packs.
    $this->container->get('entity_type.manager')->getStorage('context')->load('node')->delete();
    $this->container->get('entity_type.manager')->getStorage('context')->load('media')->delete();
    $this->container->get('entity_type.manager')->getStorage('context')->load('file')->delete();

    // Create a test content type.
    $test_type = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'test_type',
      'label' => 'Test Type',
    ]);
    $test_type->save();

    // Create an action that dsm's "Hello World!".
    $hello_world = $this->container->get('entity_type.manager')->getStorage('action')->create([
      'id' => 'hello_world',
      'label' => 'Hello World',
      'type' => 'system',
      'plugin' => 'action_message_action',
      'configuration' => [
        'message' => 'Hello World!',
      ],
    ]);
    $hello_world->save();

  }

  /**
   * Creates a test context.
   */
  protected function createContext($label, $name) {
    $this->drupalPostForm('admin/structure/context/add', ['label' => $label, 'name' => $name], t('Save'));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Adds a condition to the test context.
   */
  protected function addCondition($context_id, $condition_id) {
    $this->drupalGet("admin/structure/context/$context_id/condition/add/$condition_id");
    $this->getSession()->getPage()->pressButton('Save and continue');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Adds a reaction to the test context.
   */
  protected function addPresetReaction($context_id, $reaction_type, $action_id) {
    $this->drupalGet("admin/structure/context/$context_id/reaction/add/$reaction_type");
    $this->getSession()->getPage()->findById("edit-reactions-$reaction_type-actions")->selectOption($action_id);
    $this->getSession()->getPage()->pressButton(t('Save and continue'));
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Creates a new TN media with a file.
   */
  protected function createThumbnailWithFile() {
    // Have to do this in two steps since there's no ajax for the alt text.
    // It's as annoying as in real life.
    $file = current($this->getTestFiles('image'));
    $values = [
      'name[0][value]' => 'Test Media',
      'files[field_image_0]' => drupal_realpath($file->uri),
    ];
    $this->drupalPostForm('media/add/tn', $values, t('Save and publish'));
    $values = [
      'field_image[0][alt]' => 'Alternate text',
    ];
    $this->getSession()->getPage()->fillField('edit-field-image-0-alt', 'alt text');
    $this->getSession()->getPage()->pressButton(t('Save and publish'));
    $this->assertResponse(200);
  }

  /**
   * Create a new node by posting its add form.
   */
  protected function postNodeAddForm($bundle_id, $values, $button_text) {
    $this->drupalPostForm("node/add/$bundle_id", $values, t('@text', ['@text' => $button_text]));
    $this->assertResponse(200);
  }

  /**
   * Edits a node by posting its edit form.
   */
  protected function postEntityEditForm($entity_url, $values, $button_text) {
    $this->drupalPostForm("$entity_url/edit", $values, t('@text', ['@text' => $button_text]));
    $this->assertResponse(200);
  }

}
