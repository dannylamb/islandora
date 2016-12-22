<?php

use Drupal\Core\Config\ConfigFactoryInterface;
use const Drupal\islandora\IslandoraSettings\CONFIG;
use const Drupal\islandora\IslandoraSettings\BROKER_URL;

/**
 * Decouples configuration from constructing a new MessagePublisher.
 */
class MessagePublisherFactory {

  /**
   * Creates a new MessagePublisher.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Configuration service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   Service to generate UUID's for receipt headers.
   */
  public static function create(ConfigFactoryInterface $config, UuidInterface $uuid_generator) {
    return new MessagePublisher(
      $config->get(CONFIG)->get(BROKER_URL),
      $uuidGenerator
    );
  }
  
}
