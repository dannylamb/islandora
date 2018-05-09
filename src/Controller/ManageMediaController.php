<?php

namespace Drupal\islandora\Controller;

use Drupal\node\NodeInterface;

/**
 * Page to select new media type to add.
 */
class ManageMediaController extends ManageMembersController {

  /**
   * Renders a list of media types to add.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node you want to add a media to.
   */
  public function addToNodePage(NodeInterface $node) {
    return $this->generateTypeList(
      'media',
      'media_type',
      'entity.media.add_form',
      'entity.media_type.add_form',
      $node,
      'field_media_of'
    );
  }

}
