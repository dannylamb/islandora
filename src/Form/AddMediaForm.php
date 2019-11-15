<?php

namespace Drupal\islandora\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\islandora\IslandoraUtils;
use Drupal\islandora\MediaSource\MediaSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Utility\Token;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Routing\RouteMatch;

/**
 * Form that lets users upload one or more files as children to a resource node.
 */
class AddMediaForm extends FormBase {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Media source service.
   *
   * @var \Drupal\islandora\MediaSource\MediaSourceService
   */
  protected $mediaSource;

  /**
   * Islandora utils.
   *
   * @var \Drupal\islandora\IslandoraUtils
   */
  protected $utils;

  /**
   * Islandora settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * Parent ID, cached to survive between batch operations.
   *
   * @var int
   */
  protected $parentId;

  /**
   * The route match object.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

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
    Connection $database
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
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_media_form';
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

    $form['upload'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#description' => $this->t("Upload a file for @title", ['@title' => $parent->getTitle()]),
      '#upload_location' => $upload_location,
      '#upload_validators' => [
        'file_validate_extensions' => [$valid_extensions],
      ],
      '#required' => TRUE,
    ];

    $options = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree('islandora_media_use', 0, NULL, TRUE);
    foreach ($terms as $term) {
      $options[$this->utils->getUriForTerm($term)] = $term->getName();
    };
    $form['use'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Usage'),
      '#description' => $this->t("Defined by Portland Common Data Model: Use Extension https://pcdm.org/2015/05/12/use. ''Original File'' will trigger creation of derivatives."),
      '#options' => $options,
      '#required' => TRUE,
    ];

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
    $parent_id = $this->routeMatch->getParameter('node');
    $parent = $this->entityTypeManager->getStorage('node')->load($parent_id);

    $fid = $form_state->getValue('upload')[0];
    $external_uris = $form_state->getValue('use');

    // Since we make 2 different entities, do this in a transaction.
    $transaction = $this->database->startTransaction();

    try {
      $file = $this->entityTypeManager->getStorage('file')->load($fid);
      $file->setPermanent();
      $file->save();

      $parent = $this->entityTypeManager->getStorage('node')->load($parent_id);

      $mime = $file->getMimetype();
      $exploded_mime = explode('/', $mime);
      if ($exploded_mime[0] == 'image') {
        if (in_array($exploded_mime[1], ['tiff', 'jp2'])) {
          $media_type = 'file';
        }
        else {
          $media_type = 'image';
        }
      }
      elseif ($exploded_mime[0] == 'audio') {
        $media_type = 'audio';
      }
      elseif ($exploded_mime[0] == 'video') {
        $media_type = 'video';
      }
      else {
        $media_type = 'file';
      }
      $source_field = $this->mediaSource->getSourceFieldName($media_type);

      $terms = [];
      foreach ($external_uris as $uri) {
        $terms[] = $this->utils->getTermForUri($uri);
      }
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $media_type,
        'uid' => $this->account->id(),
        $source_field => $fid,
        'name' => $file->getFileName(),
        IslandoraUtils::MEDIA_USAGE_FIELD => $terms,
        IslandoraUtils::MEDIA_OF_FIELD => $parent,
      ]);
      $media->save();

      $form_state->setRedirect(
        'view.media_of.page_1',
        ['node' => $parent_id]
      );
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
   * Check if the user can create any "Islandora" media.
   *
   * @param \Drupal\Core\Routing\RouteMatch $route_match
   *   The current routing match.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Whether we can or can't show the "thing".
   */
  public function access(RouteMatch $route_match) {
    if ($this->utils->canCreateIslandoraEntity('media', 'media_type')) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

  /**
   * Utility function to check if user can create 'Islandora' entity types.
   */
  protected function canCreateIslandoraEntity($entity_type, $bundle_type) {
    $bundles = $this->entityTypeManager->getStorage($bundle_type)->loadMultiple();
    $access_control_handler = $this->entityTypeManager->getAccessControlHandler($entity_type);

    $allowed = [];
    foreach (array_keys($bundles) as $bundle) {
      // Skip bundles that aren't 'Islandora' types.
      if (!$this->utils->isIslandoraType($entity_type, $bundle)) {
        continue;
      }

      $access = $access_control_handler->createAccess($bundle, NULL, [], TRUE);
      if (!$access->isAllowed()) {
        continue;
      }

      return TRUE;
    }

    return FALSE;
  }

}
