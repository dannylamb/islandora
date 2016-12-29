<?php

namespace Drupal\Tests\islandora\Kernel;

/**
 * Tests the basic behavior of the CreateEventGenerator rules action.
 *
 * @group islandora
 * @coversDefaultClass \Drupal\islandora\Plugin\RulesAction\CreateEventGenerator
 */
class CreateEventGeneratorTest extends EventGeneratorActionTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Get an instance of the action.
    $this->action = $this->actionManager->createInstance('islandora_create_event_generator');
  }

  /**
   * Tests the CreateEventGenerator action.
   *
   * @covers \Drupal\islandora\Plugin\RulesAction\CreateEventGenerator::execute
   */
  public function testExecute() {
    // Execute the action and get the message it outputs.
    $msg = $this->assertExecution();

    // Assert it's outputs a 'Create' event.
    $this->assertTrue($msg["type"] == "Create", "Event must be of type 'Create'.");
  }

}
