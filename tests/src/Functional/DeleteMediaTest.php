<?php

namespace Drupal\Tests\islandora\Functional;

/**
 * Tests the DeleteMedia and DeleteMediaAndFile actions.
 *
 * @group islandora
 */
class DeleteMediaTest extends IslandoraFunctionalTestBase {

  /**
   * Media.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $media;

  /**
   * File to belong to the media.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a test user.
    $account = $this->createUser(['create media']);

    // Make a file for the Media.
    $this->file = $this->container->get('entity_type.manager')->getStorage('file')->create([
      'uid' => $account->id(),
      'uri' => "public://test_file.txt",
      'filename' => "test_file.txt",
      'filemime' => "text/plain",
      'status' => FILE_STATUS_PERMANENT,
    ]);
    $this->file->save();

    // Get the source field for the media.
    $type_configuration = $this->testMediaType->get('source_configuration');
    $source_field = $type_configuration['source_field'];

    // Make the media for the referencer.
    $this->media = $this->container->get('entity_type.manager')->getStorage('media')->create([
      'bundle' => $this->testMediaType->id(),
      'name' => 'Media',
      "$source_field" => [$this->file->id()],
    ]);
    $this->media->save();
  }

  /**
   * Tests the delete_media action.
   *
   * @covers \Drupal\islandora\Plugin\Action\DeleteMedia::execute
   */
  public function testDeleteMedia() {
    $action = $this->container->get('entity_type.manager')->getStorage('action')->load('delete_media');

    $mid = $this->media->id();
    $fid = $this->file->id();

    $action->execute([$this->media]);

    // Attempt to reload the entities.
    // Media should be gone but file should remain.
    $this->assertTrue(
      !$this->container->get('entity_type.manager')->getStorage('media')->load($mid),
      "Media must be deleted after running action"
    );
    $this->assertTrue(
      $this->container->get('entity_type.manager')->getStorage('file')->load($fid),
      "File must remain after running action"
    );
  }

  /**
   * Tests the delete_media_and_file action.
   *
   * @covers \Drupal\islandora\Plugin\Action\DeleteMediaAndFile::execute
   */
  public function testDeleteMediaAndFile() {
    $action = $this->container->get('entity_type.manager')->getStorage('action')->load('delete_media_and_file');

    $mid = $this->media->id();
    $fid = $this->file->id();

    $action->execute([$this->media]);

    // Attempt to reload the entities.
    // Both media and file should be gone.
    $this->assertTrue(
      !$this->container->get('entity_type.manager')->getStorage('media')->load($mid),
      "Media must be deleted after running action"
    );
    $this->assertTrue(
      !$this->container->get('entity_type.manager')->getStorage('file')->load($fid),
      "File must be deleted after running action"
    );
  }

}
