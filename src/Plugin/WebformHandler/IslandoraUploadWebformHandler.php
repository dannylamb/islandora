<?php

namespace Drupal\islandora\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\Entity\Node;
use Drupal\islandora\MediaSource\MediaSourceService;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\webformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Create a node/media combo from a file upload.
 *
 * @WebformHandler(
 *   id = "islandora_upload",
 *   label = @Translation("Create a node/media combo."),
 *   category = @Translation("Content"),
 *   description = @Translation("Create a node/media combo from an uploaded file."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class IslandoraUploadWebformHandler extends WebformHandlerBase {

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Media source service.
   *
   * @var \Drupal\islandora\MediaSource\MediaSourceService
   */
  protected $mediaSource;

  /**
   * Cached copy of the node that gets created upon submission.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
                              $plugin_id,
                              $plugin_definition,
                              LoggerChannelFactoryInterface $logger_factory,
                              ConfigFactoryInterface $config_factory,
                              EntityTypeManagerInterface $entity_type_manager,
                              WebformSubmissionConditionsValidatorInterface $conditions_validator,
                              EntityFieldManagerInterface $entity_field_manager,
                              MediaSourceService $media_source) {

    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $logger_factory,
      $config_factory,
      $entity_type_manager,
      $conditions_validator
    );
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->mediaSource = $media_source;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('entity_field.manager'),
      $container->get('islandora.media_source_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Strip out content types that don't have the required fields.
    foreach ($form['elements']['content_type']['#options'] as $k => $v) {
      if (!$this->bundleHasField('node', $k, 'field_model')) {
        unset($form['elements']['content_type']['#options'][$k]);
      }
    }

    // Strip out media types that don't have the required fields.
    foreach ($form['elements']['media_type']['#options'] as $k => $v) {
      if (!$this->bundleHasField('media', $k, 'field_media_of') ||
          !$this->bundleHasField('media', $k, 'field_media_use')) {
        unset($form['elements']['media_type']['#options'][$k]);
      }
    }

  }

  /**
   * Utility function for filtering out bundles without the required fields.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle.
   * @param string $field_name
   *   Field name.
   *
   * @return bool
   *   TRUE if the bundle has the specified field.
   */
  protected function bundleHasField($entity_type, $bundle, $field_name) {
    $all_bundle_fields = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    return isset($all_bundle_fields[$field_name]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Get form field values.
    $submission_array = $webform_submission->getData();
    $file = $this->entityTypeManager->getStorage('file')->load($submission_array['file']);
    $model = $this->entityTypeManager->getStorage('taxonomy_term')->load($submission_array['model']);
    $use = $this->entityTypeManager->getStorage('taxonomy_term')->load($submission_array['media_use']);

    // Make the node.
    $this->node = $this->entityTypeManager->getStorage('node')->create([
      'type' => $submission_array['content_type'],
      'status' => TRUE,
      'title' => $file->getFileName(),
      'field_model' => [$model],
    ]);
    $this->node->save();

    // Make the media.
    $source_field = $this->mediaSource->getSourceFieldName($submission_array['media_type']);
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => $submission_array['media_type'],
      'status' => TRUE,
      'name' => $file->getFileName(),
      'field_media_use' => [$use],
      'field_media_of' => [$this->node],
      $source_field => [$file],
    ]);
    $media->save();
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Redirect the user to the node's edit form.
    $form_state->setRedirect('entity.node.edit_form', ['node' => $this->node->id()]);
  }

}
