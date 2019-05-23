<?php

namespace Drupal\Tests\islandora\Kernel;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Tests\field\Traits\EntityReferenceTestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Tests the upload form handler.
 *
 * @group islandora
 */
class IslandoraUploadWebformHandlerTest extends IslandoraKernelTestBase {

  use EntityReferenceTestTrait;
  use MediaTypeCreationTrait;
  use TestFileCreationTrait;

  protected $testType;

  protected $testMediaType;

  protected $testVocabulary;

  protected $modelTerm;

  protected $useTerm;

  protected $file;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a test content type.
    $this->testType = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'test_type',
      'name' => 'Test Type',
    ]);
    $this->testType->save();
    $this->createEntityReferenceField('node', 'test_type', 'field_member_of', 'Member Of', 'node', 'default', [], 2);
    $this->createEntityReferenceField('node', 'test_type', 'field_model', 'Model', 'taxonomy_term', 'default', [], 2);

    // Create a media type.
    $this->testMediaType = $this->createMediaType('file', ['id' => 'test_media_type']);
    $this->testMediaType->save();
    $this->createEntityReferenceField('media', $this->testMediaType->id(), 'field_media_of', 'Media Of', 'node', 'default', [], 2);
    $this->createEntityReferenceField('media', $this->testMediaType->id(), 'field_media_use', 'Media Use', 'taxonomy_term', 'default', [], 2);

    // Create a vocabulary.
    $this->testVocabulary = $this->container->get('entity_type.manager')->getStorage('taxonomy_vocabulary')->create([
      'name' => 'Test Vocabulary',
      'vid' => 'test_vocabulary',
    ]);
    $this->testVocabulary->save();

    // Create two terms.
    $this->modelTerm = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->create([
      'name' => 'Binary',
      'vid' => $this->testVocabulary->id(),
    ]);
    $this->modelTerm->save();

    $this->useTerm = $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->create([
      'name' => 'Original File',
      'vid' => $this->testVocabulary->id(),
    ]);
    $this->useTerm->save();

    // Pretend this is the user uploaded file.
    $this->file = $this->container->get('entity_type.manager')->getStorage('file')->create([
      'uri' => "public://test_file.txt",
      'filename' => "test_file.txt",
      'filemime' => "text/plain",
      'status' => FILE_STATUS_PERMANENT,
    ]);
    $this->file->save();
  }

  /**
   * @covers \Drupal\islandora\Plugin\WebformHandler\IslandoraUploadWebformHandler::submitForm
   * @covers \Drupal\islandora\Plugin\WebformHandler\IslandoraUploadWebformHandler::confirmForm
   */
  public function testSubmitForm() {
    $handler_manager = $this->container->get('plugin.manager.webform.handler');
    $handler = $handler_manager->createInstance('islandora_upload');

    $form = [];

    $prophecy = $this->prophesize(FormStateInterface::class);
    $prophecy->setRedirect('entity.node.edit_form', ['node' => 1])->shouldBeCalled();
    $form_state = $prophecy->reveal();

    $prophecy = $this->prophesize(WebformSubmissionInterface::class);
    $prophecy->getData()->willReturn([
      'content_type' => 'test_type',
      'media_type' => 'test_media_type',
      'model' => "{$this->modelTerm->id()}",
      'media_use' => "{$this->useTerm->id()}",
      'file' => $this->file->id(),
    ]);
    $webform_submission = $prophecy->reveal();

    $handler->submitForm($form, $form_state, $webform_submission);

    $node = $this->container->get('entity_type.manager')->getStorage('node')->load(1);
    $media = $this->container->get('entity_type.manager')->getStorage('media')->load(1);

    // Assert content type.
    $actual = $node->bundle();
    $expected = $this->testType->id();
    $this->assertTrue($actual == $expected, "Incorrect Content Type: Expected $expected, received $actual");

    // Assert model.
    $actual = $node->get('field_model')->referencedEntities()[0]->id();
    $expected = $this->modelTerm->id();
    $this->assertTrue($actual == $expected, "Incorrect Model: Expected $expected, received $actual");

    // Assert title.
    $actual = $node->getTitle();
    $expected = $this->file->getFileName();
    $this->assertTrue($actual == $expected, "Incorrect Title: Expected $expected, received $actual");

    // Assert media type.
    $actual = $media->bundle();
    $expected = $this->testMediaType->id();
    $this->assertTrue($actual == $expected, "Incorrect Media Type: Expected $expected, received $actual");

    // Assert media use.
    $actual = $media->get('field_media_use')->referencedEntities()[0]->id();
    $expected = $this->useTerm->id();
    $this->assertTrue($actual == $expected, "Incorrect Media Use: Expected $expected, received $actual");

    // Assert media name.
    $actual = $media->getName();
    $expected = $this->file->getFileName();
    $this->assertTrue($actual == $expected, "Incorrect Name: Expected $expected, received $actual");

    // Assert media is using file.
    $actual = $media->get('field_media_file')->referencedEntities()[0]->id();
    $expected = $this->file->id();
    $this->assertTrue($actual == $expected, "Incorrect File: Expected $expected, received $actual");

    // Assert media is linked to node.
    $actual = $media->get('field_media_of')->referencedEntities()[0]->id();
    $expected = $node->id();
    $this->assertTrue($actual == $expected, "Linked to incorrect node: Expected $expected, received $actual");

    // Assert that setRedirect is called in the confirm form function.
    $handler->confirmForm($form, $form_state, $webform_submission);
  }

}
