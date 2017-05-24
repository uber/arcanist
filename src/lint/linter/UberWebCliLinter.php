<?php

/**
 * Uses UberStandard to detect errors and potential problems in JavaScript code.
 */
final class UberWebCliLinter extends ArcanistExternalLinter {

  private $eslintrc;

  public function getInfoName() {
    return 'UberWebCli';
  }

  public function getInfoURI() {
    // web-cli lint delegates to standard https://github.com/uber/standard
    return 'https://code.uberinternal.com/diffusion/WEWEBC/';
  }

  public function getInfoDescription() {
    return pht('Use `web-cli lint` to detect issues with JavaScript source files.');
  }

  public function getLinterName() {
    return 'WebCli';
  }

  public function getLinterConfigurationName() {
    return 'web-cli';
  }

  protected function getDefaultMessageSeverity($code) {
    return ArcanistLintSeverity::SEVERITY_ERROR;
  }

  public function getDefaultBinary() {
    return getcwd().'/node_modules/.bin/web-cli';
  }

  public function getVersion() {
    list($stdout, $stderr) = execx(
      '%C --version',
      $this->getExecutableCommand());

    $matches = array();
    $regex = '/^(?P<version>\d+\.\d+\.\d+)$/';
    if (preg_match($regex, $stderr, $matches)) {
      return $matches['version'];
    } else {
      return false;
    }
  }

  public function getInstallInstructions() {
    return pht('Install web-cli using `npm install @uber/web-cli`.');
  }

  public function shouldExpectCommandErrors() {
    return true;
  }

  protected function getMandatoryFlags() {
    $options = array('lint', '--reporter=json');
    return $options;
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'uber-standard.eslintrc' => array(
        'type' => 'optional string',
        'help' => pht('Custom .eslintrc configuration file.'),
      )
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'uber-standard.eslintrc':
        $this->eslintrc = $value;
        return;
    }

    return parent::setLinterConfigurationValue($key, $value);
  }

  protected function getDefaultFlags() {
    $options = array();
    return $options;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    // Remove first line with the command that was called by web-cli
    $jsonout = substr($stdout, strpos($stdout, "\n")+1);
    $json = json_decode($jsonout, true);
    $files = idx($json, 'files');

    if (!is_array($files)) {
      // Something went wrong and we can't decode the output. Exit abnormally.
      throw new ArcanistUsageException(
        "web-cli lint returned unparseable output.\n".
        "stdout:\n\n{$stdout}".
        "stderr:\n\n{$stderr}");
    }

    $messages = array();
    foreach ($files as $f) {
      $errors = idx($f, 'errors');
      foreach ($errors as $err) {
        $message = new ArcanistLintMessage();
        $message->setPath(idx($f, 'file'));
        $message->setLine(idx($err, 'line'));
        $message->setChar(idx($err, 'column'));
        $message->setCode(idx($err, 'rule'));
        $message->setDescription(idx($err, 'message'));
        $message->setSeverity($this->getLintMessageSeverity(idx($err, 'type')));

        $messages[] = $message;
      }
    }

    return $messages;
  }

}
