<?php

/**
 * Mock submit queue client for UberArcanistSubmitQueueEngineTestCase.
 */
final class SubmitQueueMockClient extends stdClass {
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
}
