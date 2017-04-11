<?php

namespace Drupal\islandora\Plugin\RulesAction;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\islandora\Form\IslandoraSettingsForm;
use Drupal\rules\Core\RulesActionBase;
use Drupal\jwt\Authentication\Provider\JwtAuth;
use Stomp\Exception\StompException;
use Stomp\StatefulStomp;
use Stomp\Transport\Message;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an action to broadcast an event to multiple queues/topics.
 *
 * @RulesAction(
 *   id = "islandora_broadcast",
 *   label = @Translation("Broadcast Message"),
 *   category = @Translation("Islandora"),
 *   context = {
 *     "message" = @ContextDefinition("string",
 *       label = @Translation("Message")
 *     ),
 *     "recipients" = @ContextDefinition("string",
 *       label = @Translation("Recipients"),
 *       description = @Translation("Queues/Topics to receive the message"),
 *       multiple = TRUE
 *     ),
 *   }
 * )
 */
class Broadcaster extends RulesActionBase implements ContainerFactoryPluginInterface {

  /**
   * Stomp client.
   *
   * @var \Stomp\StatefulStomp
   */
  protected $stomp;

  /**
   * Name of broadcast queue.
   *
   * @var string
   */
  protected $broadcastQueue;

  /**
   * The JWT Auth Service.
   *
   * @var \Drupal\jwt\Authentication\Provider\JwtAuth
   */
  protected $auth;

  /**
   * Constructs a BroadcastAction.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param string $broadcast_queue
   *   Name of queue that will handle distributing the broadcast.
   * @param \Stomp\StatefulStomp $stomp
   *   Stomp client.
   * @param \Drupal\jwt\Authentication\Provider\JwtAuth $auth
   *   JWT Auth client.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $broadcast_queue,
    StatefulStomp $stomp,
    JwtAuth $auth
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->broadcastQueue = $broadcast_queue;
    $this->stomp = $stomp;
    $this->auth = $auth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $config = $container->get('config.factory');
    $settings = $config->get(IslandoraSettingsForm::CONFIG_NAME);
    $broadcastQueue = $settings->get(IslandoraSettingsForm::BROADCAST_QUEUE);

    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $broadcastQueue,
      $container->get('islandora.stomp'),
      $container->get('jwt.authentication.jwt')
    );
  }

  /**
   * Sends a message to a broadcaster to get distributed.
   *
   * @param string $message
   *   Message body to send.
   * @param array $recipients
   *   List of queues/topics to broadcast message to.
   */
  protected function doExecute($message, array $recipients) {
    // Transform recipients array into comma searated list.
    $recipients = array_map('trim', $recipients);
    $recipients = implode(',', $recipients);
    $headers = ['IslandoraBroadcastRecipients' => $recipients];

    // Include a token for later authentication in the message.
    $token = $this->auth->generateToken();
    if ($token !== NULL) {
      $headers['Authorization'] = "Bearer $token";
      dsm($headers['Authorization'], "TOKEN");
    }
    else {
      // JWT isn't properly configured. Log and notify user.
      \Drupal::logger('islandora')->error(
        'Error getting JWT token for message: @msg', ['@msg' => $e->getMessage()]
      );
      drupal_set_message(
        t('Error getting JWT token for message. Check JWT Configuration.'), 'error'
      );
    }

    // Transform message from string into a proper message object.
    $message = new Message($message, $headers);

    // Send the message.
    try {
      $this->stomp->begin();
      $this->stomp->send($this->broadcastQueue, $message);
      $this->stomp->commit();
    }
    catch (StompException $e) {
      // Log it.
      \Drupal::logger('islandora')->error(
        'Error publishing message: @msg',
        ['@msg' => $e->getMessage()]
      );

      // Notify user.
      drupal_set_message(
        t('Error publishing message: @msg',
          ['@msg' => $e->getMessage()]
        ),
        'error'
      );
    }
  }

}
