<?php

namespace Drupal\islandora\Plugin\ContextReaction;

use Drupal\islandora\PresetReaction\PresetReaction;

/**
 * Provides a content reaction emits an index event.
 *
 * @ContextReaction(
 *   id = "index",
 *   label = @Translation("Index")
 * )
 */
class IndexReaction extends PresetReaction { }
