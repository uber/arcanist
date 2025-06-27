<?php

/**
 * Test cases for @{class:CompareCommitToRevisionWorkflow}.
 * 
 * Run with:
 * `~/Uber/arcanist/bin/arc unit src/workflow/__tests__/CompareCommitToRevisionWorkflowTestCase.php`
 */
final class CompareCommitToRevisionWorkflowTestCase extends PhutilTestCase {

  public function testCheckContentMatchBetweenDiffs() {
    $workflow = new CompareCommitToRevisionWorkflow();

    // Test identical diffs
    $local_diff = "line1\nline2\nline3";
    $reviewed_diff = "line1\nline2\nline3";
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Identical diffs should match');

    // Test empty diffs
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array('', '')),
      'Empty diffs should match');

    // Test local diff is subset of reviewed diff
    $local_diff = "line1\nline3";
    $reviewed_diff = "line1\nline2\nline3\nline4";
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Local diff as subset of reviewed diff should match');

    // Test local diff has extra lines that reviewed diff doesn't have
    $local_diff = "line1\nline2\nline3\nline4";
    $reviewed_diff = "line1\nline3";
    $this->assertFalse(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Local diff with extra lines should not match');

    // Test reordered lines where local is subset
    $local_diff = "add line A\nadd line C";
    $reviewed_diff = "add line A\nadd line B\nadd line C\nadd line D";
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Local diff lines should match when they appear in order in reviewed diff');

    // Test case where local diff line appears later in reviewed diff
    $local_diff = "line3\nline1";
    $reviewed_diff = "line1\nline2\nline3\nline4";
    $this->assertFalse(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Should not match when local diff lines are out of order compared to reviewed diff');

    // Test with single line diffs
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array('single line', 'single line')),
      'Single identical lines should match');

    // Test basic single line case  
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array('line1', "line1\nline2")),
      'Single line local diff should match when present in multi-line reviewed diff');

    $this->assertFalse(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array('missing line', 'line1\nline2')),
      'Single line local diff should not match when missing from reviewed diff');

    // Test with realistic diff-like content - simpler case
    $local_diff = "function foo() {\n  return 1;\n}";
    $reviewed_diff = "function foo() {\n  return 1;\n}\n\nfunction bar() {\n  return 2;\n}";
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Code diff format should work when local is subset of reviewed');

    // Test edge case: local diff longer than reviewed diff
    $local_diff = "line1\nline2\nline3\nline4\nline5";
    $reviewed_diff = "line1\nline2";
    $this->assertFalse(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Should return false when local diff is longer and reviewed diff runs out');

    // Test with whitespace variations
    $local_diff = "line1\n  line2\nline3";
    $reviewed_diff = "line1\n  line2\nline3\nline4";
    $this->assertTrue(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Should handle whitespace in lines correctly');

    // Test mixed content where some lines match and some don't
    $local_diff = "matching line\nunmatched line\nmatching line 2";
    $reviewed_diff = "matching line\ndifferent line\nmatching line 2\nextra line";
    $this->assertFalse(
      $this->callPrivateMethod($workflow, 'checkContentMatchBetweenDiffs', array($local_diff, $reviewed_diff)),
      'Should not match when some lines don\'t have corresponding matches');
  }

  /**
   * Helper method to call private methods for testing purposes.
   */
  private function callPrivateMethod($object, $method_name, $parameters = array()) {
    $reflection = new ReflectionClass(get_class($object));
    $method = $reflection->getMethod($method_name);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
  }

}
