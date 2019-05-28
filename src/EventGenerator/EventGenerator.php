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
  public function generateEvent(EntityInterface $entity, UserInterface $user, array $data) {

    $user_url = $user->toUrl()->setAbsolute()->toString();
    $entity_type = $entity->getEntityTypeId();

    $entity_url = Url::fromRoute(
      "rest.entity.$entity_type.GET",
      [$entity_type => $entity->id()],
      ['absolute' => TRUE]
    )->toString();
    $mimetype = $entity instanceof FileInterface ? $entity->getMimeType() : 'text/html';

    $event = [
      "@context" => "https://www.w3.org/ns/activitystreams",
      "actor" => [
        "type" => "Person",
        "id" => "urn:uuid:{$user->uuid()}",
        "url" => [
          [
            "name" => "Canonical",
            "type" => "Link",
            "href" => "$user_url",
            "mediaType" => "text/html",
            "rel" => "canonical",
          ],
        ],
      ],
      "object" => [
        "id" => "urn:uuid:{$entity->uuid()}",
        "url" => [
          [
            "name" => "Canonical",
            "type" => "Link",
            "href" => $entity_url,
            "mediaType" => $mimetype,
            "rel" => "canonical",
          ],
          [
            "name" => "JSON",
            "type" => "Link",
            "href" => "$entity_url?_format=json",
            "mediaType" => "application/json",
            "rel" => "alternate",
          ],
          [
            "name" => "JSONLD",
            "type" => "Link",
            "href" => "$entity_url?_format=jsonld",
            "mediaType" => "application/ld+json",
            "rel" => "alternate",
          ]
        ],
      ],
    ];

    $event_type = $data["event"];
    if ($data["event"] == "Generate Derivative") {
      $event["type"] = "Activity";
      $event["summary"] = $data["event"];
    }
    else {
      $event["type"] = ucfirst($data["event"]);
      $event["summary"] = ucfirst($data["event"]) . " a " . ucfirst($entity_type);
    }

    unset($data["event"]);
    unset($data["queue"]);

    if (!empty($data)) {
      $event["attachment"] = [
        "type" => "Object",
        "content" => $data,
        "mediaType" => "application/json",
      ];
    }

    return json_encode($event);
  }

}
