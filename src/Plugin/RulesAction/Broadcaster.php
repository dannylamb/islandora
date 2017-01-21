<?php

namespace Drupal\islandora\Plugin\RulesAction;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\islandora\Form\IslandoraSettingsForm;
use Drupal\rules\Core\RulesActionBase;
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
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $broadcast_queue, StatefulStomp $stomp) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->broadcastQueue = $broadcast_queue;
    $this->stomp = $stomp;
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
      $container->get('islandora.stomp')
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

    // Transform message from string into a proper message object.
    $message = new Message($message, ['IslandoraBroadcastRecipients' => $recipients]);

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
