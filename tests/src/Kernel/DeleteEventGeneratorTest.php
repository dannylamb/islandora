<?php

namespace Drupal\Tests\islandora\Kernel;

/**
 * Tests the basic behavior of the DeleteEventGenerator rules action.
 *
 * @group islandora
 * @coversDefaultClass \Drupal\islandora\Plugin\RulesAction\DeleteEventGenerator
 */
class DeleteEventGeneratorTest extends EventGeneratorActionTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Get an instance of the action.
    $this->action = $this->actionManager->createInstance('islandora_delete_event_generator');
  }

  /**
   * Tests the DeleteEventGenerator action.
   *
   * @covers \Drupal\islandora\Plugin\RulesAction\DeleteEventGenerator::execute
   */
  public function testExecute() {
    // Execute the action and get the message it outputs.
    $msg = $this->assertExecution();

    // Assert it's outputs a 'Delete' event.
    $this->assertTrue($msg["type"] == "Delete", "Event must be of type 'Delete'.");
  }

}
