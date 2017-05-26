<?php

namespace Drupal\Tests\islandora\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\simpletest\UserCreationTrait;

/**
 * Base class for testing EventGenerator functionality.
 */
abstract class EventGeneratorTestBase extends IslandoraKernelTestBase {

  use UserCreationTrait;

  /**
   * User entity.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Fedora resource entity.
   *
   * @var \Drupal\node\Entity\NodeInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Create a test user.
    $this->user = $this->createUser(['add fedora resource entities']);

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

}
