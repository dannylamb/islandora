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
    $event = $this->generateEvent($entity, $user);
    $event["type"] = "Create";
    return json_encode($event);
  }

  /**
   * {@inheritdoc}
   */
  public function generateUpdateEvent(EntityInterface $entity, UserInterface $user) {
    $event = $this->generateEvent($entity, $user);
    $event["type"] = "Update";
    return json_encode($event);
  }

  /**
   * {@inheritdoc}
   */
  public function generateDeleteEvent(EntityInterface $entity, UserInterface $user) {
    $event = $this->generateEvent($entity, $user);
    $event["type"] = "Delete";
    return json_encode($event);
  }

  /**
   * Shared event generation function that does not impose a 'Type'.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was created.
   * @param \Drupal\user\UserInterface $user
   *   The user who created the entity.
   *
   * @return array
   *   Event message as an array.
   */
  protected function generateEvent(EntityInterface $entity, UserInterface $user) {
    $entity_url = $entity->toUrl()->setAbsolute()->toString();
    $user_url = $user->toUrl()->setAbsolute()->toString();
    $event = [
      "@context" => "https://www.w3.org/ns/activitystreams",
      "actor" => [
        "type" => "Person",
        "id" => "urn:islandora:{$user->uuid()}",
        "url" => [
          [
            "type" => "Link",
            "href" => "$user_url",
            "mediaType" => "text/html",
          ],
          [
            "type" => "Link",
            "href" => "$user_url?_format=jsonld",
            "mediaType" => "application/ld+json",
          ],
        ],
      ],
      "object" => [
        "id" => "urn:islandora:{$entity->uuid()}",
        "url" => [
          [
            "type" => "Link",
            "href" => "$entity_url",
            "mediaType" => "text/html",
          ],
          [
            "type" => "Link",
            "href" => "$entity_url?_format=jsonld",
            "mediaType" => "application/ld+json",
          ],
        ],
      ],
    ];

    if ($entity instanceof Media) {
      $this->addAttachment($entity, $event);
    }

    return $event;
  }

  /**
   * Adds the 'attachment' info to the event array.
   *
   * @param \Drupal\media_entity\Entity\Media $entity
   *   The entity that was updated.
   * @param array $event
   *   Array of info to be serialized to jsonld.
   */
  protected function addAttachment(Media $entity, array &$event) {
    if ($entity->hasField("field_image")) {
      $file = $entity->field_image->entity;
    }
    elseif ($entity->hasField("field_file")) {
      $file = $entity->field_file->entity;
    }
    else {
      \Drupal::logger('islandora')->warning(
        "Cannot parse 'field_image' or 'field_file' from Media entity {$entity->id()}"
      );
      return;
    }

    if ($file === NULL) {
      \Drupal::logger('islandora')->debug(
        "'field_image' or 'field_file' is null in Media entity {$entity->id()}"
      );
      return;
    }

    $url = file_create_url($file->getFileUri());
    $mime = $file->getMimeType();
    $event['object']['attachment'] = [
      'url' => $url,
      'mediaType' => $mime,
    ];
  }

}
