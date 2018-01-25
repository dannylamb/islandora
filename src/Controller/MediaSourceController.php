<?php

namespace Drupal\islandora\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\media_entity\MediaInterface;
use Drupal\system\FileDownloadController;
use Drupal\islandora\IslandoraUtils;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MediaSourceController.
 *
 * @package Drupal\islandora\Controller
 */
class MediaSourceController extends FileDownloadController {

  /**
   * Islandora utility functions.
   *
   * @var \Drupal\islandora\IslandoraUtils
   */
  protected $utils;

  /**
   * MediaSourceController constructor.
   *
   * @param \Drupal\islandora\IslandoraUtils $utils
   *   Islandora utility functions.
   */
  public function __construct(
    IslandoraUtils $utils
  ) {
    $this->utils = $utils;
  }

  /**
   * Controller's create method for dependecy injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The App Container.
   *
   * @return \Drupal\islandora\Controller\MediaSourceController
   *   Controller instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('islandora.utils')
    );
  }

  /**
   * Returns an JSON-LD Context for a entity bundle.
   *
   * @param \Drupal\media_entity\MediaInterface $media
   *   The media whose source is being requested.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Symfony Http Request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An Http response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Thrown when the requested file does not exist.
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the file.
   */
  public function get(MediaInterface $media, Request $request) {
    try {
      $source_field = $this->utils->getSourceField($media->bundle());

      if (empty($source_field)) {
        throw new NotFoundHttpException();
      }

      $files = $media->get($source_field)->referencedEntities();

      if (empty($files)) {
        throw new NotFoundHttpException();
      };

      $file = reset($files); 
      list($scheme, $target) = explode('://', $file->getFileUri());
      $request->query->set('file', $target);
      return $this->download($request, $scheme);
    }
    catch (NotFoundHttpException $e) {
      throw $e;
    }
    catch (AccessDeniedException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $message = $e->getMessage();
      $status = $e->getStatusCode();
      return new Response($message, $status ? $status : 500);
    }
  }

  public function put(MediaInterface $media, Request $request) {
    try {
      $content_type = $request->headers->get('Content-Type', "");

      if (empty($content_type)) {
        return new Response("Missing Content-Type header.", 400);
      }

      $source_field = $this->utils->getSourceField($media->bundle());

      if (empty($source_field)) {
        throw new NotFoundHttpException();
      }

      $files = $media->get($source_field)->referencedEntities();

      if (empty($files)) {
        $content_disposition = $request->headers->get('Content-Disposition', "");

        if (empty($content_disposition)) {
          return new Response("Missing Content-Disposition header.", 400);
        }

        $matches = [];
        if (!preg_match('/attachment; filename="(.*)"/', $content_disposition, $matches)) {
          return new Response("Malformed Content-Disposition header.", 400);
        }

        $filename = $matches[1];
        $scheme = file_default_scheme();
        $destination = "$scheme://$filename";

        $file = file_save_data($request->getContent(), $destination, FILE_EXISTS_REPLACE); 
        $media->set($source_field, $file);
        $media->save();

        return new Response("", 201);
      }
      else {
        $file = reset($files);
        file_unmanaged_save_data($request->getContent(), $file->getFileUri(), FILE_EXISTS_REPLACE);
        return new Response("", 204);
      }
    }
    catch (\Exception $e) {
      $message = $e->getMessage();
      $status = $e->getStatusCode();
      return new Response($message, $status ? $status : 500);
    }
  }
}
