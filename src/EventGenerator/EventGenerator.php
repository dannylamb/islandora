<?php

namespace Drupal\islandora\EventGenerator;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\user\UserInterface;
use Drupal\media_entity\MediaInterface;
use Drupal\node\NodeInterface;

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
   * {@inheritdoc}
   */
  public function generateDerivativeEvent(EntityInterface $entity, UserInterface $user, array $config) {
    $event = $this->generateEvent($entity, $user);
    $event["type"] = "Activity";
    $event["summary"] = "Generate a derivative";
    $event["attachment"] = [
      "type" => "Note",
      "mediaType" => "application/json",
      "content" => $config,
    ];
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
        "url" => $this->generateEntityLinks($entity),
      ],
    ];
  }

  /**
   * Generates entity urls depending on entity type.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return array
   *   AS2 Links.
   */
  protected function generateEntityLinks(EntityInterface $entity) {
    if ($entity instanceof FileInterface) {
      return $this->generateFileLinks($entity);
    }
    elseif ($entity instanceof MediaInterface) {
      return $this->generateMediaLinks($entity);
    }

    return $this->generateNodeLinks($entity);
  }

  /**
   * Generates file urls.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   *
   * @return array
   *   AS2 Links.
   */
  protected function generateFileLinks(FileInterface $file) {
    $file_url = $file->url();
    $checksum_url = Url::fromRoute('view.file_checksum.rest_export_1', ['file' => $file->id()])
      ->setAbsolute()
      ->toString();

    return [
      [
        "name" => "File",
        "type" => "Link",
        "href" => "$file_url",
        "mediaType" => $file->getMimeType(),
      ],
      [
        "name" => "Checksum",
        "type" => "Link",
        "href" => "$checksum_url?_format=json",
        "mediaType" => "application/json",
      ],
    ];
  }

  /**
   * Generates media urls.
   *
   * @param \Drupal\media_entity\MediaInterface $media
   *   The media.
   *
   * @return array
   *   AS2 Links.
   */
  protected function generateMediaLinks(MediaInterface $media) {
    $url = $media->toUrl()->setAbsolute()->toString();
    return [
        [
          "name" => "Canoncial",
          "type" => "Link",
          "href" => "$url",
          "mediaType" => "text/html",
          "rel" => "canonical",
        ],
        [
          "name" => "JSONLD",
          "type" => "Link",
          "href" => "$url?_format=jsonld",
          "mediaType" => "application/ld+json",
        ],
        [
          "name" => "JSON",
          "type" => "Link",
          "href" => "$url?_format=json",
          "mediaType" => "application/json",
        ],
    ];
  }

  /**
   * Generates node urls.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node.
   *
   * @return array
   *   AS2 Links.
   */
  protected function generateNodeLinks(NodeInterface $node) {
    $url = $node->toUrl()->setAbsolute()->toString();
    return [
        [
          "name" => "Canoncial",
          "type" => "Link",
          "href" => "$url",
          "mediaType" => "text/html",
          "rel" => "canonical",
        ],
        [
          "name" => "JSONLD",
          "type" => "Link",
          "href" => "$url?_format=jsonld",
          "mediaType" => "application/ld+json",
        ],
    ];
  }

}
