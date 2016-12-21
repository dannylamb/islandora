<?php

namespace Drupal\islandora\Messaging;

use Drupal\Component\Uuid\UuidInterface;

/**
 * STOMP implementation of MessagingServiceInterface. 
 */
class StompService implements MessagingServiceInterface {

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
  protected $uuidService;

  /**
   * Constructs a new StompService.
   *
   * @param string $broker_url
   *   Url used to connect to message broker.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid_service
   *   Service to generate UUID's for receipt headers.
   */
  public __construct($broker_url, UuidInterface $uuid_service) {
    $this->brokerUrl = $broker_url;
    $this->uuidService = $uuid_service;
  }

  /**
   * {@inheritdoc}
   */
  public function publish($destination, $message, $headers = []) {
    try {
      // Obtain the connection to the broker.
      $stomp = new Stomp($this->brokerUrl);
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
      $headers['receipt'] = $this->uuidService->generate();
    }

    $result = $stomp->send($destination, $message, $headers);

    // Close the connection to the broker.
    unset($stomp);

    return $result;
  }

}
