<?php

namespace Drupal\islandora\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\media_entity\MediaInterface;
use Drupal\islandora\MediaSource\MediaSourceService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class MediaSourceController.
 *
 * @package Drupal\islandora\Controller
 */
class MediaSourceController extends ControllerBase {

  /**
   * Service for business logic.
   *
   * @var \Drupal\islandora\MediaSource\MediaSourceService
   */
  protected $service;

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * MediaSourceController constructor.
   *
   * @param \Drupal\islandora\MediaSource\MediaSourceService $service
   *   Service for business logic.
   * @param \Drupal\Core\Database\Connection $database
   *   Database connection.
   */
  public function __construct(
    MediaSourceService $service,
    Connection $database
  ) {
    $this->service = $service;
    $this->database = $database;
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
      $container->get('islandora.media_source_service'),
      $container->get('database')
    );
  }

  /**
   * Updates a source file for a Media.
   *
   * @param \Drupal\media_entity\MediaInterface $media
   *   The media whose source file you want to update.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   204 on success.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function put(MediaInterface $media, Request $request) {
    // Since we update both the Media and its File, do this in a transaction.
    $transaction = $this->database->startTransaction();

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
      $transaction->rollBack();
      throw $e;
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw new HttpException(500, $e->getMessage());
    }
  }

}
