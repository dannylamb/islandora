<?php

namespace Drupal\islandora\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\taxonomy\TermInterface;

/**
 * Provides an 'Is Term' condition.
 *
 * @Condition(
 *   id = "is_term",
 *   label = @Translation("Is Term"),
 *   context = {
 *     "taxonomy_term" = @ContextDefinition("entity:taxonomy_term", label = @Translation("Term"))
 *   }
 * )
 */
class IsTerm extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('The entity is a Taxonomy Term');
  }

  /**
   * {@inheritdoc}
   */
  public function getContextMapping() {
    $this->configuration['context_mapping'] = ['taxonomy_term' => '@islandora.taxonomy_term_route_context_provider:taxonomy_term'];
    return parent::getContextMapping();
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return $this->getContextValue('taxonomy_term') instanceof TermInterface;
  }

}
