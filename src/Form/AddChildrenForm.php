<?php

namespace Drupal\islandora\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatch;
use Drupal\islandora\IslandoraUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Drupal\Core\Utility\Token;

/**
 * Form that lets users upload one or more files as children to a resource node.
 */
class AddChildrenForm extends AddMediaForm {

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

    $fids = $form_state->getValue('upload');

    $operations = [];
    foreach ($fids as $fid) {
      $operations[] = [[$this, 'buildNodeForFile'], [$fid, $parent_id]];
    }

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
   * @param array $context
   *   Batch context.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function buildNodeForFile($fid, $parent_id, array &$context) {
    // Since we make 3 different entities, do this in a transaction.
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
        $model = $this->utils->getTermForUri('http://purl.org/coar/resource_type/c_c513');
      }
      elseif ($exploded_mime[0] == 'audio') {
        $media_type = 'audio';
        $model = $this->utils->getTermForUri('http://purl.org/coar/resource_type/c_18cc');
      }
      elseif ($exploded_mime[0] == 'video') {
        $media_type = 'video';
        $model = $this->utils->getTermForUri('http://purl.org/coar/resource_type/c_12ce');
      }
      else {
        $media_type = 'file';
        if ($mime == 'application/pdf') {
          $model = $this->utils->getTermForUri('https://schema.org/DigitalDocument');
        }
        else {
          $model = $this->utils->getTermForUri('http://purl.org/coar/resource_type/c_1843');
        }
      }
      $source_field = $this->mediaSource->getSourceFieldName($media_type);

      $node = $this->entityTypeManager->getStorage('node')->create([
        'type' => $this->config->get(IslandoraSettingsForm::UPLOAD_FORM_BUNDLE),
        'title' => $file->getFileName(),
        IslandoraUtils::MODEL_FIELD => $model,
        IslandoraUtils::MEMBER_OF_FIELD => $parent,
        'uid' => $this->account->id(),
        'status' => 1,
      ]);
      $node->save();

      $uri = $this->config->get(IslandoraSettingsForm::UPLOAD_FORM_TERM);
      $term = $this->utils->getTermForUri($uri);
      $media = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $media_type,
        $source_field => $fid,
        'name' => $file->getFileName(),
        IslandoraUtils::MEDIA_USAGE_FIELD => $term,
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
