<?php

namespace Drupal\islandora\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\islandora\IslandoraUtils;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Handles File CRUD with binaries.
 *
 * @package Drupal\islandora\Controller
 */
class FileController {

  /**
   * Returns an JSON-LD Context for a entity bundle.
   *
   * @param \Drupal\file\FileInterface $file
   *   The requested file.
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
  public function get(FileInterface $file) {
    try {
      $uri = $file->getFileUri();
      list($scheme, $target) = explode('://', $uri);
      $response = new BinaryFileResponse($uri, 200, [], $scheme == 'public');
      $response->headers->set('Content-Type', $file->getMimetype());
      $response->headers->set('Content-Disposition', "attachment; filename={$file->getFilename()}");
      return $response;
    }
    catch (HttpException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $message = $e->getMessage();
      $status = $e->getStatusCode();
      return new Response($message, $status ? $status : 500);
    }
  }

  public function post(Request $request) {
    try {
      $content_type = $request->headers->get('Content-Type', "");

      if (empty($content_type)) {
        throw new BadRequestException("Missing Content-Type header");
      }

      $content_disposition = $request->headers->get('Content-Disposition', "");

      if (empty($content_disposition)) {
        throw new BadRequestException("Missing Content-Disposition header");
      }

      $matches = [];
      if (!preg_match('/attachment; filename="(.*)"/', $content_disposition, $matches)) {
        throw new BadRequestException("Malformed Content-Disposition header");
      }
      $filename = $matches[1];

      $file = file_save_data($request->getContent()); 
      $file->setFilename($filename);
      $file->setMimeType($content_type);
      $file->save();

      $response = new Response("", 201);
      $response->headers->set(
        "Location",
        Url::fromRoute('islandora.file.download', ['file' => $file->id()], ['absolute' => TRUE])->toString()
      );

      return $response;
    }
    catch (HttpException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $message = $e->getMessage();
      $status = $e->getStatusCode();
      return new Response($message, $status ? $status : 500);
    }
  }

  public function put(FileInterface $file, Request $request) {
    try {
      $content_type = $request->headers->get('Content-Type', "");

      if (empty($content_type)) {
        throw new BadRequestException("Missing Content-Type header");
      }

      $content_disposition = $request->headers->get('Content-Disposition', "");

      if (empty($content_disposition)) {
        throw new BadRequestException("Missing Content-Disposition header");
      }

      $matches = [];
      if (!preg_match('/attachment; filename="(.*)"/', $content_disposition, $matches)) {
        throw new BadRequestException("Malformed Content-Disposition header");
      }
      $filename = $matches[1];

      $file = file_save_data($request->getContent(), $file->getFileUri(), FILE_EXISTS_REPLACE); 
      $file->setFilename($filename);
      $file->setMimeType($content_type);
      $file->save();

      $response = new Response("", 204);

      return $response;
    }
    catch (HttpException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $message = $e->getMessage();
      $status = $e->getStatusCode();
      return new Response($message, $status ? $status : 500);
    }
  }
}

