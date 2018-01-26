<?php

namespace Drupal\islandora\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\media_entity\MediaInterface;
use Drupal\islandora\MediaSource\MediaSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class MediaSourceController.
 *
 * @package Drupal\islandora\Controller
 */
class MediaSourceController extends ControllerBase {

  /**
   * Islandora utility functions.
   *
   * @var \Drupal\islandora\MediaSource\MediaSourceService
   */
  protected $service;

  /**
   * MediaSourceController constructor.
   *
   * @param \Drupal\islandora\MediaSource\MediaSourceService $service
   *   Service for business logic.
   */
  public function __construct(
    MediaSourceService $service
  ) {
    $this->service = $service;
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
      $container->get('islandora.media_source_service')
    );
  }

  public function put(MediaInterface $media, Request $request) {
    try {
      $content_type = $request->headers->get('Content-Type', "");

      if (empty($content_type)) {
        throw new BadRequestHttpException("Missing Content-Type header");
      }

      $content_length = $request->headers->get('Content-Length', 0);

      if ($content_length <= 0) {
        throw new BadRequestHttpException("Missing Content-Length");
      }
      
      $content_disposition = $request->headers->get('Content-Disposition', "");

      if (empty($content_disposition)) {
        throw new BadRequestHttpException("Missing Content-Disposition header");
      }

      $matches = [];
      if (!preg_match('/attachment; filename="(.*)"/', $content_disposition, $matches)) {
        throw new BadRequestHttpException("Malformed Content-Disposition header");
      }

      $filename = $matches[1];

      $this->service->updateSourceField(
        $media,
        $request->getContent(TRUE),
        $content_type,
        $content_length,
        $filename
      );

      return new Response("", 204);

    }
    catch (HttpException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      throw new HttpException(500, $e->getMessage());
    }
  }
}
