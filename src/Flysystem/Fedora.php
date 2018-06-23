<?php

namespace Drupal\islandora\Flysystem;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\flysystem\Plugin\FlysystemPluginInterface;
use Drupal\flysystem\Plugin\FlysystemUrlTrait;
use Drupal\islandora\Flysystem\Adapter\FedoraAdapter;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Client;
use Islandora\Chullo\IFedoraApi;
use Islandora\Chullo\FedoraApi;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drupal plugin for the Fedora Flysystem adapter.
 *
 * @Adapter(id = "fedora")
 */
class Fedora implements FlysystemPluginInterface, ContainerFactoryPluginInterface {

  use FlysystemUrlTrait;

  protected $fedora;

  /**
   * Constructs a Fedora plugin for Flysystem.
   *
   * @param \Islandora\Chullo\IFedoraApi $fedora
   *   Fedora client.
   */
  public function __construct(
    IFedoraApi $fedora
  ) {
    $this->fedora = $fedora;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    // Construct Authorization header using jwt token.
    $jwt = $container->get('jwt.authentication.jwt');
    $auth = 'Bearer ' . $jwt->generateToken();

    // Construct guzzle client to middleware that adds the header.
    $stack = HandlerStack::create();
    $stack->push(static::addHeader('Authorization', $auth));
    $client = new Client([
      'handler' => $stack,
      'base_uri' => $configuration['root'],
    ]);
    $fedora = new FedoraApi($client); 

    // Return it.
    return new static(
      $fedora
    );
  }

  /**
   * Guzzle middleware to add a header to outgoing requests.
   *
   * @param string $header
   *   Header name.
   * @param string $value
   *   Header value.
   */
  public static function addHeader($header, $value) {
    return function (callable $handler) use ($header, $value) {
      return function (
        RequestInterface $request,
        array $options
      ) use ($handler, $header, $value) {
        $request = $request->withHeader($header, $value);
        return $handler($request, $options);
      };
    };
  }

  /**
   * {@inheritdoc}
   */
  public function getAdapter() {
    return new FedoraAdapter($this->fedora);
  }

  /**
   * {@inheritdoc}
   */
  public function ensure($force = FALSE) {
    // Check fedora root for sanity.
    $response = $this->fedora->getResourceHeaders('');

    if ($response->getStatusCode() != 200) {
      return [[
        'severity' => RfcLogLevel::ERROR,
        'message' => '%url returned %status',
        'context' => [
          '%url' => $this->fedora->getBaseUri(),
          '%status' => $response->getStatusCode(),
        ],
      ]];
    }

    return [];
  }

}
