<?php

namespace Drupal\islandora;

use Drupal\context\ContextManager;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\file\FileInterface;
use Drupal\islandora\ContextProvider\NodeContextProvider;
use Drupal\islandora\ContextProvider\MediaContextProvider;
use Drupal\islandora\ContextProvider\FileContextProvider;
use Drupal\media_entity\MediaInterface;
use Drupal\node\NodeInterface;

class IslandoraUtils {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityFieldManager;

  /**
   * Media storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaStorage;

  /**
   * Media bundle storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mediaBundleStorage;

  /**
   * Entity query.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Context manager.
   *
   * @var \Drupal\context\ContextManager
   */
  protected $contextManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_storage
   *   Media storage.
   * @param \Drupal\Core\Entity\EntityStorageInterface $media_bundle_storage
   *   Media bundle storage.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   Entity query.
   * @param \Drupal\context\ContextManager
   *   Context manager.
   */
  public function __construct(
    EntityFieldManager $entity_field_manager,
    EntityStorageInterface $media_storage,
    EntityStorageInterface $media_bundle_storage,
    QueryFactory $entity_query,
    ContextManager $context_manager
  ) {
    $this->entityFieldManager = $entity_field_manager;
    $this->mediaStorage = $media_storage;
    $this->mediaBundleStorage = $media_bundle_storage;
    $this->entityQuery = $entity_query;
    $this->contextManager = $context_manager;
  }

  /**
   * Factory.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   Entity query.
   *
   * @return \Drupal\islandora\IslandoraUtils
   *   IslandoraUtils instance.
   */
  public static function create(
    EntityFieldManager $entity_field_manager,
    EntityTypeManager $entity_type_manager,
    QueryFactory $entity_query,
    ContextManager $context_manager
  ) {
    return new static(
      $entity_field_manager,
      $entity_type_manager->getStorage('media'),
      $entity_type_manager->getStorage('media_bundle'),
      $entity_query,
      $context_manager
    );
  }

  /**
   * Retrieves field storage definitions for an Entity that references Media.
   *
   * @param string $entity_type
   *   Type of content entity (e.g. node, media, etc...) doing the referencing.
   * @param string $bundle
   *   Bundle of content entity doing the referencing.
   *
   * @return \Drupal\Core\Field\FieldDefinitionInterface[]
   *   Associative array of FieldDefinitionInterfaces keyed by field name.
   */
  public function getMediaReferenceFields($entity_type, $bundle) {
    $fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    return array_filter($fields, function ($field) {
      $storage_def = $field->getFieldStorageDefinition();
      return $storage_def->isBaseField() == FALSE && $field->getType() == "entity_reference" && $storage_def->getSetting('target_type') == 'media';
    });
  }

  /**
   * Retrieves Media that have just been added to an entity reference field.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The Node suspected of referencing a Media.
   *
   * @return \Drupal\media_entity\MediaInterface[]
   *   Array of MediaInterfaces keyed by id.
   */
  public function getNewMediaReferences(NodeInterface $node) {
    $media_reference_fields = $this->getMediaReferenceFields($node->getEntityTypeId(), $node->bundle());

    $mids = [];
    foreach ($media_reference_fields as $field_name => $field_definition) {
      $field_item_list = $node->get($field_name);
      $original_field_item_list = $node->original->get($field_name);

      // Continue if empty or no change in entity reference field.
      if ($field_item_list->isempty() || $field_item_list->equals($original_field_item_list)) {
        continue;
      }

      // Check each entity that's referenced in each field by searching the
      // original values before the update occurred.  If it's found, the media
      // is not newly referenced.
      foreach ($field_item_list as $field_item) {
        // grab the mid and search the original list.
        $value = $field_item->getvalue();
        $mid = $value['target_id'];

        $is_found = false;
        foreach ($original_field_item_list as $item) {
          $orig_value = $item->getvalue();
          $orig_mid = $orig_value['target_id'];
          if ($is_found = $orig_mid == $mid) {
            break;
          }
        }

        if ($is_found) {
          continue;
        }
 
        $mids[] = $mid;
      }
    }

    return empty($mids) ? [] : $this->mediaStorage->loadMultiple($mids);
  }

