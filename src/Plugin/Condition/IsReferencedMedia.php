<?php

namespace Drupal\islandora\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Condition to see if a Media is referenced by a Node using a particular field.
 *
 * @Condition(
 *   id = "is_referenced_media",
 *   label = @Translation("Is Referenced Media"),
 *   context = {
 *     "media" = @ContextDefinition("entity:media", label = @Translation("Media"))
 *   }
 * )
 */
class IsReferencedMedia extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Content type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $contentTypeStorage;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityFieldManager;

  /**
   * Entity query service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * Creates a new IsReferencedMedia instance.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $content_type_storage
   *   Content type storage.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   Entity query service.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(
    EntityStorageInterface $content_type_storage,
    EntityFieldManager $entity_field_manager,
    QueryFactory $entity_query,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->contentTypeStorage = $content_type_storage;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityQuery = $entity_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node_type'),
      $container->get('entity_field.manager'),
      $container->get('entity.query'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Get all entity reference fields that target Media.
    $media_reference_fields = $this->entityQuery->get('field_storage_config')
      ->condition('entity_type', 'node')
      ->condition('type', 'entity_reference')
      ->condition('settings.target_type', 'media')
      ->execute();

    // Trim off the preceding 'node.'.
    $media_reference_fields = array_map(
      function ($field) {
        return ltrim($field, 'node.');
      },
      $media_reference_fields
    );

    // Flip the results so it can be used in an array_intersect_key later on.
    $media_reference_fields = array_flip($media_reference_fields);

    // Get all content types.
    $content_types = $this->contentTypeStorage->loadMultiple();

    // Build up the 2D options array.
    $options = [];
    foreach ($content_types as $content_type) {
      // Filter fields to those we know are media references.
      $all_fields = $this->entityFieldManager->getFieldDefinitions('node', $content_type->id());
      $reference_fields = array_intersect_key($all_fields, $media_reference_fields);

      // First dimension is keyed by the content type label.
      // Second dimension is keyed by the content_type machine name concatenated
      // with the field name.  The content type machine name is needed for
      // disambiguation, otherwise fields attached to multiple content types
      // have unexpected behaviour when submitting the form.
      foreach ($reference_fields as $field_name => $field_definition) {
        $content_type_label = $content_type->label();
        $field_key = $content_type->id() . '|' . $field_name;
        $field_label = $field_definition->getLabel();
        $options[$content_type_label][$field_key] = $field_label;
      }
    }

    // Create the 2D select.
    $form['field'] = [
      '#title' => $this->t('Referenced As'),
      '#description' => $this->t('The field that references the Media.'),
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => isset($this->configuration['field']) ? $this->configuration['field'] : '',
      '#size' => 10,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['field'] = $form_state->getValue('field');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['field' => ''] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('The Media is referenced by a Node using the specified field.');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Check to see that the media is referenced by a node of the specified
    // type using the specified field.
    $media = $this->getContextValue('media');
    $field_key = $this->configuration['field'];
    list($content_type, $field) = explode('|', $field_key);
    return $this->entityQuery->get('node')
      ->condition('type', $content_type)
      ->condition("$field.target_id", $media->id())
      ->execute();
  }

}
