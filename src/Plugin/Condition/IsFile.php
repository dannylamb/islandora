<?php

namespace Drupal\islandora\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Plugin\Exception\ContextException;

/**
 * Provides an 'Is File' condition.
 *
 * @Condition(
 *   id = "is_file",
 *   label = @Translation("Is File"),
 *   context = {
 *     "file" = @ContextDefinition("entity:file", label = @Translation("File"))
 *   }
 * )
 */
class IsFile extends ConditionPluginBase {

  /**
   * {@inheritdoc}
   */
  public function summary() {
    return $this->t('The entity is a File');
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    // Getting the context value will short-circuit if the file isn't in
    // context. So return FALSE if that happens.
    return $this->getContextValue('file');
  }

}
