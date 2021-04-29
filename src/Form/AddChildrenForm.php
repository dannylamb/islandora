<?php

namespace Drupal\islandora\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Token;
use Drupal\islandora\IslandoraUtils;
use Drupal\islandora\MediaSource\MediaSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Form that lets users upload one or more files as children to a resource node.
 */
class AddChildrenForm extends AddMediaForm {

  /**
   * To list the available bundle types.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new IslandoraUploadForm object.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    IslandoraUtils $utils,
    MediaSourceService $media_source,
    ImmutableConfig $config,
    Token $token,
    AccountInterface $account,
    RouteMatchInterface $route_match,
    Connection $database,
    EntityTypeBundleInfoInterface $entity_type_bundle_info
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->utils = $utils;
    $this->mediaSource = $media_source;
    $this->config = $config;
    $this->token = $token;
    $this->account = $account;
    $this->routeMatch = $route_match;
    $this->database = $database;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('islandora.utils'),
      $container->get('islandora.media_source_service'),
      $container->get('config.factory')->get('islandora.settings'),
      $container->get('token'),
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('database'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_children_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $upload_pattern = $this->config->get(IslandoraSettingsForm::UPLOAD_FORM_LOCATION);
    $upload_location = $this->token->replace($upload_pattern);

    $valid_extensions = $this->config->get(IslandoraSettingsForm::UPLOAD_FORM_ALLOWED_MIMETYPES);

    $this->parentId = $this->routeMatch->getParameter('node');
    $parent = $this->entityTypeManager->getStorage('node')->load($this->parentId);

    // File upload widget.
    $form['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Files'),
      '#description' => $this->t("Upload one or more files to add children to @title", ['@title' => $parent->getTitle()]),
      '#upload_location' => $upload_location,
      '#upload_validators' => [
        'file_validate_extensions' => [$valid_extensions],
      ],
      '#multiple' => TRUE,
    ];

    // Drop down to select content type.
    $options = [];
    foreach ($this->entityTypeBundleInfo->getBundleInfo('node') as $bundle_id => $bundle) {
      if ($this->utils->isIslandoraType('node', $bundle_id)) {
        $options[$bundle_id] = $bundle['label'];
      }
    };
    $form['bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Content type'),
      '#description' => $this->t('Each child created will have this content type.'),
      '#options' => $options,
      '#required' => TRUE,
    ];

    // Filter out bundles that don't have field_model.
    $bundles_with_model = [];
    foreach (array_keys($options) as $bundle) {
      $fields = $this->entityFieldManager->getFieldDefinitions('node', $bundle);
      if (isset($fields[IslandoraUtils::MODEL_FIELD])) {
        $bundles_with_model[] = $bundle;
      }
    }

    // Model drop down.
    // Only shows up if the selected bundle has field_model.
    $options = [];
    foreach ($this->entityTypeManager->getStorage('taxonomy_term')->loadTree('islandora_models', 0, NULL, TRUE) as $term) {
      $options[$term->id()] = $term->getName();
    };
    $form['model'] = [
      '#type' => 'select',
      '#title' => $this->t('Model'),
      '#description' => $this->t('Each child will be tagged with this model.'),
      '#options' => $options,
      '#states' => [
        'visible' => [],
        'required' => [],
      ],
    ];
    if (!empty($bundles_with_model)) {
      foreach ($bundles_with_model as $bundle) {
        $form['model']['#states']['visible'][] = [':input[name="bundle"]' => ['value' => $bundle]];
        $form['model']['#states']['required'][] = [':input[name="bundle"]' => ['value' => $bundle]];
      }
    }

    $this->addMediaUseTerms($form);

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the parent.
    $parent_id = $this->routeMatch->getParameter('node');
    $parent = $this->entityTypeManager->getStorage('node')->load($parent_id);

    // Hack values out of the form.
    $fids = $form_state->getValue('upload');
    $bundle = $form_state->getValue('bundle');
    $model_tid = $form_state->getValue('model');
    $use_tids = $form_state->getValue('use');

    // Create an operation for each uploaded file.
    $operations = [];
    foreach ($fids as $fid) {
      $operations[] = [
        [$this, 'buildNodeForFile'],
        [$fid, $parent_id, $bundle, $model_tid, $use_tids],
      ];
    }

    // Set up and trigger the batch.
    $batch = [
      'title' => $this->t("Uploading Children for @title", ['@title' => $parent->getTitle()]),
      'operations' => $operations,
      'progress_message' => t('Processed @current out of @total. Estimated time: @estimate.'),
      'error_message' => t('The process has encountered an error.'),
      'finished' => [$this, 'buildNodeFinished'],
    ];
    batch_set($batch);
  }

  /**
   * Wires up a file/media/node combo for a file upload.
   *
   * @param int $fid
   *   Uploaded file id.
   * @param int $parent_id
   *   Id of the parent node.
   * @param string $bundle
   *   Content type to create.
   * @param int|null $model_tid
   *   Id of the Model term.
   * @param int[] $use_tids
   *   Ids of the Media Use terms.
   * @param array $context
   *   Batch context.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function buildNodeForFile($fid, $parent_id, $bundle, $model_tid, array $use_tids, array &$context) {
    // Since we make 3 different entities, do this in a transaction.
    $transaction = $this->database->startTransaction();

    try {
      // Set the file to permanent.
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      $file->setPermanent();
      $file->save();

      // Make the resource node.
      $parent = $this->entityTypeManager->getStorage('node')->load($parent_id);
      $media_type = $this->guessMediaTypeFromMimetype($file->getMimetype());
      $source_field = $this->mediaSource->getSourceFieldName($media_type);

      $node = $this->entityTypeManager->getStorage('node')->create([
        'type' => $bundle,
        'title' => $file->getFileName(),
        IslandoraUtils::MEMBER_OF_FIELD => $parent,
        'uid' => $this->account->id(),
        'status' => 1,
      ]);
      if ($model_tid) {
        $node->set(
          IslandoraUtils::MODEL_FIELD,
          $this->entityTypeManager->getStorage('taxonomy_term')->load($model_tid)
        );
      }
      $node->save();

      // Make a media for the uploaded file and assign it to the resource node.
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $media_type,
        $source_field => $fid,
        'name' => $file->getFileName(),
        IslandoraUtils::MEDIA_USAGE_FIELD => $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($use_tids),
        IslandoraUtils::MEDIA_OF_FIELD => $node,
      ]);
      $media->save();
    }
    catch (HttpException $e) {
      $transaction->rollBack();
      throw $e;
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw new HttpException(500, $e->getMessage());
    }
  }

  /**
   * Batch finished callback.
   *
   * $success bool
   *   Success status
   * $results mixed
   *   The 'results' from the batch context.
   * $operations array
   *   Remaining operations.
   */
  public function buildNodeFinished($success, $results, $operations) {
    return new RedirectResponse(
      Url::fromRoute('view.manage_members.page_1', ['node' => $this->parentId])->toString()
    );
  }

  /**
   * Check if the user can create any "Islandora" nodes and media.
   *
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   The current routing match.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Whether we can or can't show the "thing".
   */
  public function access(RouteMatch $route_match) {
    $can_create_media = $this->utils->canCreateIslandoraEntity('media', 'media_type');
    $can_create_node = $this->utils->canCreateIslandoraEntity('node', 'node_type');

    if ($can_create_media && $can_create_node) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
