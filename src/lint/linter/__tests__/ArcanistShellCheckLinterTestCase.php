<?php

final class ArcanistShellCheckLinterTestCase extends ArcanistExternalLinterTestCase {

  public function testLinter() {
    $this->executeTestsInDirectory(dirname(__FILE__).'/shellcheck/');
  }

}
