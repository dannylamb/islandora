<?php

namespace Drupal\islandora\MediaSource;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\media_entity\MediaInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Utility functions for working with source files for Media.
 */
class MediaSourceService {

  /**
   * Media bundle storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaBundleStorage;

  /**
   * Field config storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $fieldConfigStorage;

  /**
   * Stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_bundle_storage
   *   Media bundle storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $field_config_storage
   *   Field config storage.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   *   Stream wrapper manager.
   */
  public function __construct(
    EntityStorageInterface $media_bundle_storage,
    EntityStorageInterface $field_config_storage,
    StreamWrapperManager $stream_wrapper_manager
  ) {
    $this->mediaBundleStorage = $media_bundle_storage;
    $this->fieldConfigStorage = $field_config_storage;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * Factory.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   *   Stream wrapper manager.
   *
   * @return \Drupal\islandora\MediaSource\MediaSourceService
   *   MediaSourceService instance.
   */
  public static function create(
    EntityTypeManager $entity_type_manager,
    StreamWrapperManager $stream_wrapper_manager
  ) {
    return new static(
      $entity_type_manager->getStorage('media_bundle'),
      $entity_type_manager->getStorage('field_config'),
      $stream_wrapper_manager
    );
  }

  /**
   * Gets the name of a source field for a Media.
   *
   * @param string $media_bundle
   *   Media bundle whose source field you are searching for.
   *
   * @return string|null
   *   Field name if it exists in configuration, else NULL.
   */
  public function getSourceFieldName($media_bundle) {
    $bundle = $this->mediaBundleStorage->load($media_bundle);
    $type_configuration = $bundle->getTypeConfiguration();

    if (!isset($type_configuration['source_field'])) {
      return NULL;
    }

    return $type_configuration['source_field'];
  }

  /**
   * Gets a list of valid file extensions for a field.
   *
   * @param string $entity_type
   *   Entity type (node, media, etc...).
   * @param string $bundle
   *   Bundle the field belongs to.
   * @param string $field
   *   The field whose valid extensions you're looking for.
   *
   * @return string
   *   Space delimited string containing valid extensions.
   */
  public function getFileFieldExtensions($entity_type, $bundle, $field) {
    $field_config = $this->fieldConfigStorage->load("$entity_type.$bundle.$field");
    if (!$field_config) {
      return "";
    }
    return $field_config->getSetting('file_extensions');
  }

  /**
   * Updates a media's source field with the supplied resource.
   *
   * @param \Drupal\media_entity\MediaInterface $media
   *   The media to update.
   * @param resource $resource
   *   New file contents as a resource.
   * @param string $mimetype
   *   New mimetype of contents.
   * @param string $content_length
   *   New size of contents.
   * @param string $filename
   *   New filename for contents.
   *
   * @throws HttpException
   */
  public function updateSourceField(
    MediaInterface $media,
    $resource,
    $mimetype,
    $content_length,
    $filename
  ) {
    // Get the source field for the media type.
    $source_field = $this->getSourceFieldName($media->bundle());

    if (empty($source_field)) {
      throw new NotFoundHttpException("Source field not set for {$media->bundle()} media");
    }

    // Get the file from the media.
    $files = $media->get($source_field)->referencedEntities();
    $file = reset($files);

    // Set relevant fields on file.
    $file->setMimeType($mimetype);
    $file->setFilename($filename);
    $file->setSize($content_length);

    // Validate file extension.
    $entity_type = $media->getEntityTypeId();
    $bundle = $media->bundle();
    $valid_extensions = $this->getFileFieldExtensions($entity_type, $bundle, $source_field);
    $errors = file_validate_extensions($file, $valid_extensions);

    if (!empty($errors)) {
      throw new BadRequestHttpException("Invalid file extension.  Valid types are :$valid_extensions");
    }

    // Copy the contents over using streams.
    $uri = $file->getFileUri();
    $file_stream_wrapper = $this->streamWrapperManager->getViaUri($uri);
    $path = "";
    $file_stream_wrapper->stream_open($uri, 'w', STREAM_REPORT_ERRORS, $path);
    $file_stream = $file_stream_wrapper->stream_cast(STREAM_CAST_AS_STREAM);
    if (stream_copy_to_stream($resource, $file_stream) === FALSE) {
      throw new HttpException(500, "The file could not be copied into $uri");
    }
    $file->save();

    // Set fields provided by type plugin and mapped in bundle configuration
    // for the media.
    foreach ($media->bundle->entity->field_map as $source => $destination) {
      if ($media->hasField($destination) && $value = $media->getType()->getField($media, $source)) {
        $media->set($destination, $value);
      }
    }

    // Flush the image cache for the image so thumbnails get regenerated.
    image_path_flush($uri);

    $media->save();
  }

}
