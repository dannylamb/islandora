<?php

namespace Drupal\islandora\EventSubscriber;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\islandora\IslandoraUtils;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class NodeLinkHeaderSubscriber.
 *
 * @package Drupal\islandora\EventSubscriber
 */
class NodeLinkHeaderSubscriber extends LinkHeaderSubscriber implements EventSubscriberInterface {

  /**
   * Derivative utils.
   *
   * @var \Drupal\islandora\IslandoraUtils
   */
  protected $utils;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   * @param Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack (for current request).
   * @param \Drupal\islandora\IslandoraUtils $utils
   *   Derivative utils.
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    EntityFieldManager $entity_field_manager,
    AccessManagerInterface $access_manager,
    AccountInterface $account,
    RouteMatchInterface $route_match,
    RequestStack $request_stack,
    IslandoraUtils $utils
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->accessManager = $access_manager;
    $this->account = $account;
    $this->routeMatch = $route_match;
    $this->accessManager = $access_manager;
    $this->account = $account;
    $this->requestStack = $request_stack;
    $this->utils = $utils;
  }

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
      $this->generateRelatedMediaLinks($node),
      $this->generateRestLinks($node)
    );

    // Add the link headers to the response.
    if (empty($links)) {
      return;
    }

    $response->headers->set('Link', $links, FALSE);
  }

  protected function generateRelatedMediaLinks(NodeInterface $node) {
    return array_map(
      function (MediaInterface $media) {
        $url = $media->url('canonical', ['absolute' => TRUE]);
        $model = $media->get('field_behavior')
            ->first()
            ->get('entity')
            ->getTarget()
            ->getValue()
            ->label();
        return "<$url>; rel=\"related\"; title=\"$model\"";
      },
      $this->utils->getMedia($node)
    );
  }
}
