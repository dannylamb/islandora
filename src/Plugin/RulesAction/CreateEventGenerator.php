<?php

namespace Drupal\islandora\Plugin\RulesAction;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;
use Drupal\islandora\EventGenerator\EventGeneratorActionBase;

/**
 * Provides an action to generate a serialized Update event.
 *
 * @RulesAction(
 *   id = "islandora_create_event_generator",
 *   label = @Translation("Generate Create Event"),
 *   category = @Translation("Islandora"),
 *   context = {
 *     "entity" = @ContextDefinition("entity",
 *       label = @Translation("Created entity"),
 *       description = @Translation("The entity that was created")
 *     ),
 *     "user" = @ContextDefinition("entity:user",
 *       label = @Translation("User"),
 *       description = @Translation("The user who created the entity")
 *     ),
 *   },
 *   provides = {
 *     "event_message" = @ContextDefinition("string",
 *       label = @Translation("Serialized event message")
 *     )
 *   }
 * )
 */
class CreateEventGenerator extends EventGeneratorActionBase {

  /**
   * Provides the serialized create event to downstream actions.
   */
  protected function doExecute(EntityInterface $entity, UserInterface $user) {
    $this->setProvidedValue('event_message', $this->eventGenerator->generateCreateEvent($entity, $user));
  }

}
