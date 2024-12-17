<?php

/**
 * Thrown when the Harbormaster Buildable is not passing.
 */
final class ArcanistBuildableNotPassingException extends ArcanistUsageException {

  public function __construct() {
    parent::__construct(pht('The Harbormaster Buildable is not passing. Land is not permitted unless all blocking CI jobs pass. If you are running custom CI jobs that are intended to be non-blocking, you can mark the build as non-blocking in its build plan. See t.uber.com/code-land-policy for more information. For emergencies, use BREAKGLASS by adding it to your revision summary. BREAKGLASS will trigger an audit and is reported monthly to EngLT.'));
  }

}
