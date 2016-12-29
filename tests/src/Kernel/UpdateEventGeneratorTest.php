<?php

namespace Drupal\Tests\islandora\Kernel;

/**
 * Tests the basic behavior of the UpdateEventGenerator rules action.
 *
 * @group islandora
 * @coversDefaultClass \Drupal\islandora\Plugin\RulesAction\UpdateEventGenerator
 */
class UpdateEventGeneratorTest extends EventGeneratorActionTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Get an instance of the action.
    $this->action = $this->actionManager->createInstance('islandora_update_event_generator');
  }

  /**
   * Tests the UpdateEventGenerator action.
   *
   * @covers \Drupal\islandora\Plugin\RulesAction\UpdateEventGenerator::execute
   */
  public function testExecute() {
    // Execute the action and get the message it outputs.
    $msg = $this->assertExecution();

    // Assert it's outputs a 'Update' event.
    $this->assertTrue($msg["type"] == "Update", "Event must be of type 'Update'.");
  }

}
