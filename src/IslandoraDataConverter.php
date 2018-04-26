<?php

namespace Drupal\islandora;

class IslandoraDataConverter {

  public static function link($data) {
    \Drupal::logger('islandora')->debug(json_encode($data));
    return $data;
  }

}
