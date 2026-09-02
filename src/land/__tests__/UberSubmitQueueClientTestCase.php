<?php

final class UberSubmitQueueClientTestCase extends PhutilTestCase {

  private $savedToken;

  protected function willRunOneTest($test) {
    $this->savedToken = getenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV);
    putenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV);
  }

  protected function didRunOneTest($test) {
    if ($this->savedToken === false) {
      putenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV);
    } else {
      putenv(
        UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV.'='.$this->savedToken);
    }
  }

  public function testNoBearerTokenWithoutEnvironmentVariable() {
    $client = new UberSubmitQueueClient(
      'https://submitqueue.example.com',
      'conduit-token');

    $this->assertEqual('', $client->getSubmitQueueToken());
    $this->assertEqual('conduit-token', $client->getConduitToken());
  }

  public function testEnvironmentSuppliesBearerToken() {
    putenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV.'=sq-token');

    $client = new UberSubmitQueueClient(
      'https://submitqueue.example.com',
      'conduit-token');

    $this->assertEqual('sq-token', $client->getSubmitQueueToken());
  }

  public function testBearerTokenDoesNotReplaceConduitToken() {
    putenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV.'=sq-token');

    $client = new UberSubmitQueueClient(
      'https://submitqueue.example.com',
      'conduit-token');

    $this->assertEqual('conduit-token', $client->getConduitToken());
  }

}
