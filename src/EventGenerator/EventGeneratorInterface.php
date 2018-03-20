<?php

namespace Drupal\islandora\EventGenerator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\user\UserInterface;

/**
 * Inteface for a service that provides serialized AS2 messages.
 */
interface EventGeneratorInterface {

  /**
   * Generates an event as an associative array.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity in the action.
   * @param \Drupal\user\UserInterface $user
   *   The user performing the action.
   * @param array $data
   *   Arbitrary data to include as a json encoded note.
   *
   * @return string
   *   Serialized event message.
   */
  public function generateEvent(EntityInterface $entity, UserInterface $user, array $data);

}
