<?php

// class which encapsulates complexity of getting jira issue
final class UberTask extends Phobject {
  private $future;
  private $issues = false;

  const URL = 'https://arcanist-the-service-staging.uberinternal.com/';

  public function __construct($jql = '', $url=self::URL) {
    $usso = new UberUSSO();
    $hostname = parse_url($url, PHP_URL_HOST);
    $token = $usso->maybeUseUSSOToken($hostname);
    if (!$token) {
      $token = $usso->getUSSOToken($hostname);
    }
    $payload = "{}";
    if ($jql) {
      $payload = json_encode(array('jql' => $jql));
    }
    $future = id(new HTTPSFuture($url, $payload))
      ->setFollowLocation(false)
      ->setMethod('POST')
      ->addHeader('Authorization', "Bearer ${token}")
      ->addHeader('Rpc-Caller', 'arcanist')
      ->addHeader('Rpc-Encoding', 'json')
      ->addHeader('Rpc-Procedure', 'ArcanistTheService::getIssues');
    $future->start();
    $this->future = $future;
  }

  public function getIssues() {
    if ($this->issues !== false) {
      return $this->issues;
    }
    list($body, $headers) = $this->future->resolvex();
    if (empty($body)) {
      $this->issues = array();
      return $this->issues;
    }
    $issues = phutil_json_decode($body);
    if (!$issues) {
      $this->issues = array();
      return $this->issues;
    }
    $this->issues = idx($issues, 'issues', array());
    return $this->issues;
  }
}
