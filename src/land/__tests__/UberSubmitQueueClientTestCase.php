<?php

final class UberSubmitQueueClientTestCase extends PhutilTestCase {

  const TEST_HOST = 'submitqueue-token-test.example.com';

  private $savedToken;
  private $cacheFile;
  private $hadCacheFile;
  private $savedCache;

  protected function willRunOneTest($test) {
    $this->savedToken = getenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV);
    putenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV);

    $this->cacheFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.
      'usso-token-cache-'.md5(self::TEST_HOST).'.json';
    $this->hadCacheFile = Filesystem::pathExists($this->cacheFile);
    if ($this->hadCacheFile) {
      $this->savedCache = Filesystem::readFile($this->cacheFile);
    }
  }

  protected function didRunOneTest($test) {
    if ($this->savedToken === false) {
      putenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV);
    } else {
      putenv(
        UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV.'='.$this->savedToken);
    }

    if ($this->hadCacheFile) {
      Filesystem::writeFile($this->cacheFile, $this->savedCache);
    } else if (Filesystem::pathExists($this->cacheFile)) {
      Filesystem::remove($this->cacheFile);
    }
  }

  public function testEnvironmentSuppliesSubmitQueueToken() {
    putenv(UberSubmitQueueClient::SUBMITQUEUE_TOKEN_ENV.'=sq-token');

    $client = new UberSubmitQueueClient(
      'https://'.self::TEST_HOST,
      'conduit-token');

    $this->assertEqual('sq-token', $this->getSubmitQueueToken($client));
  }

  public function testCachedUSSOTokenWithoutEnvironmentVariable() {
    Filesystem::writeFile(
      $this->cacheFile,
      json_encode(
        array(
          'createdAt' => time(),
          'token' => 'cached-usso-token',
        )));

    $client = new UberSubmitQueueClient(
      'https://'.self::TEST_HOST,
      'conduit-token');

    $this->assertEqual(
      'cached-usso-token',
      $this->getSubmitQueueToken($client));
  }

  private function getSubmitQueueToken(UberSubmitQueueClient $client) {
    $method = new ReflectionMethod($client, 'getSubmitQueueToken');
    $method->setAccessible(true);

    return $method->invoke($client);
  }

}
