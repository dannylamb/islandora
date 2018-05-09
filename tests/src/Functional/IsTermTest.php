<?php

namespace Drupal\Tests\islandora\Functional;

/**
 * Tests the IsTerm condition.
 *
 * @group islandora
 */
class IsTermTest extends IslandoraFunctionalTestBase {

  /**
   * @covers \Drupal\islandora\ContextProvider\NodeContextProvider::__construct
   * @covers \Drupal\islandora\ContextProvider\NodeContextProvider::getRuntimeContexts
   * @covers \Drupal\islandora\ContextProvider\MediaContextProvider::__construct
   * @covers \Drupal\islandora\ContextProvider\MediaContextProvider::getRuntimeContexts
   * @covers \Drupal\islandora\IslandoraContextManager::evaluateContexts
   * @covers \Drupal\islandora\IslandoraContextManager::applyContexts
   * @covers \Drupal\islandora\Plugin\Condition\IsTerm::evaluate
   * @covers \Drupal\islandora\PresetReaction\PresetReaction::buildConfigurationForm
   * @covers \Drupal\islandora\PresetReaction\PresetReaction::submitConfigurationForm
   * @covers \Drupal\islandora\PresetReaction\PresetReaction::execute
   * @covers \Drupal\islandora\IslandoraServiceProvider::alter
   */
  public function testIsTerm() {
    // Create a test user.
    $account = $this->drupalCreateUser([
      'bypass node access',
      'administer contexts',
      'administer taxonomy',
    ]);
    $this->drupalLogin($account);

    $this->createContext('Test', 'test');
    $this->addCondition('test', 'is_term');
    $this->addPresetReaction('test', 'index', 'hello_world');

    // Create a new term and confirm Hello World! is printed to the screen.
    $this->drupalPostForm(
      'admin/structure/taxonomy/manage/' . $this->testVocabulary->id() . '/add',
      ['name[0][value]' => 'Test Term'],
      t('Save')
    );
    $this->assertSession()->pageTextContains("Hello World!");

    // Create a new node and confirm Hello World! is not printed to the screen.
    $this->postNodeAddForm('test_type', ['title[0][value]' => 'Test Node'], 'Save');
    $this->assertSession()->pageTextNotContains("Hello World!");
  }

}
