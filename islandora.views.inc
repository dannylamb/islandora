<?php

/**
 * @file
 * Provide Views data for integer-weight fields.
 */

/**
 * Implements hook_views_data_alter().
 */
function islandora_views_data_alter(&$data) {
  // For now only support Nodes.
  $fields = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions('node');
  foreach ($fields as $field => $field_storage_definition) {
    if ($field_storage_definition->getType() == 'integer' && strpos($field, "field_") === 0) {
      $data['node__' . $field][$field . '_value']['field'] = $data['node__' . $field][$field]['field'];
      $data['node__' . $field][$field]['title'] = t('Integer Weight Selector (@field)', [
        '@field' => $field,
      ]);
      $data['node__' . $field][$field]['help'] = t('Provides a drag-n-drop reordering of integer-based weight fields.');
      $data['node__' . $field][$field]['title short'] = t('Integer weight selector');
      $data['node__' . $field][$field]['field']['id'] = 'integer_weight_selector';
    }
  }
}
