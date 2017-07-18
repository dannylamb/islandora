<?php

namespace Drupal\islandora\EventGenerator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
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

    $user_url = $user->toUrl()->setAbsolute()->toString();

    return [
      "@context" => "https://www.w3.org/ns/activitystreams",
      "actor" => [
        "type" => "Person",
        "id" => "urn:uuid:{$user->uuid()}",
        "url" => [
          [
            "name" => "Drupal Canonical",
            "type" => "Link",
            "href" => "$user_url",
            "mediaType" => "text/html",
            "rel" => "canonical",
          ],
          [
            "name" => "Drupal JSONLD",
            "type" => "Link",
            "href" => "$user_url?_format=jsonld",
            "mediaType" => "application/ld+json",
          ],
          [
            "name" => "Drupal JSON",
            "type" => "Link",
            "href" => "$user_url?_format=json",
            "mediaType" => "application/json",
          ],
        ],
      ],
      "object" => [
        "id" => "urn:uuid:{$entity->uuid()}",
        "url" => $this->generateEntityLinks($entity),
      ],
    ];
  }

  /**
   * Generates entity urls (files are slightly different).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that was created.
   *
   * @return array
   *   AS2 Links.
   */
  protected function generateEntityLinks(EntityInterface $entity) {
    if ($entity instanceof FileInterface) {
        $file_url = $entity->url();
        $checksum_url = Url::fromRoute('view.file_checksum.rest_export_1', ['file' => $entity->id()])
          ->setAbsolute()
          ->toString();
        $json_url = Url::fromRoute('rest.entity.file.GET.json', ['file' => $entity->id()])
          ->setAbsolute()
          ->toString();

        return [
          [
            "name" => "Drupal Canonical",
            "type" => "Link",
            "href" => "$file_url",
            "mediaType" => $entity->getMimeType(),
            "rel" => "canonical",
          ],
          [
            "name" => "Drupal Checksum",
            "type" => "Link",
            "href" => "$checksum_url?_format=json",
            "mediaType" => "application/json",
          ],
          [
            "name" => "Drupal JSON",
            "type" => "Link",
            "href" => "$json_url?_format=json",
            "mediaType" => "application/json",
          ],
        ];
    }
    else {
      $entity_url = $entity->toUrl()->setAbsolute()->toString();
      return [
          [
            "name" => "Drupal Canoncial",
            "type" => "Link",
            "href" => "$entity_url",
            "mediaType" => "text/html",
            "rel" => "canonical",
          ],
          [
            "name" => "Drupal JSONLD",
            "type" => "Link",
            "href" => "$entity_url?_format=jsonld",
            "mediaType" => "application/ld+json",
          ],
          [
            "name" => "Drupal JSON",
            "type" => "Link",
            "href" => "$entity_url?_format=json",
            "mediaType" => "application/json",
          ],
      ];
    }
  }
}
