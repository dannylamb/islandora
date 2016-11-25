<?php

namespace Drupal\islandora\Indexer;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\islandora\FedoraResourceInterface;
use Drupal\islandora\Constants;

/**
 * Class FedoraIndexer.
 *
 * @package Drupal\islandora
 */
class FedoraIndexer {

  protected $stompBrokerUrl;
  protected $fedoraIndexQueue;
  protected $serializer;

  public function __construct($config, $serializer) {
    $settings = $config->get(Constants::CONFIG_NAME);
    $this->stompBrokerUrl = $settings->get(Constants::BROKER_URL);
    $this->fedoraIndexQueue = $settings->get(Constants::FEDORA_INDEX_QUEUE);
    $this->serializer = $serializer;
  }

  public function index(FedoraResourceInterface $entity) {
    dsm($this->serializer->serialize($entity, 'jsonld'));
/*
    try {
      $stomp = new Stomp($this->broker_url);
      unset($stomp);
    }
    catch(\Exception $e) {
      dsm("EXCEPTION");
    }
*/
    dsm("YOU INDEXED SOMETING");
  }

  public function delete(FedoraResourceInterface $entity) {
    dsm("YOU DELETED SOMETING");
  }

}
