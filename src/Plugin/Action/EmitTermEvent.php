<?php

namespace Drupal\islandora\Plugin\Action;

use Drupal\islandora\EventGenerator\EmitEvent;

/**
 * Emits a Term event.
 *
 * @Action(
 *   id = "emit_term_event",
 *   label = @Translation("Emit a term event to a queue/topic"),
 *   type = "taxonomy_term"
 * )
 */
class EmitTermEvent extends EmitEvent {}
