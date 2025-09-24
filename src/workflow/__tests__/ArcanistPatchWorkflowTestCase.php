<?php

/**
 * Test cases for @{class:ArcanistPatchWorkflow}.
 *
 * Run with:
 * `path/to/arcanist/bin/arc unit src/workflow/__tests__/ArcanistPatchWorkflowTestCase.php`
 */

final class ArcanistPatchWorkflowTestCase extends PhutilTestCase {
  /**
   * Helper method to call private methods for testing purposes.
   */
  private function callPrivateMethod($object, $method_name, $parameters = array()) {
    $reflection = new ReflectionClass(get_class($object));
    $method = $reflection->getMethod($method_name);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
  }

  public function testUberMergeUsingStagingTagFlagExists() {
    $workflow = new ArcanistPatchWorkflow();
    $arguments = $workflow->getArguments();

    // Check that uber-merge-using-staging-tag argument exists
    $this->assertTrue(array_key_exists('uber-merge-using-staging-tag', $arguments));
  }

  public function testHasCommonAncestorSuccess() {
    $workflow = new ArcanistPatchWorkflow();

    // Create a mock repository API that returns success
    $mock_repo_api = $this->createMockRepositoryAPI(array('has_common_ancestor' => true, 'merge_base_output' => 'abc123def456'));
    $this->assertTrue($this->callPrivateMethod($workflow, 'hasCommonAncestor', array('refs/heads/feature', $mock_repo_api)));
  }

  public function testHasCommonAncestorFailure() {
    $workflow = new ArcanistPatchWorkflow();

    // Create a mock repository API that returns failure.
    // We shouldn't get both an error and output, but we'll test that hasCommonAncestor returns false in this case.
    $mock_repo_api = $this->createMockRepositoryAPI(array('has_common_ancestor' => false, 'merge_base_output' => 'abc123def456'));
    $this->assertFalse($this->callPrivateMethod($workflow, 'hasCommonAncestor', array('refs/heads/feature', $mock_repo_api)));
  }

  public function testHasCommonAncestorEmptyOutput() {
    $workflow = new ArcanistPatchWorkflow();

    // Create a mock repository API that returns success but empty output
    $mock_repo_api = $this->createMockRepositoryAPI(array('has_common_ancestor' => true, 'merge_base_output' => ''));
    $this->assertFalse($this->callPrivateMethod($workflow, 'hasCommonAncestor', array('refs/heads/feature', $mock_repo_api)));
  }

  public function testHasCommonAncestorWhitespaceOutput() {
    $workflow = new ArcanistPatchWorkflow();

    // Create a mock repository API that returns success but whitespace-only output
    $mock_repo_api = $this->createMockRepositoryAPI(array('has_common_ancestor' => true, 'merge_base_output' => '   '));
    $this->assertFalse($this->callPrivateMethod($workflow, 'hasCommonAncestor', array('refs/heads/feature', $mock_repo_api)));
  }

  // Tests for mergeBranchFromStagingArea method
  public function testMergeBranchFromStagingAreaMethodExists() {
    $workflow = new ArcanistPatchWorkflow();

    // Test that the method exists and is callable
    $method_exists = method_exists($workflow, 'mergeBranchFromStagingArea');
    $this->assertTrue($method_exists);

    // Test that it's a private method
    $reflection = new ReflectionClass($workflow);
    $method = $reflection->getMethod('mergeBranchFromStagingArea');
    $this->assertTrue($method->isPrivate());
  }

  // Helper method to create a mock repository API
  // Mock only the operations you need - each option controls a specific Git command
  private function createMockRepositoryAPI($options = array()) {
    // Default options - each operation defaults to success
    $defaults = array(
      'has_common_ancestor' => true,
      'merge_base_output' => 'abc123def456',
      'fetch_success' => null,
      'merge_base_ancestor_output' => 0,
      'base_rev_parse_success' => null,
      'base_rev_parse_output' => ''
    );

    $options = array_merge($defaults, $options);

    $mock = new PatchWorkflowMockRepositoryAPI($options);

    return $mock;
  }

  // Helper method to create a mock UberRefProvider
  private function createMockUberRefProvider() {
    $mock = new PatchWorkflowMockUberRefProvider();

    return $mock;
  }

  // Helper method to set up a workflow with all necessary mocks
  private function setupWorkflowWithMocks($mock_repo_api) {
    $workflow = new ArcanistPatchWorkflow();

    // Set the repository API mock
    $workflow->setRepositoryAPI($mock_repo_api);

    // Set the UberRefProvider mock using reflection
    $reflection = new ReflectionClass($workflow);
    $property = $reflection->getProperty('uberRefProvider');
    $property->setAccessible(true);
    $property->setValue($workflow, $this->createMockUberRefProvider());

    return $workflow;
  }

