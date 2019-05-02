<?php

namespace Drupal\Tests\islandora\Kernel;

use Drupal\Core\Entity\EntityInterface;
use Drupal\file\FileInterface;
use Drupal\media\MediaInterface;
use Drupal\islandora\GeminiLookup;
use Drupal\islandora\MediaSource\MediaSourceService;
use Drupal\jwt\Authentication\Provider\JwtAuth;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Islandora\Crayfish\Commons\Client\GeminiClient;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class GeminiLookupTest.
 *
 * @group islandora
 * @coversDefaultClass \Drupal\islandora\GeminiLookup
 */
class GeminiLookupTest extends IslandoraKernelTestBase {

  private $jwtAuth;

  private $logger;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    $prophecy = $this->prophesize(JwtAuth::class);
    $this->jwtAuth = $prophecy->reveal();

    $prophecy = $this->prophesize(LoggerInterface::class);
    $this->logger = $prophecy->reveal();
  }

  /**
   * @covers ::lookup
   * @covers ::__construct
   */
  public function testEntityNotSaved() {
    $prophecy = $this->prophesize(EntityInterface::class);
    $prophecy->id()->willReturn(NULL);
    $entity = $prophecy->reveal();

    $prophecy = $this->prophesize(GeminiClient::class);
    $gemini_client = $prophecy->reveal();

    $prophecy = $this->prophesize(MediaSourceService::class);
    $media_source = $prophecy->reveal();

    $prophecy = $this->prophesize(Client::class);
    $guzzle = $prophecy->reveal();

    $this->geminiLookup = new GeminiLookup(
        $gemini_client,
        $this->jwtAuth,
        $media_source,
        $guzzle,
        $this->logger
    );

    $this->assertEquals(NULL, $this->geminiLookup->lookup($entity));
  }

  /**
   * @covers ::lookup
   * @covers ::__construct
   */
  public function testEntityNotFound() {
    $prophecy = $this->prophesize(EntityInterface::class);
    $prophecy->id()->willReturn(1);
    $prophecy->getEntityTypeId()->willReturn('node');
    $prophecy->uuid()->willReturn('abc123');
    $entity = $prophecy->reveal();

    $prophecy = $this->prophesize(GeminiClient::class);
    $prophecy->getUrls(Argument::any(), Argument::any())
      ->willReturn([]);
    $gemini_client = $prophecy->reveal();

    $prophecy = $this->prophesize(MediaSourceService::class);
    $media_source = $prophecy->reveal();

    $prophecy = $this->prophesize(Client::class);
    $guzzle = $prophecy->reveal();

    $this->geminiLookup = new GeminiLookup(
        $gemini_client,
        $this->jwtAuth,
        $media_source,
        $guzzle,
        $this->logger
    );

    $this->assertEquals(NULL, $this->geminiLookup->lookup($entity));
  }

  /**
   * @covers ::lookup
   * @covers ::__construct
   */
  public function testEntityFound() {
    $prophecy = $this->prophesize(EntityInterface::class);
    $prophecy->id()->willReturn(1);
    $prophecy->getEntityTypeId()->willReturn('node');
    $prophecy->uuid()->willReturn('abc123');
    $entity = $prophecy->reveal();

    $prophecy = $this->prophesize(GeminiClient::class);
    $prophecy->getUrls(Argument::any(), Argument::any())
      ->willReturn(['drupal' => '', 'fedora' => 'http://localhost:8080/fcrepo/rest/abc123']);
    $gemini_client = $prophecy->reveal();

    $prophecy = $this->prophesize(MediaSourceService::class);
    $media_source = $prophecy->reveal();

    $prophecy = $this->prophesize(Client::class);
    $guzzle = $prophecy->reveal();

    $this->geminiLookup = new GeminiLookup(
        $gemini_client,
        $this->jwtAuth,
        $media_source,
        $guzzle,
        $this->logger
    );

    $this->assertEquals(
      'http://localhost:8080/fcrepo/rest/abc123',
      $this->geminiLookup->lookup($entity)
    );
  }

  /**
   * @covers ::lookup
   * @covers ::__construct
   */
  public function testMediaHasNoSourceFile() {
    $prophecy = $this->prophesize(MediaInterface::class);
    $prophecy->id()->willReturn(1);
    $prophecy->getEntityTypeId()->willReturn('media');
    $prophecy->uuid()->willReturn('abc123');
    $entity = $prophecy->reveal();

    $prophecy = $this->prophesize(GeminiClient::class);
    $gemini_client = $prophecy->reveal();

    $prophecy = $this->prophesize(MediaSourceService::class);
    $prophecy->getSourceFile(Argument::any())
      ->willThrow(new NotFoundHttpException("Media has no source"));
    $media_source = $prophecy->reveal();

    $prophecy = $this->prophesize(Client::class);
    $guzzle = $prophecy->reveal();

    $this->geminiLookup = new GeminiLookup(
        $gemini_client,
        $this->jwtAuth,
        $media_source,
        $guzzle,
        $this->logger
    );

    $this->assertEquals(NULL, $this->geminiLookup->lookup($entity));
  }

  /**
   * @covers ::lookup
   * @covers ::__construct
   */
  public function testMediaNotFound() {
    $prophecy = $this->prophesize(MediaInterface::class);
    $prophecy->id()->willReturn(1);
    $prophecy->getEntityTypeId()->willReturn('media');
    $prophecy->uuid()->willReturn('abc123');
    $entity = $prophecy->reveal();

    $prophecy = $this->prophesize(FileInterface::class);
    $prophecy->uuid()->willReturn('xyzpdq');
    $file = $prophecy->reveal();

    $prophecy = $this->prophesize(MediaSourceService::class);
    $prophecy->getSourceFile(Argument::any())
      ->willReturn($file);
    $media_source = $prophecy->reveal();

    $prophecy = $this->prophesize(GeminiClient::class);
    $prophecy->getUrls(Argument::any(), Argument::any())
      ->willReturn([]);
    $gemini_client = $prophecy->reveal();

    $prophecy = $this->prophesize(Client::class);
    $guzzle = $prophecy->reveal();

    $this->geminiLookup = new GeminiLookup(
        $gemini_client,
        $this->jwtAuth,
        $media_source,
        $guzzle,
        $this->logger
    );

    $this->assertEquals(NULL, $this->geminiLookup->lookup($entity));
  }

  /**
   * @covers ::lookup
   * @covers ::__construct
   */
  public function testFileFoundButNoDescribedby() {
    $prophecy = $this->prophesize(MediaInterface::class);
    $prophecy->id()->willReturn(1);
    $prophecy->getEntityTypeId()->willReturn('media');
    $prophecy->uuid()->willReturn('abc123');
    $entity = $prophecy->reveal();

    $prophecy = $this->prophesize(FileInterface::class);
    $prophecy->uuid()->willReturn('xyzpdq');
    $file = $prophecy->reveal();

    $prophecy = $this->prophesize(MediaSourceService::class);
    $prophecy->getSourceFile(Argument::any())
      ->willReturn($file);
    $media_source = $prophecy->reveal();

    $prophecy = $this->prophesize(GeminiClient::class);
    $prophecy->getUrls(Argument::any(), Argument::any())
      ->willReturn(['drupal' => '', 'fedora' => 'http://localhost:8080/fcrepo/rest/xyzpdq']);
    $gemini_client = $prophecy->reveal();

    $prophecy = $this->prophesize(Client::class);
    $prophecy->head(Argument::any(), Argument::any())
      ->willReturn(new Response(200, []));
    $guzzle = $prophecy->reveal();

    $this->geminiLookup = new GeminiLookup(
        $gemini_client,
        $this->jwtAuth,
        $media_source,
        $guzzle,
        $this->logger
    );

    $this->assertEquals(NULL, $this->geminiLookup->lookup($entity));
  }

  /**
   * @covers ::lookup
   * @covers ::__construct
   */
  public function testMediaFound() {
    $prophecy = $this->prophesize(MediaInterface::class);
    $prophecy->id()->willReturn(1);
    $prophecy->getEntityTypeId()->willReturn('media');
    $prophecy->uuid()->willReturn('abc123');
    $entity = $prophecy->reveal();

    $prophecy = $this->prophesize(FileInterface::class);
    $prophecy->uuid()->willReturn('xyzpdq');
    $file = $prophecy->reveal();

    $prophecy = $this->prophesize(MediaSourceService::class);
    $prophecy->getSourceFile(Argument::any())
      ->willReturn($file);
    $media_source = $prophecy->reveal();

    $prophecy = $this->prophesize(GeminiClient::class);
    $prophecy->getUrls(Argument::any(), Argument::any())
      ->willReturn(['drupal' => '', 'fedora' => 'http://localhost:8080/fcrepo/rest/xyzpdq']);
    $gemini_client = $prophecy->reveal();

    $prophecy = $this->prophesize(Client::class);
    $prophecy->head(Argument::any(), Argument::any())
      ->willReturn(new Response(200, ['Link' => '<http://localhost:8080/fcrepo/rest/xyzpdq/fcr:metadata>; rel="describedby"']));
    $guzzle = $prophecy->reveal();

    $this->geminiLookup = new GeminiLookup(
        $gemini_client,
        $this->jwtAuth,
        $media_source,
        $guzzle,
        $this->logger
    );

    $this->assertEquals(
      'http://localhost:8080/fcrepo/rest/xyzpdq/fcr:metadata',
      $this->geminiLookup->lookup($entity)
    );
  }

}
