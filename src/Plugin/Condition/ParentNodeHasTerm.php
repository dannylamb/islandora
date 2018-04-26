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
   * {@inheritdoc}
   */
  public function evaluate() {
    $media = $this->getContextValue('media');
    if (!$media) {
      return FALSE;
    }
    $node = $this->utils->getParentNode($media);
    if (!$node) {
      return FALSE;
    }
    return $this->evaluateEntity($node);
  }

  /**
   * {@inheritdoc}
   */
  public function summary()
  {
    if (!empty($this->configuration['negate'])) {
      return $this->t('The parent node is not associated with taxonomy term with uri @uri.', array('@uri' => $this->configuration['uri']));
    }
    else {
      return $this->t('The parent node is associated with taxonomy term with uri @uri.', array('@uri' => $this->configuration['uri']));
    }
  }

}



