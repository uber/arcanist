<?php

/**
 * This linter invokes checkstyle for verifying code style standards.
 */
class UberCheckstyleLinter extends ArcanistLinter {

  private $script = null;

  public function getInfoName() {
    return 'Java checkstyle linter';
  }

  public function getLinterName() {
    return 'CHECKSTYLE';
  }

  public function getInfoURI() {
    return 'http://checkstyle.sourceforge.net';
  }

  public function getInfoDescription() {
    return 'Use checkstyle to perform static analysis on Java code';
  }

  public function getLinterConfigurationName() {
    return 'checkstyle';
  }

  public function getLinterConfigurationOptions() {
    $options = array(
      'checkstyle.script' => array(
        'type' => 'string',
        'help' => pht('Checkstyle script to execute'),
        )
      );
    return $options + parent::getLinterConfigurationOptions();
  }

  public function setLinterConfigurationValue($key, $value) {
    switch ($key) {
      case 'checkstyle.script':
      $this->script = $value;
      return;
    }
    return parent::setLinterConfigurationValue($key, $value);
  }

  public function lintPath($path) {
    if (array_search($path, $this->getPaths()) === 0) {
      $chunks = array_chunk($this->getPaths(), 500);
      $futures = id(new FutureIterator(array()));
      foreach ($chunks as $chunk) {
        $future = new ExecFuture('%C %C', $this->script, implode(' ', $this->getPaths()));
        $future->setCWD($this->getProjectRoot());
        $futures->addFuture($future);
      }

      foreach ($futures as $future) {
        list($stdout) = $future->resolvex();
        $this->parseCheckstyleOutput($stdout);
      }
    }
    return;
  }

  private function parseCheckstyleOutput($stdout) {
    $dom = new DOMDocument();
    $ok = @$dom->loadXML($stdout);

    $files = $dom->getElementsByTagName('file');
    foreach ($files as $file) {
      $errors = $file->getElementsByTagName('error');
      $path = $file->getAttribute('name');
      $arcPath = ltrim(str_replace(getcwd(), '', $path), '/');

      $changedLines = $this->getEngine()->getPathChangedLines($arcPath);
      if ($changedLines) {
        $changedLines = array_keys($changedLines);
      } else {
        $changedLines = array();
      }

      foreach ($errors as $error) {
        $source = idx(array_slice(explode('.', $error->getAttribute('source')), -1), 0);
        $line = $error->getAttribute('line');

        // Do not fail for errors outside changed lines
        if (($source == "JavadocTypeCheck" || $source == "JavadocMethodCheck" || $source == "RegexpSinglelineCheck")
         && !in_array($line, $changedLines)) {
          continue;
        }

        $message = new ArcanistLintMessage();
        $message->setPath($path);
        $message->setLine($line);
        $message->setCode($this->getLinterName());
        $message->setName($source);

        // checkstyle's XMLLogger escapes these five characters
        $description = $error->getAttribute('message');
        $description = str_replace(
          ['&lt;', '&gt;', '&apos;', '&quot;', '&amp;'],
          ['<', '>', '\'', '"', '&'],
          $description);
        $message->setDescription($description);

        $column = $error->getAttribute('column');
        if ($column) {
          $message->setChar($column);
        }

        $severity = $error->getAttribute('severity');
        switch ($severity) {
          case 'error':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ERROR);
          $this->addAutofixes($message, $source, $line, $path);
          break;
          case 'warning':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
          break;
          case 'info':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_ADVICE);
          break;
          case 'ignore':
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_DISABLED);
          break;
          default:
          $message->setSeverity(ArcanistLintSeverity::SEVERITY_WARNING);
          break;
        }

        $this->addLintMessage($message);
      }
    }
  }

  private function addAutofixes($message, $source, $line, $path) {
    if (!($source == "UnusedImportsCheck"
          || $source == "RegexpMultilineCheck")) {
      return;
    }
    $file = new SplFileObject($path);
    $origLines = '';
    if ($source == "UnusedImportsCheck") {
      $file->seek($line-1); // arcanist line number starts with 1, but file starts with 0
      $origLines = $file->current();
    } else if ($source == "RegexpMultilineCheck") {
      // checkstyle reports line before blank line
      // let's set message to point at the first blank line
      $message->setLine($line+1);
      // seek to line *after* first blank line
      $curLine = $line+1;
      $file->seek($curLine);
      // collect all the blank lines we want to delete
      while ($file->current() == "\n") {
        $origLines = $origLines . "\n";
        $curLine = $curLine + 1;
        $file->seek($curLine);
      }
    }
    $message->setChar(null);
    $message->setOriginalText($origLines);
    $message->setReplacementText('');
  }
}

?>

