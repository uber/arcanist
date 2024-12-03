<?php

/**
 * Thrown when the Harbormaster Buildable is not passing.
 */
final class ArcanistBuildableNotPassingException extends ArcanistUsageException {

  public function __construct() {
    parent::__construct(pht('The Harbormaster Buildable is not passing. Use BREAKLGLASS in case of emergency.'));
  }

}