  /**
   * Indicates if a Media is referenced by a Node.
   *
   * @param int $mid
   *   Id of Media whose referencers you are searching for.
   *
   * @return bool
   *   TRUE if Media is referenced by any Node.
   */
  public function mediaIsReferenced($mid) {
    // Get all node fields that are entity references to Media.
    $fields = $this->entityQuery->get('field_storage_config')
      ->condition('entity_type', 'node')
      ->condition('type', 'entity_reference')
      ->condition('settings.target_type', 'media')
      ->execute();

    // Process field names, stripping off 'node.' and appending 'target_id'
    $conditions = array_map(
      function($field) { return ltrim($field, 'node.') . '.target_id'; },
      $fields
    ); 

    // Query for nodes that reference this media
    $query = $this->entityQuery->get('node', 'OR');
    foreach ($conditions as $condition) {
      $query->condition($condition, $mid);
    }
    return !empty($query->execute()); 
  }

  /**
   * Gets the name of a source field for a Media.
   *
   * @param string $media_bundle
   *   Media bundle whose source field you are searching for.
   *
   * @return string|NULL 
   *   Field name if it exists in configuration, else NULL.
   */
  public function getSourceField($media_bundle) {
    $bundle = $this->mediaBundleStorage->load($media_bundle);
    $type_configuration = $bundle->getTypeConfiguration();

    if (!isset($type_configuration['source_field'])) {
      return NULL;
    }

    return $type_configuration['source_field'];
  }

  /**
   * Gets ids for Media that reference a File.
   *
   * @param int $fid
   *   File id
   *
   * @return array
   *   Array of media ids
   */
  public function getReferencingMediaIds($fid) {
    // Get media fields that reference files.
    $fields = $this->entityQuery->get('field_storage_config')
      ->condition('entity_type', 'media')
      ->condition('settings.target_type', 'file')
      ->execute();

    // Process field names, stripping off 'media.' and appending 'target_id'
    $conditions = array_map(
      function($field) { return ltrim($field, 'media.') . '.target_id'; },
      $fields
    );

    // Query for media that reference this file 
    $query = $this->entityQuery->get('media', 'OR');
    foreach ($conditions as $condition) {
      $query->condition($condition, $fid);
    }

    return $query->execute();
  }

  /**
   * Executes context reactions for a Node.
   *
   * @param string $reaction_type
   *   Reaction type
   * @param \Drupal\node\NodeInterface $node
   *   Node to evaluate contexts and pass to reaction
   */
  public function executeNodeReactions($reaction_type, NodeInterface $node) {
    $provider = new NodeContextProvider($node);
    $provided = $provider->getRuntimeContexts([]);
    $this->contextManager->evaluateContexts($provided);

    // Fire off index reactions.
    foreach ($this->contextManager->getActiveReactions($reaction_type) as $reaction) {
      $reaction->execute($node);
    }
  }

  /**
   * Executes context reactions for a Media.
   *
   * @param string $reaction_type
   *   Reaction type
   * @param \Drupal\media_entity\MediaInterface $media
   *   Media to evaluate contexts and pass to reaction
   */
  public function executeMediaReactions($reaction_type, MediaInterface $media) {
    $provider = new MediaContextProvider($media);
    $provided = $provider->getRuntimeContexts([]);
    $this->contextManager->evaluateContexts($provided);

    // Fire off index reactions.
    foreach ($this->contextManager->getActiveReactions($reaction_type) as $reaction) {
      $reaction->execute($media);
    }
  }

  /**
   * Executes context reactions for a File.
   *
   * @param string $reaction_type
   *   Reaction type
   * @param \Drupal\file\FileInterface $file
   *   File to evaluate contexts and pass to reaction
   */
  public function executeFileReactions($reaction_type, FileInterface $file) {
    $provider = new FileContextProvider($file);
    $provided = $provider->getRuntimeContexts([]);
    $this->contextManager->evaluateContexts($provided);

    // Fire off index reactions.
    foreach ($this->contextManager->getActiveReactions($reaction_type) as $reaction) {
      $reaction->execute($file);
    }
  }

}
