<?php

namespace Drupal\islandora\Messaging;

/**
 * Interface for interacting with messaging queues.
 */
interface MessagingServiceInterface {

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
   * @return bool
   *   TRUE if successful.
   *
   * @throws \RuntimeException
   */
  public function publish($destination, $message, array $headers = []);

}
