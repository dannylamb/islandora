<?php

namespace Drupal\islandora\EventGenerator;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rules\Core\RulesActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Abstract base class for EventGenerator RulesActions.  Sets up DI.
 */
abstract class EventGeneratorActionBase extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * The event generator that will serialize the events.
   *
   * @var \Drupal\islandora\EventGenerator\EventGeneratorInterface
   */
  protected $eventGenerator;

  /**
   * Constructs a EventGeneratorActionBase.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\islandora\EventGenerator\EventGeneratorInterface $event_generator
   *   The EventGenerator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventGeneratorInterface $event_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->eventGenerator = $event_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('islandora.eventgenerator')
    );
  }

}
