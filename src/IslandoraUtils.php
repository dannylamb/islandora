<?php

namespace Drupal\islandora;

use Drupal\context\ContextManager;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\file\FileInterface;
use Drupal\islandora\ContextProvider\NodeContextProvider;
use Drupal\islandora\ContextProvider\MediaContextProvider;
use Drupal\islandora\ContextProvider\FileContextProvider;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Utility functions for figuring out when to fire derivative reactions.
 */
class IslandoraUtils {

  const MEDIA_OF_FIELD = 'field_media_of';
  const EXTERNAL_URI_FIELD = 'field_external_uri';
  const TAGS_FIELD = 'field_tags';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

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
   * Stream wrapper manager.
   *
   * @var \Drupal\Core\StreamWrapper\StreamWrapperManager
   */
  protected $streamWrapperManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   Entity query.
   * @param \Drupal\context\ContextManager $context_manager
   *   Context manager.
   * @param \Drupal\Core\StreamWrapper\StreamWrapperManager $stream_wrapper_manager
   *   Stream wrapper manager.
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    EntityFieldManager $entity_field_manager,
    QueryFactory $entity_query,
    ContextManager $context_manager,
    StreamWrapperManager $stream_wrapper_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityQuery = $entity_query;
    $this->contextManager = $context_manager;
    $this->streamWrapperManager = $stream_wrapper_manager;
  }

  /**
   * Gets nodes that a media belongs to.
   *
   * @param MediaInterface $media
   *   The Media whose node you are searching for.
   *
   * @return \Drupal\node\NodeInterface
   *   Parent node.
   */
  public function getParentNode(MediaInterface $media) {
    if (!$media->hasField(self::MEDIA_OF_FIELD)) {
      return NULL;
    }
    $field = $media->get(self::MEDIA_OF_FIELD);
    if ($field->isEmpty()) {
      return NULL;
    }
    return $field->first()
      ->get('entity')
      ->getTarget()
      ->getValue();
  }

  /**
   * Gets media that belong to a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The parent node.
   *
   * @return MediaInterface[]
   *   The children Media.
   */
  public function getMedia(NodeInterface $node) {
    $mids = $this->entityQuery->get('media')->condition(self::MEDIA_OF_FIELD, $node->id())->execute();
    return $this->entityTypeManager->getStorage('media')->loadMultiple($mids);
  }

  /**
   * Gets media that belong to a node with the specified term.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The parent node.
   * @param \Drupal\taxonomy\TermInterface $term
   *   Taxonomy term.
   *
   * @return MediaInterface
   *   The child Media.
   */
  public function getMediaWithTerm(NodeInterface $node, TermInterface $term) {
    $mids = $this->entityQuery->get('media')
      ->condition(self::MEDIA_OF_FIELD, $node->id())
      ->condition(self::TAGS_FIELD, $term->id())
      ->execute();
    if (empty($mids)) {
      return NULL;
    }
    return $this->entityTypeManager->getStorage('media')->load(reset($mids));
  }

  /**
   * Gets Media that reference a File.
   *
   * @param int $fid
   *   File id.
   *
   * @return \Drupal\media\MediaInterface[]
   *   Array of media.
   */
  public function getReferencingMedia($fid) {
    // Get media fields that reference files.
    $fields = $this->entityQuery->get('field_storage_config')
      ->condition('entity_type', 'media')
      ->condition('settings.target_type', 'file')
      ->execute();

    // Process field names, stripping off 'media.' and appending 'target_id'.
    $conditions = array_map(
      function ($field) {
        return ltrim($field, 'media.') . '.target_id';
      },
      $fields
    );

    // Query for media that reference this file.
    $query = $this->entityQuery->get('media', 'OR');
    foreach ($conditions as $condition) {
      $query->condition($condition, $fid);
    }

    return $this->entityTypeManager->getStorage('media')->loadMultiple($query->execute());
  }

  /**
   * Gets the taxonomy term associated with an external uri.
   *
   * @param string $uri
   *   External uri.
   *
   * @return \Drupal\taxonomy\TermInterface|NULL
   *   Term or NULL if not found.
   */
  public function getTermForUri($uri) {
    $results = $this->entityQuery->get('taxonomy_term')
      ->condition(self::EXTERNAL_URI_FIELD . '.uri', $uri)
      ->execute();

    if (empty($results)) {
      return NULL; 
    }
    return $this->entityTypeManager->getStorage('taxonomy_term')->load(reset($results));
  }

  /**
   * Gets the taxonomy term associated with an external uri.
   *
   * @param \Drupal\taxonomy\TermInterface $term
   *   Taxonomy term.
   *
   * @return string|NULL
   *   URI or NULL if not found.
   */
  public function getUriForTerm(TermInterface $term) {
    if ($term && $term->hasField(self::EXTERNAL_URI_FIELD)) {
      $field = $term->get(self::EXTERNAL_URI_FIELD);
      if (!$field->isEmpty()) {
        $link = $field->first()->getValue();
        return $link['uri'];
      }
    }
    return NULL;
  }

  /**
   * Executes context reactions for a Node.
   *
   * @param string $reaction_type
   *   Reaction type.
   * @param \Drupal\node\NodeInterface $node
   *   Node to evaluate contexts and pass to reaction.
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
   *   Reaction type.
   * @param \Drupal\media\MediaInterface $media
   *   Media to evaluate contexts and pass to reaction.
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
   *   Reaction type.
   * @param \Drupal\file\FileInterface $file
   *   File to evaluate contexts and pass to reaction.
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

  /**
   * Executes derivative reactions for a Media and Node.
   *
   * @param string $reaction_type
   *   Reaction type.
   * @param \Drupal\node\NodeInterface $node
   *   Node to pass to reaction.
   * @param \Drupal\media\MediaInterface $media
   *   Media to evaluate contexts.
   */
  public function executeDerivativeReactions($reaction_type, NodeInterface $node, MediaInterface $media) {
    $provider = new MediaContextProvider($media);
    $provided = $provider->getRuntimeContexts([]);
    $this->contextManager->evaluateContexts($provided);

    // Fire off index reactions.
    foreach ($this->contextManager->getActiveReactions($reaction_type) as $reaction) {
      $reaction->execute($node);
    }
  }

  /**
   * Evaluates if fields have changed between two instances of a ContentEntity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The updated entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $original
   *   The original entity.
   */
  public function haveFieldsChanged(ContentEntityInterface $entity, ContentEntityInterface $original) {

    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

    $ignore_list = ['vid' => 1, 'changed' => 1, 'path' => 1];
    $field_definitions = array_diff_key($field_definitions, $ignore_list);

    foreach ($field_definitions as $field_name => $field_definition) {
      $langcodes = array_keys($entity->getTranslationLanguages());

      if ($langcodes !== array_keys($original->getTranslationLanguages())) {
        // If the list of langcodes has changed, we need to save.
        return TRUE;
      }

      foreach ($langcodes as $langcode) {
        $items = $entity
          ->getTranslation($langcode)
          ->get($field_name)
          ->filterEmptyItems();
        $original_items = $original
          ->getTranslation($langcode)
          ->get($field_name)
          ->filterEmptyItems();

        // If the field items are not equal, we need to save.
        if (!$items->equals($original_items)) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
