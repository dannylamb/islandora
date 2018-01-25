<?php

namespace Drupal\islandora\Controller;

use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\islandora\IslandoraUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestException;

/**
 * Handles File CRUD with binaries for Media associated with a Node.
 *
 * @package Drupal\islandora\Controller
 */
class RelatedMediaController {

  protected $utils;

  public function __construct(
    IslandoraUtils $utils,
  ) {
    $this->utils = $utils;
  }

  public function put(
    NodeInterface $node,
    $field_id,
    Request $request
  ) {
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


