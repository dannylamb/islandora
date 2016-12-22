<?php

namespace Drupal\islandora\MessagePublisher;

use Drupal\Component\Uuid\UuidInterface;

/**
 * STOMP implementation of MessagingPublisherInterface. 
 */
class MessagePublisher implements MessagePublisherInterface {

  /**
   * Url used to connect to the message broker.
   *
   * @var string
   */
  protected $brokerUrl;

  /**
   * UUID generator.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidGenerator;

  /**
   * Constructs a new MessagePublisher.
   *
   * @param string $broker_url
   *   Url used to connect to message broker.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_generator
   *   Service to generate UUID's for receipt headers.
   */
  public __construct($broker_url, UuidInterface $uuid_generator) {
    $this->brokerUrl = $broker_url;
    $this->uuidGenerator = $uuid_generator;
  }

  /**
   * {@inheritdoc}
   */
  public function publish($destination, $message, $headers = []) {
    try {
      // Obtain the connection to the broker.
      $stomp = new \Stomp($this->brokerUrl);
    }
    catch(StompException $e) {
      throw new \RuntimeException(
        "Connection to STOMP broker failed: " . $e->getMessage(),
        $e->getCode(),
        $e
      );
    }

    // Add a receipt header if it hasn't already been done.
    // This forces the client to wait until the STOMP broker has received
    // the message or timeout occurs.  Otherwise, the returned bool would
    // always be TRUE.
    if (!isset($headers['receipt'])) {
      $headers['receipt'] = $this->uuidGenerator->generate();
    }

    $result = $stomp->send($destination, $message, $headers);

    // Close the connection to the broker.
    unset($stomp);

    if (!$result) {
      throw new \RuntimeException(
        "Failure publishing message to $destination",
        500
      );
    }
  }

}
