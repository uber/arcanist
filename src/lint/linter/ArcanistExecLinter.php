<?php

/**
 * Execute arbitrary command once per configuration
 * (regardless of how many files are "include"d).
 *
 * `.arclint` example:
 *
 *   {
 *     "linters": {
 *       "makelint": {
 *         "type": "exec",
 *         "exec.command": "make lint",
 *         "include": "(\\.go$)",
 *         "exclude": [
 *           "(^vendor/)"
 *         ]
 *       }
 *     }
 *   }
 *
 * This linter can also be configured using .arcconfig, e.g.
 *
 *   {
 *     "lint.engine": "ArcanistSingleLintEngine",
 *     "lint.engine.single.linter": "ArcanistExecLinter",
 *     "linter.exec.command": "make lint"
 *   }
 *
 * The command will be invoked from the project root, so you can specify a
 * relative path like `scripts/lint.sh` or an absolute path like
 * `/opt/lint/lint.sh`.
 *
 * The return code of the command must be 0, or an exception will be raised
 * reporting that the linter failed.
 */
final class ArcanistExecLinter extends ArcanistLinter {

  private $command = null;

  public function willLintPaths(array $paths) {
    $cmd = $this->command;
    if (!$cmd) {
      // fallback to .arcconfig
      $cmd = $this->getEngine()->getConfigurationManager()
        ->getConfigFromAnySource('linter.exec.command');
    }
    $root = $this->getProjectRoot();
    $future = new ExecFuture('%C', $cmd);
    $future->setCWD($root);
    $future->resolvex(); // non-zero exit code will result in an error
  }

  public function getInfoDescription() {
    return pht('Execute arbitrary command once per configuration.');
  }

  public function getLinterName() {
    return $this->getLinterConfigurationName();
  }

  public function getLinterConfigurationName() {
    return 'exec';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'exec.command' => array(
        'type' => 'string',
        'help' => pht('Command to execute.'),
      )
    );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'exec.command':
        $this->command = $value;
        return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

}
