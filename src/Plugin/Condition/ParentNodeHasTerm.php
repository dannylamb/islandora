<?php

/**
 * @file
 */

namespace Drupal\islandora\Plugin\Condition;

use Drupal\islandora\IslandoraUtils;

/**
* Provides a 'Term' condition for Media.
*
* @Condition(
*   id = "parent_node_has_term",
*   label = @Translation("Parent node for media has term"),
*   context = {
*     "media" = @ContextDefinition("entity:media", required = TRUE , label = @Translation("media"))
*   }
* )
*
*/
class ParentNodeHasTerm extends NodeHasTerm {

  /**
   * Islandora utils.
   *
   * @var \Drupal\islandora\IslandoraUtils
   */
  protected $utils;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $content_type_storage
   *   Taxonomy term storage.
   * @param \Drupal\islandora\IslandoraUtils $utils
   *   Islandora utils.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityStorageInterface $term_storage,
    IslandoraUtils $utils
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $term_storage);
    $this->utils = $utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_term'),
      $container->get('islandora.utils')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return $this->evaluateEntity(
      $this->utils->getParentNode($this->getContextValue('media'))
    );
  }

  /**
   * {@inheritdoc}
   */
  public function summary()
  {
    $tids = array_map(
      function (array $elem) {
          return $elem['target_id'];
      },
      $this->configuration['tids']
    );
    $tids = implode(',', $tids);

    if (!empty($this->configuration['negate'])) {
      return $this->t('The parent node is not associated with taxonomy term(s) @tids.', array('@tids' => $tids));
    }
    else {
      return $this->t('The parent node is associated with taxonomy term(s) @tids.', array('@tids' => $tids));
    }
  }

}



