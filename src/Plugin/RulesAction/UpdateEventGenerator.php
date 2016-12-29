<?php

namespace Drupal\islandora\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;
use Drupal\islandora\EventGenerator\EventGeneratorActionBase;

/**
 * Provides an action to generate a serialized Update event.
 *
 * @RulesAction(
 *   id = "islandora_update_event_generator",
 *   label = @Translation("Generate Update Event"),
 *   category = @Translation("Islandora"),
 *   context = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Updated entity"),
 *       description = @Translation("The entity that was updated")
 *     ),
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       description = @Translation("The user who updated the entity")
 *     ),
 *   },
 *   provides = {
 *     "event_message" = @ContextDefinition("string",
 *       label = @Translation("Serialized event message")
 *     )
 *   }
 * )
 */
class UpdateEventGenerator extends EventGeneratorActionBase {

  /**
   * Provides the serialized update event to downstream actions.
   */
  protected function doExecute(EntityInterface $entity, UserInterface $user) {
    $this->setProvidedValue('event_message', $this->eventGenerator->generateUpdateEvent($entity, $user));
  }

}
