<?php

namespace Drupal\islandora\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatch;
use Drupal\islandora\IslandoraUtils;

/**
 * Page to select new type to add as member.
 */
class AddCollectionController extends ManageMembersController {

  /**
   * Renders a list of types to add as a collection.
   */
  public function addCollectionPage() {
    $term = $this->utils->getTermForUri('http://purl.org/dc/dcmitype/Collection');
    $field = IslandoraUtils::MODEL_FIELD;

    return $this->generateTypeList(
      'node',
      'node_type',
      'node.add',
      'node.type_add',
      $field,
      ['query' => ["edit[$field][widget]" => $term->id()]]
    );
  }

  /**
   * Check if the object being displayed "is Islandora".
   *
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   The current routing match.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Whether we can or can't show the "thing".
   */
  public function access(RouteMatch $route_match) {
    if ($this->utils->canCreateIslandoraEntity('node', 'node_type')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
