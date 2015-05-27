<?php

/**
 * Tests for @{class:TAPTestEngine}.
 */
final class TAPTestEngineTestCase extends PhutilTestCase {

  public function testCoverageEnable() {
    $testEngine = new TAPTestEngine();
    $this->assertEqual(false, $testEngine->getEnableCoverage(), pht('coverage not enabled by default'));
    $testEngine->setEnableCoverage(NULL);
    $this->assertEqual(false, $testEngine->getEnableCoverage(), pht('should not be enabled if not enabled with boolean'));
    $testEngine->setEnableCoverage(true);
    $this->assertEqual(true, $testEngine->getEnableCoverage(), pht('should be enabled'));
  }

}
