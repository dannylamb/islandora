<?php

namespace Drupal\Tests\islandora\Kernel;

/**
 * Base class for testing EventGenerator functionality.
 */
abstract class EventGeneratorActionTestBase extends EventGeneratorTestBase {

  /**
   * Action plugin manager.
   *
   * @var \Drupal\rules\Core\RulesActionManagerInterface
   */
  protected $actionManager;

  /**
   * Action to test.
   *
   * @var Drupal\rules\Core\RulesActionInterface
   */
  protected $action;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Get the action manager.
    $this->actionManager = $this->container->get('plugin.manager.rules_action');
  }

  /**
   * Utility function to bootstrap an action, run it, and do basic asserts.
   *
   * @return array
   *   The event message, as an array.
   */
  protected function assertExecution() {

    // Set the required contexts for the action to run.
    $this->action->setContextValue('entity', $this->entity);
    $this->action->setContextValue('user', $this->user);

    // Execute the action.
    $this->action->execute();

    // Assert some basics.
    $message_str = $this->action->getProvidedContext('event_message')->getContextValue();
    $this->assertNotEmpty($message_str, "Event message must not be empty.");
    $message = json_decode($message_str, TRUE);
    $this->assertTrue(array_key_exists('type', $message), "Event has type key.");

    // Return the event message.
    return $message;
  }

}
