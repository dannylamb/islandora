<?php

namespace Drupal\Tests\islandora\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Abstract base class for Islandora kernel tests.
 */
abstract class IslandoraKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'block',
    'node',
    'path',
    'text',
    'options',
    'serialization',
    'rest',
    'basic_auth',
    'hal',
    'rdf',
    'action',
    'context',
    'jsonld',
    'views',
    'key',
    'jwt',
    'file',
    'taxonomy',
    'image',
    'media',
    'webform',
    'islandora',
    'flysystem',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Bootstrap minimal Drupal environment to run the tests.
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installSchema('file', 'file_usage');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('context');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig('filter');
  }

}
