<?php

namespace Drupal\Tests\islandora\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simpletest\UserCreationTrait;

/**
 * Tests the basic behavior of a vector clock.
 *
 * @group islandora
 * @coversDefaultClass \Drupal\islandora\VersionCounter\VersionCounter
 */
class VersionCounterTest extends IslandoraKernelTestBase {

  use UserCreationTrait;

  /**
   * Fedora resource entity.
   *
   * @var \Drupal\node\Entity\NodeInterface
   */
  protected $entity;

  /**
   * User entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a test user.
    $this->user = $this->createUser(['administer nodes']);

    $test_type = NodeType::create([
      'type' => 'test_type',
      'label' => 'Test Type',
    ]);
    $test_type->save();

    // Create a test entity.
    $this->entity = Node::create([
      "type" => "test_type",
      "uid" => $this->user->get('uid'),
      "title" => "Test Fixture",
      "langcode" => "und",
      "status" => 1,
    ]);
    $this->entity->save();
  }

  /**
   * @covers \Drupal\islandora\VersionCounter\VersionCounter::create
   * @covers \Drupal\islandora\VersionCounter\VersionCounter::get
   */
  public function testInitializesRecord() {
    $versionCounter = $this->container->get('islandora.versioncounter');

    $this->assertTrue($versionCounter->get($this->entity->uuid()) == 0,
      "Version counter db record must be initialized to 0."
    );
  }

  /**
   * @covers \Drupal\islandora\VersionCounter\VersionCounter::create
   * @expectedException \Drupal\Core\Database\IntegrityConstraintViolationException
   */
  public function testCannotCreateDuplicateRecord() {
    $versionCounter = $this->container->get('islandora.versioncounter');
    $versionCounter->create($this->entity->uuid());
  }

  /**
   * @covers \Drupal\islandora\VersionCounter\VersionCounter::increment
   * @covers \Drupal\islandora\VersionCounter\VersionCounter::get
   */
  public function testRecordIncrementsOnUpdate() {
    $this->entity->setTitle("New Title");
    $this->entity->save();

    $versionCounter = $this->container->get('islandora.versioncounter');

    $this->assertTrue($versionCounter->get($this->entity->uuid()) == 1,
      "Version counter db record must increment on entity update."
    );
  }

  /**
   * @covers \Drupal\islandora\VersionCounter\VersionCounter::delete
   * @covers \Drupal\islandora\VersionCounter\VersionCounter::get
   */
  public function testRecordsGetCleanedUp() {
    $this->entity->delete();

    $versionCounter = $this->container->get('islandora.versioncounter');

    $this->assertTrue($versionCounter->get($this->entity->uuid()) == -1,
      "Version counter db record must be deleted on entity delete."
    );
  }

}
