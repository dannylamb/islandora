<?php

namespace Drupal\islandora\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;

/**
 * Class NodeLinkHeaderSubscriber.
 *
 * @package Drupal\islandora\EventSubscriber
 */
class NodeLinkHeaderSubscriber extends LinkHeaderSubscriber implements EventSubscriberInterface {

  /**
   * Adds node-specific link headers to appropriate responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Event containing the response.
   */
  public function onResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();

    $node = $this->getObject($response, 'node');

    if ($node === FALSE) {
      return;
    }

    $links = array_merge(
      $this->generateEntityReferenceLinks($node),
      $this->generateRestLinks($node)
    );

    // Add the link headers to the response.
    if (empty($links)) {
      return;
    }

    $response->headers->set('Link', $links, FALSE);
  }

}
