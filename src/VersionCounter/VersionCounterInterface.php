<?php

namespace Drupal\islandora\VersionCounter;

interface VersionCounterInterface {

  /**
   * Creates a version count record in the db for an entity.
   *
   * @param $uuid
   *   Entity UUID.
   *
   * @throws Drupal\Core\Database\IntegrityConstraintViolationException
   *
   * @return integer
   *   The id of the newly created db record.
   */
  function create($uuid);

  /**
   * Returns the version count for an entity.
   *
   * @param $uuid
   *   Entity UUID.
   *
   * @return integer
   *   The version count of the entity. Returns -1 if there is no record for the
   *   uuid in the database.
   */
  function get($uuid);

  /**
   * Increments a version count for an entity.
   *
   * @param $uuid
   *   Entity UUID.
   *
   * @return integer
   *   Returns 1 on success.  Returns 0 if no record exists for the uuid in the
   *   database.
   */
  function increment($uuid);

  /**
   * Deletes a version count record in the db for an entity.
   *
   * @param $uuid
   *   Entity UUID.
   *
   * @return integer
   *   Returns 1 on success.  Returns 0 if no record exists for the uuid in the
   *   database.
   */
  function delete($uuid);

}
