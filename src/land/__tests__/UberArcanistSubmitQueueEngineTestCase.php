<?php

/**
 * Test cases for @{class:UberArcanistSubmitQueueEngine}.
 *
 * Run with:
 * `path/to/arcanist/bin/arc unit src/land/__tests__/UberArcanistSubmitQueueEngineTestCase.php`
 */

final class UberArcanistSubmitQueueEngineTestCase extends PhutilTestCase {

  /**
   * Helper method to call private methods for testing purposes.
   */
  private function callPrivateMethod($object, $method_name, $parameters = array()) {
    $reflection = new ReflectionClass(get_class($object));
    $method = $reflection->getMethod($method_name);
    $method->setAccessible(true);
    return $method->invokeArgs($object, $parameters);
  }

  /**
   * Helper method to create a mock submit queue client.
   */
  private function createMockSubmitQueueClient() {
    $mock = new SubmitQueueMockClient();

    return $mock;
  }

  /**
   * Helper method to create a mock repository API.
   */
  private function createMockRepositoryAPI($remote_url = 'git@github.com:example/repo.git') {
    $mock = new SubmitQueueMockRepositoryAPI($remote_url);

    return $mock;
  }

  /**
   * Helper method to create a mock workflow.
   */
  private function createMockWorkflow() {
    $mock = new SubmitQueueMockWorkflow();

    return $mock;
  }

  /**
   * Helper method to create a UberArcanistSubmitQueueEngine with mocked dependencies.
   * Returns both the engine and the mock client for parameter verification.
   */
  private function createEngineWithMocks($options = array()) {
    $defaults = array(
      'skip_submit_queue_checks' => false,
      'should_shadow' => false,
      'target_onto' => 'master',
      'target_remote' => 'origin',
      'revision' => array(
        'id' => '123',
        'diffs' => array('456')
      ),
      'remote_url' => 'git@github.com:example/repo.git'
    );

    $options = array_merge($defaults, $options);

    $mock_client = $this->createMockSubmitQueueClient();
    $mock_conduit = new stdClass();
    $engine = new UberArcanistSubmitQueueEngine($mock_client, $mock_conduit, false);

    // Set up the engine state
    $engine->setRevision($options['revision']);
    $engine->setShouldShadow($options['should_shadow']);
    $engine->setSkipSubmitQueueChecks($options['skip_submit_queue_checks']);
    $engine->setTargetOnto($options['target_onto']);
    $engine->setTargetRemote($options['target_remote']);

    // Set up repository API mock
    $mock_repo_api = $this->createMockRepositoryAPI($options['remote_url']);
    $engine->setRepositoryAPI($mock_repo_api);

    // Set up workflow mock
    $mock_workflow = $this->createMockWorkflow();
    $engine->setWorkflow($mock_workflow);

    return array($engine, $mock_client);
  }

  public function testPushChangeToSubmitQueue_BasicMerge() {
    list($engine, $mock_client) = $this->createEngineWithMocks();

    // Execute the method
    $this->callPrivateMethod($engine, 'pushChangeToSubmitQueue');

    // Verify the correct method was called
    $this->assertEqual('submitMergeRequest', $mock_client->last_call_method);

    // Verify parameters were passed correctly
    $this->assertEqual('git@github.com:example/repo.git', $mock_client->last_call_params['remoteUrl']);
    $this->assertEqual('456', $mock_client->last_call_params['diffId']);
    $this->assertEqual('123', $mock_client->last_call_params['revisionId']);
    $this->assertEqual(false, $mock_client->last_call_params['shouldShadow']);
    $this->assertEqual('master', $mock_client->last_call_params['targetOnto']);
  }

  public function testPushChangeToSubmitQueue_ComplexScenario() {
    list($engine, $mock_client) = $this->createEngineWithMocks(array(
      'skip_submit_queue_checks' => false,
      'should_shadow' => true,
      'target_onto' => 'custom-branch',
      'target_remote' => 'my-remote',
      'revision' => array(
        'id' => '999',
        'diffs' => array('888')
      ),
      'remote_url' => 'git@test.com:foo/bar.git'
    ));

    // Execute the method
    $this->callPrivateMethod($engine, 'pushChangeToSubmitQueue');

    // Verify the correct method was called
    $this->assertEqual('submitMergeRequest', $mock_client->last_call_method);

    // Verify all parameters were passed correctly in a complex scenario
    $this->assertEqual('git@test.com:foo/bar.git', $mock_client->last_call_params['remoteUrl']);
    $this->assertEqual('888', $mock_client->last_call_params['diffId']);
    $this->assertEqual('999', $mock_client->last_call_params['revisionId']);
    $this->assertEqual(true, $mock_client->last_call_params['shouldShadow']);
    $this->assertEqual('custom-branch', $mock_client->last_call_params['targetOnto']);
  }

  public function testPushChangeToSubmitQueue_PriorityMerge() {
    list($engine, $mock_client) = $this->createEngineWithMocks(array(
      'skip_submit_queue_checks' => true,
      'should_shadow' => false,
      'target_onto' => 'main',
      'target_remote' => 'origin',
      'revision' => array(
        'id' => '111',
        'diffs' => array('222')
      ),
      'remote_url' => 'git@test.com:foo/bar.git'
    ));

    // Execute the method
    $this->callPrivateMethod($engine, 'pushChangeToSubmitQueue');

    // Verify the correct method was called
    $this->assertEqual('submitPriorityMergeRequest', $mock_client->last_call_method);

    // Verify the revisionId parameter was passed correctly
    $this->assertEqual('111', $mock_client->last_call_params['revisionId']);
    $this->assertEqual(1, count($mock_client->last_call_params), 'Should only have one parameter');
  }
}
