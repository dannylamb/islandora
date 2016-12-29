<?php

namespace Drupal\Tests\islandora\Kernel;

use Drupal\islandora\EventGenerator\EventGenerator;

/**
 * Tests the EventGenerator default implementation.
 *
 * @group islandora
 * @coversDefaultClass \Drupal\islandora\EventGenerator\EventGenerator
 */
class EventGeneratorTest extends EventGeneratorTestBase {

  /**
   * The EventGenerator to test.
   *
   * @var \Drupal\islandora\EventGenerator\EventGeneratorInterface
   */
  protected $eventGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create the event generator so we can test it.
    $this->eventGenerator = new EventGenerator();
  }

  /**
   * Tests the generateCreateEvent() method.
   *
   * @covers \Drupal\islandora\EventGenerator\EventGenerator::generateCreateEvent
   */
  public function testGenerateCreateEvent() {
    $json = $this->eventGenerator->generateCreateEvent($this->entity, $this->user);
    $msg = json_decode($json, TRUE);

    $this->assertBasicStructure($msg);
    $this->assertTrue($msg["type"] == "Create", "Event must be of type 'Create'.");
  }

  /**
   * Tests the generateUpdateEvent() method.
   *
   * @covers \Drupal\islandora\EventGenerator\EventGenerator::generateUpdateEvent
   */
  public function testGenerateUpdateEvent() {
    $json = $this->eventGenerator->generateUpdateEvent($this->entity, $this->user);
    $msg = json_decode($json, TRUE);

    $this->assertBasicStructure($msg);
    $this->assertTrue($msg["type"] == "Update", "Event must be of type 'Update'.");
  }

  /**
   * Tests the generateDeleteEvent() method.
   *
   * @covers \Drupal\islandora\EventGenerator\EventGenerator::generateDeleteEvent
   */
  public function testGenerateDeleteEvent() {
    $json = $this->eventGenerator->generateDeleteEvent($this->entity, $this->user);
    $msg = json_decode($json, TRUE);

    $this->assertBasicStructure($msg);
    $this->assertTrue($msg["type"] == "Delete", "Event must be of type 'Delete'.");
  }

  /**
   * Util function for repeated checks.
   *
   * @param array $msg
   *   The message parsed as an array.
   */
  protected function assertBasicStructure(array $msg) {
    // Looking for @context.
    $this->assertTrue(array_key_exists('@context', $msg), "Context key exists");
    $this->assertTrue($msg["@context"] == "https://www.w3.org/ns/activitystreams", "Context must be activity stream.");

    // Make sure it has a type.
    $this->assertTrue(array_key_exists('type', $msg), "Message must have 'type' key.");

    // Make sure the actor exists, is a person, and has a uri.
    $this->assertTrue(array_key_exists('actor', $msg), "Message must have 'actor' key.");
    $this->assertTrue(array_key_exists("type", $msg["actor"]), "Actor must have 'type' key.");
    $this->assertTrue($msg["actor"]["type"] == "Person", "Actor must be a 'Person'.");
    $this->assertTrue(array_key_exists("id", $msg["actor"]), "Actor must have 'id' key.");
    $this->assertTrue($msg["actor"]["id"] == $this->user->toUrl()->setAbsolute()->toString(), "Id must be a user's uri");

    // Make sure the object exists and is a uri.
    $this->assertTrue(array_key_exists('object', $msg), "Message must have 'object' key.");
    $this->assertTrue($msg["object"] == $this->entity->toUrl()->setAbsolute()->toString(), "Object must be an entity uri.");
  }

}
