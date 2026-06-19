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

/**
 * Testable version of UberSubmitQueueClient that tracks USSO token usage.
 */
class SubmitQueueMockClientWithUSSOTracking extends UberSubmitQueueClient {
  public $last_use_usso_token = null;
  public $added_headers = array();

  protected function callMethod($method, $api, array $params, $use_usso_token = false) {
    // Track whether USSO token was requested
    $this->last_use_usso_token = $use_usso_token;
    $this->added_headers = array();

    // Create a mock future
    $mock_future = new SubmitQueueMockHTTPSFuture($this);

    // Simulate adding headers
    $mock_future->addHeader('Host', $this->getHost());

    // Simulate USSO token logic
    if ($use_usso_token) {
      // For testing, use a simple mock token
      $token = 'mock-usso-token';

      // Check if environment variable is set (matching real behavior)
      $env_token = getenv('ARC_USSO_TOKEN');
      if ($env_token) {
        $token = $env_token;
      }

      $mock_future->addHeader('Authorization', "Bearer {$token}");
    }

    $mock_future->setMethod($method);
    $mock_future->setTimeout($this->timeout);

    // Return a mock future that resolves to a success response
    return new SubmitQueueMockFuture($mock_future);
  }
}

/**
 * Mock HTTPSFuture for testing.
 */
class SubmitQueueMockHTTPSFuture {
  private $client;

  public function __construct($client) {
    $this->client = $client;
  }

  public function addHeader($name, $value) {
    // Track added headers in the testable client
    if ($name === 'Authorization') {
      $this->client->added_headers[] = array($name, $value);
    }
    return $this;
  }

  public function setMethod($method) {
    return $this;
  }

  public function setTimeout($timeout) {
    return $this;
  }
}

/**
 * Mock UberSubmitQueueFuture for testing.
 */
class SubmitQueueMockFuture {
  private $future;

  public function __construct($future) {
    $this->future = $future;
  }

  public function isReady() {
    return true;
  }

  public function resolve() {
    return 'http://submit-queue.example.com/status/mock';
  }
}
