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
    $mock = new class() extends stdClass {
      public $last_call_method = null;
      public $last_call_params = null;

      public function submitMergeRequest($remoteUrl, $diffId, $revisionId, $shouldShadow, $targetOnto) {
        $this->last_call_method = 'submitMergeRequest';
        $this->last_call_params = array(
          'remoteUrl' => $remoteUrl,
          'diffId' => $diffId,
          'revisionId' => $revisionId,
          'shouldShadow' => $shouldShadow,
          'targetOnto' => $targetOnto
        );
        return 'http://submit-queue.example.com/status/123';
      }

      public function submitPriorityMergeRequest($revisionId) {
        $this->last_call_method = 'submitPriorityMergeRequest';
        $this->last_call_params = array(
          'revisionId' => $revisionId
        );
        return 'http://submit-queue.example.com/priority/456';
      }
    };

    return $mock;
  }

  /**
   * Helper method to create a mock repository API.
   */
  private function createMockRepositoryAPI($remote_url = 'git@github.com:example/repo.git') {
    $mock = new class($remote_url) extends ArcanistRepositoryAPI {
      private $remote_url;

      public function __construct($remote_url) {
        $this->remote_url = $remote_url;
      }

      public function uberGetGitRemotePushUrl($remote) {
        return $this->remote_url;
      }

      // Required abstract methods from ArcanistRepositoryAPI
      public function getSourceControlSystemName() {
        return 'git';
      }

      public function getMetadataPath() {
        return '.git';
      }

      public function getSourceControlBaseRevision() {
        return 'HEAD~1';
      }

      public function getCanonicalRevisionName($string) {
        return $string;
      }

      public function getBranchName() {
        return 'mock-branch';
      }

      public function getSourceControlPath() {
        return '/mock/path';
      }

      public function isHistoryDefaultImmutable() {
        return false;
      }

      public function supportsAmend() {
        return true;
      }

      public function getWorkingCopyRevision() {
        return 'mock-revision';
      }

      public function updateWorkingCopy() {
        return;
      }

      public function getLocalCommitInformation() {
        return array();
      }

      public function getAllLocalChanges() {
        return array();
      }

      public function supportsLocalCommits() {
        return true;
      }

      public function hasLocalCommits() {
        return false;
      }

      public function getCommitMessage($commit) {
        return 'Mock commit message';
      }

      public function loadWorkingCopyDifferentialRevisions(ConduitClient $conduit, array $query) {
        return array();
      }

      public function updateLocalHashes(array $hashes) {
        return;
      }

      protected function buildLocalFuture(array $argv) {
        return new ExecFuture('echo mock');
      }

      public function getFinalizedRevisionMessage() {
        return 'Mock finalized message';
      }

      public function execxLocal($pattern /* , ... */) {
        return array('mock output');
      }

      public function getCommitSummary($commit) {
        return 'Mock summary';
      }

      // Additional required abstract methods
      protected function buildUncommittedStatus() {
        return array();
      }

      protected function buildCommitRangeStatus() {
        return array();
      }

      public function getAllFiles() {
        return array();
      }

      public function getBlame($path) {
        return array();
      }

      public function getRawDiffText($path) {
        return '';
      }

      public function getOriginalFileData($path) {
        return '';
      }

      public function getCurrentFileData($path) {
        return '';
      }

      public function getRemoteURI() {
        return $this->remote_url;
      }

      public function supportsLocalBranchMerge() {
        return true;
      }

      public function supportsCommitRanges() {
        return true;
      }
    };

    return $mock;
  }

  /**
   * Helper method to create a mock workflow.
   */
  private function createMockWorkflow() {
    $mock = new class() extends ArcanistWorkflow {
      public function getWorkflowName() {
        return 'mock-workflow';
      }

      public function getCommandSynopses() {
        return array();
      }

      public function getCommandHelp() {
        return 'Mock workflow for testing';
      }

      public function run() {
        return 0;
      }
    };

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

    // Mock the repository API
    $mock_repo_api = $this->createMockRepositoryAPI($options['remote_url']);
    $engine->setRepositoryAPI($mock_repo_api);

    // Mock the workflow
    $mock_workflow = $this->createMockWorkflow();
    $engine->setWorkflow($mock_workflow);

    return array($engine, $mock_client);
  }

  public function testPushChangeToSubmitQueue_RegularMerge() {
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
