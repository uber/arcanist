<?php

final class UberUSSOTestCase extends PhutilTestCase {

  private $savedToken;

  protected function willRunOneTest($test) {
    $this->savedToken = getenv(UberUSSO::USSO_TOKEN_ENV);
    putenv(UberUSSO::USSO_TOKEN_ENV);
  }

  protected function didRunOneTest($test) {
    if ($this->savedToken === false) {
      putenv(UberUSSO::USSO_TOKEN_ENV);
    } else {
      putenv(UberUSSO::USSO_TOKEN_ENV.'='.$this->savedToken);
    }
  }

  public function testConduitEnvironmentToken() {
    putenv(UberUSSO::USSO_TOKEN_ENV.'=conduit-usso-token');

    $conduit = new ConduitClient('https://phabricator.example.com/api/');
    $usso = new UberUSSO();

    $this->assertTrue($usso->enhanceConduitClient($conduit));

    $property = new ReflectionProperty($conduit, 'extraHeaders');
    $property->setAccessible(true);
    $headers = $property->getValue($conduit);

    $this->assertEqual(
      'Bearer conduit-usso-token',
      idx($headers, 'Authorization'));
  }

}
