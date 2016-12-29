<?php

namespace Drupal\islandora\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;
use Drupal\islandora\EventGenerator\EventGeneratorActionBase;

/**
 * Provides an action to generate a serialized Update event.
 *
 * @RulesAction(
 *   id = "islandora_delete_event_generator",
 *   label = @Translation("Generate Delete Event"),
 *   category = @Translation("Islandora"),
 *   context = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Deleted entity"),
 *       description = @Translation("The entity that was deleted")
 *     ),
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       description = @Translation("The user who deleted the entity")
 *     ),
 *   },
 *   provides = {
 *     "event_message" = @ContextDefinition("string",
 *       label = @Translation("Serialized event message")
 *     )
 *   }
 * )
 */
class DeleteEventGenerator extends EventGeneratorActionBase {

  /**
   * Provides the serialized delete event to downstream actions.
   */
  protected function doExecute(EntityInterface $entity, UserInterface $user) {
    $this->setProvidedValue('event_message', $this->eventGenerator->generateDeleteEvent($entity, $user));
  }

}
