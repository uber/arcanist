<?php

/** This linter invokes shellcheck to check on shell code standards */
final class ArcanistShellCheckLinter extends ArcanistExternalLinter {

  private $shell = 'bash';

  public function getInfoName() {
    return 'ShellCheck';
  }

  public function getInfoURI() {
    return 'http://www.shellcheck.net/';
  }

  public function getInfoDescription() {
    return pht(
      'ShellCheck is a static analysis and linting tool for %s scripts.',
      'sh/bash');
  }

  public function getLinterName() {
    return 'SHELLCHECK';
  }

  public function getLinterConfigurationName() {
    return 'shellcheck';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'shellcheck.shell' => array(
        'type' => 'optional string',
        'help' => pht(
          'Specify shell dialect (%s, %s, %s, %s).',
          'bash',
          'sh',
          'ksh',
          'zsh'),
      ),
    );

    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'shellcheck.shell':
        $this->setShell($value);
        return;

      default:
        return parent::setLinterConfigurationValue($key, $value);
    }
  }

  public function setShell($shell) {
    $this->shell = $shell;
    return $this;
  }

  public function getDefaultBinary() {
    return 'shellcheck';
  }

  public function getInstallInstructions() {
    return pht(
      'Install ShellCheck with `%s`.',
      'brew install shellcheck');
  }

  protected function getMandatoryFlags() {
    $options = array();

    // exclude `not following` shellcheck rule by default
    $options[] = '--exclude=SC1091'
    $options[] = '--format=checkstyle';

    if ($this->shell) {
      $options[] = '--shell='.$this->shell;
    }

    return $options;
  }

  public function getVersion() {
    list($stdout, $stderr) = execx(
      '%C --version', $this->getExecutableCommand());

    $matches = null;
    if (preg_match('/^version: (\d(?:\.\d){2})$/', $stdout, $matches)) {
      return $matches[1];
    }

    return null;
  }

  protected function parseLinterOutput($path, $err, $stdout, $stderr) {
    $report_dom = new DOMDocument();
    $ok = @$report_dom->loadXML($stdout);

    if (!$ok) {
      return false;
    }

    $files = $report_dom->getElementsByTagName('file');
    $messages = array();

    foreach ($files as $file) {
      foreach ($file->getElementsByTagName('error') as $child) {
        $code = str_replace('ShellCheck.', '', $child->getAttribute('source'));

        $message = id(new ArcanistLintMessage())
          ->setPath($path)
          ->setLine($child->getAttribute('line'))
          ->setChar($child->getAttribute('column'))
          ->setName($this->getLinterName())
          ->setCode($code)
          ->setDescription($child->getAttribute('message'));

        switch ($child->getAttribute('severity')) {
          case 'error':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;

          case 'warning':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
            break;

          case 'info':
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
            break;

          default:
            $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
            break;
        }

        $messages[] = $message;
      }
    }

    return $messages;
  }
}
