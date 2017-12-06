<?php

namespace Drupal\islandora\Plugin\Condition;

use Drupal\Core\Condition\ConditionPluginBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Media bundle' condition.
 *
 * @Condition(
 *   id = "media_bundle",
 *   label = @Translation("Media Bundle"),
 *   context = {
 *     "media" = @ContextDefinition("entity:media", label = @Translation("Media"))
 *   }
 * )
 */
class MediaBundle extends ConditionPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $entityStorage;

  /**
   * Creates a new NodeType instance.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entity_storage
   *   The entity storage.
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(EntityStorageInterface $entity_storage, array $configuration, $plugin_id, $plugin_definition) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityStorage = $entity_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity.manager')->getStorage('media_bundle'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = [];
    $bundles = $this->entityStorage->loadMultiple();
    foreach ($bundles as $bundle) {
      $options[$bundle->id()] = $bundle->label();
    }
    $form['bundles'] = [
      '#title' => $this->t('Bundles'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#default_value' => $this->configuration['bundles'],
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['bundles'] = array_filter($form_state->getValue('bundles'));
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function summary() {
    if (count($this->configuration['bundles']) > 1) {
      $bundles = $this->configuration['bundles'];
      $last = array_pop($bundles);
      $bundles = implode(', ', $bundles);
      return $this->t('The media bundle is @bundles or @last', ['@bundles' => $bundles, '@last' => $last]);
    }
    $bundle = reset($this->configuration['bundles']);
    return $this->t('The media bundle is @bundle', ['@bundle' => $bundle]);
  }

  /**
   * {@inheritdoc}
   */
  public function evaluate() {
    if (empty($this->configuration['bundles']) && !$this->isNegated()) {
      return TRUE;
    }
    $media = $this->getContextValue('media');
    return !empty($this->configuration['bundles'][$media->bundle()]);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['bundles' => []] + parent::defaultConfiguration();
  }

}
