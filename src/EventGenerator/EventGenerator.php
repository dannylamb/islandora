<?php

namespace Drupal\islandora\EventGenerator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\media_entity\Entity\Media;
use Drupal\user\UserInterface;

/**
 * The default EventGenerator implementation.
 *
 * Provides Activity Stream 2.0 serialized events.
 */
class EventGenerator implements EventGeneratorInterface {

  /**
   * {@inheritdoc}
   */
  public function generateCreateEvent(EntityInterface $entity, UserInterface $user) {
    $event = [
      "@context" => "https://www.w3.org/ns/activitystreams",
      "type" => "Create",
      "actor" => [
        "type" => "Person",
        "id" => $user->toUrl()->setAbsolute()->toString(),
      ],
      "object" => $entity->toUrl()->setAbsolute()->toString(),
    ];

    if ($entity instanceof Media) {
      $this->addAttachment($entity, $event);
    }

    return json_encode($event);
  }

  /**
   * {@inheritdoc}
   */
  public function generateUpdateEvent(EntityInterface $entity, UserInterface $user) {
    $event = [
      "@context" => "https://www.w3.org/ns/activitystreams",
      "type" => "Update",
      "actor" => [
        "type" => "Person",
        "id" => $user->toUrl()->setAbsolute()->toString(),
      ],
      "object" => $entity->toUrl()->setAbsolute()->toString(),
    ];

    if ($entity instanceof Media) {
      $this->addAttachment($entity, $event);
    }

    return json_encode($event);
  }

  /**
   * {@inheritdoc}
   */
  public function generateDeleteEvent(EntityInterface $entity, UserInterface $user) {
    $event = [
      "@context" => "https://www.w3.org/ns/activitystreams",
      "type" => "Delete",
      "actor" => [
        "type" => "Person",
        "id" => $user->toUrl()->setAbsolute()->toString(),
      ],
      "object" => $entity->toUrl()->setAbsolute()->toString(),
    ];

    if ($entity instanceof Media) {
      $this->addAttachment($entity, $event);
    }

    return json_encode($event);
  }

  protected function addAttachment(Media $entity, array &$event) {
    if ($entity->hasField("field_image")) {
      $file = $entity->field_image->entity;
    } elseif ($entity->hasField("field_file")) {
      $file = $entity->field_file->entity;
    }
    else {
      throw new \RuntimeException("Cannot parse 'field_image' or 'field_file' from Media entity", 500);
    }

    $url = file_create_url($file->getFileUri());
    $mime = $file->getMimeType();
    $event['attachment'] = [
      'url' => $url,
      'mediaType' => $mime,
    ];
  }

}
