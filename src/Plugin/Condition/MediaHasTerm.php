<?php

/**
 * @file
 */

namespace Drupal\islandora\Plugin\Condition;

/**
* Provides a 'Term' condition for Media.
*
* @Condition(
*   id = "media_has_term",
*   label = @Translation("Media has term"),
*   context = {
*     "media" = @ContextDefinition("entity:media", required = TRUE , label = @Translation("media"))
*   }
* )
*
*/
class MediaHasTerm extends NodeHasTerm {

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    return $this->evaluateEntity(
      $this->getContextValue('media')
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
      return $this->t('The media is not associated with taxonomy term(s) @tids.', array('@tids' => $tids));
    }
    else {
      return $this->t('The media is associated with taxonomy term(s) @tids.', array('@tids' => $tids));
    }
  }

}


