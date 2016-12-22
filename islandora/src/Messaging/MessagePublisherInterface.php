<?php

namespace Drupal\islandora\MessagePublisher;

/**
 * Interface for interacting with messaging queues.
 */
interface MessagePublisherInterface {

  /**
   * Publish a message to a queue/topic.
   *
   * @param string $destination 
   *   Name of the queue/topic to publish to.
   * @param string $message
   *   The message to publish.
   * @param array $headers
   *   Associative array of message headers. 
   *
   * @throws \RuntimeException
   */
  public function publish($destination, $message, array $headers = []);

}
