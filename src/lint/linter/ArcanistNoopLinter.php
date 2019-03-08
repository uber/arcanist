<?php

/**
 * Base class for linters that used to work but now need to be disabled
 * at client run-time.
 */
abstract class ArcanistNoopLinter extends ArcanistExternalLinter {
  public function getInfoURI() {
    return '';
  }

  public function getInfoDescription() {
    return pht('A former linter that now does nothing at all.');
  }

  public function getLinterName() {
    return 'NOOP';
  }

  public function getLinterConfigurationOptions() {
    return array();
  }

  public function getDefaultBinary() {
    // There are many possible paths to find truth in life. BSD and Linux took
    // different paths.
    $truebin = Filesystem::resolveBinary('true');
    if ($truebin) {
      return $truebin;
    } else {
       throw new ArcanistMissingLinterException(
         pht('Unable to find the "true" program on your path.')
       );
    }
  }

  public function getInstallInstructions() {
    return '';
  }

  protected function getMandatoryFlags() {
    return array();
  }

  public function getVersion() {
    return '1.0.0';
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    return array();
  }
}
