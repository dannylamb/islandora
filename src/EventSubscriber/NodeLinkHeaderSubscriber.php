<?php

namespace Drupal\islandora\EventSubscriber;

use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Class NodeLinkHeaderSubscriber.
 *
 * @package Drupal\islandora\EventSubscriber
 */
class NodeLinkHeaderSubscriber implements EventSubscriberInterface {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  protected $entityFieldManager;

  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManager $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   */
  public function __construct(
    EntityFieldManager $entity_field_manager,
    RouteMatchInterface $route_match
  ) {
    $this->entityFieldManager = $entity_field_manager;
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // Run this early so the headers get cached.
    $events[KernelEvents::RESPONSE][] = ['onResponse', 129];

    return $events;
  }

  /**
   * Adds node-specific link headers to appropriate responses.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   Event containing the response.
   */
  public function onResponse(FilterResponseEvent $event) {
    $response = $event->getResponse();

    // Exit early if the response is already cached.
    if ($response->headers->get('X-Drupal-Dynamic-Cache') == 'HIT') {
      return;
    }

    if (!$response->isOk()) {
      return;
    }

    // Hack the node out of the route.
    $route_object = $this->routeMatch->getRouteObject();
    if (!$route_object) {
      return;
    }

    $methods = $route_object->getMethods();
    $is_get = in_array('GET', $methods);
    $is_head = in_array('HEAD', $methods);
    if (!($is_get || $is_head)) {
      return;
    }

    $route_contexts = $route_object->getOption('parameters');
    if (!$route_contexts) {
      return;
    }
    if (!isset($route_contexts['node'])) {
      return;
    }

    $node = $this->routeMatch->getParameter('node');

    if (!$node) {
      return;
    }

    // Use the node to add link headers for each entity reference.
    $bundle = $node->bundle();

    // Get all fields for the entity.
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);

    // Strip out everything but entity references that are not base fields.
    $entity_reference_fields = array_filter($fields, function ($field) {
      return $field->getFieldStorageDefinition()->isBaseField() == FALSE && $field->getType() == "entity_reference";
    });

    // Collect links for referenced entities.
    $links = [];
    foreach ($entity_reference_fields as $field_name => $field_definition) {
      foreach ($node->get($field_name)->referencedEntities() as $referencedEntity) {
        // Headers are subject to an access check.
        if ($referencedEntity->access('view')) {
          $entity_url = $referencedEntity->url('canonical', ['absolute' => TRUE]);
          $field_label = $field_definition->label();
          $links[] = "<$entity_url>; rel=\"related\"; title=\"$field_label\"";
        }
      }
    }

    // Exit early if there aren't any.
    if (empty($links)) {
      return;
    }

    // Add the link headers to the response.
    $response->headers->set('Link', $links, FALSE);
  }

}
