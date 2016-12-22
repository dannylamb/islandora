<?php

namespace Drupal\islandora\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora\IslandoraSettings\CONFIG;
use Drupal\islandora\IslandoraSettings\FORM_ID;
use Drupal\islandora\IslandoraSettings\BROKER_URL;
use Drupal\islandora\IslandoraSettings\FEDORA_REST_ENDPOINT;

/**
 * Config form for Islandora settings.
 */
class IslandoraSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      CONFIG,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(CONFIG);

    $form[BROKER_URL] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Broker URL'),
      '#default_value' => $config->get(BROKER_URL),
    );

    $form[FEDORA_REST_ENDPOINT] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Fedora REST Endpoint'),
      '#description' => $this->t('The URL for your Fedora instance.'),
      '#default_value' => $config->get(FEDORA_REST_ENDPOINT),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(CONFIG)
      ->set(BROKER_URL, $form_state->getValue(BROKER_URL))
      ->set(FEDORA_REST_ENDPOINT, $form_state->getValue(FEDORA_REST_ENDPOINT))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
