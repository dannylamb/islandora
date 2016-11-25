<?php

namespace Drupal\islandora;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of Fedora resource entities.
 *
 * @ingroup islandora
 */
class FedoraResourceListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('Fedora resource ID');
    $header['name'] = $this->t('Name');
    $header['parent'] = $this->t('Parent');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\islandora\Entity\FedoraResource */
    $row['id'] = $entity->id();
    $row['name'] = Link::fromTextAndUrl(
      $entity->label(),
      $entity->toUrl()
    );
    if ($entity->hasParent()) {
      $row['parent'] = Link::fromTextAndUrl(
        $entity->getParent()->label(),
        $entity->getParent()->toUrl()
      );
    }
    else {
      $row['parent'] = $this->t("n/a");
    }
    return $row + parent::buildRow($entity);
  }

}