  public function testValidateStagingMergeCriteria_FetchFailure() {
    // Mock: fetch fails
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => false
      // No other mocks needed because we should short-circuit on fetch failure
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    // This should throw an ArcanistUsageException
    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to throw an ArcanistUsageException, but it succeeded');
    } catch (ArcanistUsageException $e) {
      // Expected to throw an ArcanistUsageException
      $this->assertTrue(true);
    }
  }

  public function testValidateStagingMergeCriteria_SuccessDirectAncestor() {
    // Mock: fetch succeeds, direct ancestor (base ref exists on HEAD), no common ancestor in history
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => 0, // 0 = ancestor
      'has_common_ancestor' => false,
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    // This should succeed and return early (no exception thrown)
    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      // If we get here, the method succeeded (which is what we want)
      $this->assertTrue(true);
    } catch (Exception $e) {
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to succeed, but it threw: ' . $e->getMessage());
    }
  }

  public function testValidateStagingMergeCriteria_SuccessMicrorepoMigration() {
    // Mock: fetch succeeds, not direct ancestor, hasCommonAncestor returns false
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => 1, // 1 = not ancestor
      'has_common_ancestor' => false,
      'base_rev_parse_success' => false,
      'base_rev_parse_output' => ''
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);


    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    // This should succeed and return early (no exception thrown)
    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      // If we get here, the method succeeded (which is what we want)
      $this->assertTrue(true);
    } catch (Exception $e) {
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to succeed, but it threw: ' . $e->getMessage());
    }
  }

  public function testValidateStagingMergeCriteria_SuccessCustomPrefix() {
    // Mock: fetch succeeds, not direct ancestor, no common ancestor in history, rev-parse succeeds
    // This is the same as the SuccessMicrorepoMigration test, but with a custom prefix, to confirm that doesn't make a difference.
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => 1, // 1 = not ancestor
      'has_common_ancestor' => false,
      'base_rev_parse_success' => true,
      'base_rev_parse_output' => 'custom789'
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'custom');
    $staging_uri = 'origin';

    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(789, $staging, $staging_uri));
      // If we get here, the method succeeded (which is what we want)
      $this->assertTrue(true);
    } catch (Exception $e) {
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to succeed, but it threw: ' . $e->getMessage());
    }
  }

  public function testValidateStagingMergeCriteria_IsAncestorCallFailed() {
    // Mock: fetch succeeds, merge-base --is-ancestor fails
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => null, // null = error running command
      // No other mocks needed because we should short-circuit on merge-base --is-ancestor failure
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    // This should throw an ArcanistUsageException
    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to throw an ArcanistUsageException, but it succeeded');
    } catch (ArcanistUsageException $e) {
      // Expected to throw an ArcanistUsageException
      $this->assertTrue(true);
    }
  }

  public function testValidateStagingMergeCriteria_CommonButNotDirectAncestor() {
    // Mock: fetch succeeds, not direct ancestor, hasCommonAncestor returns true, rev-parse succeeds
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => 1, // 1 = not ancestor
      'has_common_ancestor' => true,
      'base_rev_parse_success' => true,
      'base_rev_parse_output' => 'abc123def456'
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to throw an ArcanistUsageException, but it succeeded');
    } catch (ArcanistUsageException $e) {
      // Expected to throw an ArcanistUsageException
      $this->assertTrue(true);
    }
  }

  public function testValidateStagingMergeCriteriaFetchFailure() {
    // Mock: fetch fails
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => false,
      'has_common_ancestor' => false,
      'base_rev_parse_output' => ''
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to throw an ArcanistUsageException, but it succeeded');
    } catch (ArcanistUsageException $e) {
      // Expected to throw an ArcanistUsageException
      $this->assertTrue(true);
    }
  }

  public function testValidateStagingMergeCriteriaRevParseFailure() {
    // Mock: fetch succeeds, merge-base --is-ancestor fails, hasCommonAncestor returns true, rev-parse fails
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => 1, // 1 = not ancestor
      'has_common_ancestor' => true,
      'base_rev_parse_success' => false,
      'base_rev_parse_output' => ''
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'staging');
    $staging_uri = 'origin';

    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(456, $staging, $staging_uri));
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to throw an ArcanistUsageException, but it succeeded');
    } catch (ArcanistUsageException $e) {
      // Expected to throw an ArcanistUsageException
      $this->assertTrue(true);
    }
  }

  public function testValidateStagingMergeCriteria_BaseRevParseFailure() {
    // Mock: fetch succeeds, not direct ancestor, hasCommonAncestor returns true, rev-parse fails
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => 1, // 1 = not ancestor
      'has_common_ancestor' => true,
      'base_rev_parse_success' => false,
      'base_rev_parse_output' => ''
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to throw an ArcanistUsageException, but it succeeded');
    } catch (ArcanistUsageException $e) {
      // Expected to throw an ArcanistUsageException
      $this->assertTrue(true);
    }
  }

  public function testValidateStagingMergeCriteria_BaseRevParseOutputEmpty() {
    // Mock: fetch succeeds, not direct ancestor, no common ancestor in history, rev-parse output is empty
    $mock_repo_api = $this->createMockRepositoryAPI(array(
      'fetch_success' => true,
      'merge_base_ancestor_output' => 1, // 1 = not ancestor
      'has_common_ancestor' => true,
      'base_rev_parse_success' => true,
      'base_rev_parse_output' => ''
    ));

    $workflow = $this->setupWorkflowWithMocks($mock_repo_api);

    $staging = array('prefix' => 'phabricator');
    $staging_uri = 'origin';

    try {
      $this->callPrivateMethod($workflow, 'validateStagingMergeCriteria', array(123, $staging, $staging_uri));
      $this->assertTrue(false, 'Expected validateStagingMergeCriteria to throw an ArcanistUsageException, but it succeeded');
    } catch (ArcanistUsageException $e) {
      // Expected to throw an ArcanistUsageException
      $this->assertTrue(true);
    }
  }
}
