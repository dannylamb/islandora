<?php

/**
 * @file
 */

namespace Drupal\islandora\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
* Provides a 'Term' condition for nodes.
*
* @Condition(
*   id = "node_has_term",
*   label = @Translation("Node has term"),
*   context = {
*     "node" = @ContextDefinition("entity:node", required = TRUE , label = @Translation("node"))
*   }
* )
*
*/
class NodeHasTerm extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Taxonomy term storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $content_type_storage
   *   Taxonomy term storage.
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
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityStorageInterface $term_storage
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->termStorage = $term_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('taxonomy_term')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $tids = array_map(
      function (array $elem) {
          return $elem['target_id'];
      },
      $this->configuration['tids']
    );
    $terms = empty($tids) ? [] : $this->termStorage->loadMultiple($tids);

    $form['tids'] = array(
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Term(s)'),
      '#description' => $this->t('Enter a comma separated list of terms.'),
      '#tags' => TRUE,
      '#default_value' => $terms,
      '#target_type' => 'taxonomy_term',
    );

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['tids'] = $form_state->getValue('tids');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return $this->evaluateEntity(
      $this->getContextValue('node')
    );
  }

  /**
   * Evaluates if an entity has the specified term(s).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to evalute.
   *
   * @return bool
   *   TRUE if entity has the specified term(s), otherwise FALSE.
   */
  protected function evaluateEntity(EntityInterface $entity) {
    $tids = array_map(
      function (array $elem) {
          return $elem['target_id'];
      },
      $this->configuration['tids']
    );
    $tids = array_combine($tids, $tids);

    foreach ($entity->referencedEntities() as $referenced_entity) {
      if ($referenced_entity->getEntityTypeId() == 'taxonomy_term'
        && isset($tids[$referenced_entity->id()])) {
            unset($tids[$referenced_entity->id()]);
            break;
      }
    }

    return $this->isNegated() ? !empty($tids) : empty($tids);
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
      return $this->t('The node is not associated with taxonomy term(s) @tids.', array('@tids' => $tids));
    }
    else {
      return $this->t('The node is associated with taxonomy term(s) @tids.', array('@tids' => $tids));
    }
 }

}

