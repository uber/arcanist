<?php

final class UberSubmitQueueClient extends Phobject {

    // Environment variable holding a SubmitQueue token. When set, it is sent as
    // an `Authorization: Bearer` header on the outbound merge call, in the same
    // way `ARC_USSO_TOKEN` supplies a bearer token in UberUSSO. This is
    // independent of the `conduitToken` query parameter, which is unchanged.
    const SUBMITQUEUE_TOKEN_ENV = 'ARC_SUBMITQUEUE_TOKEN';

    private $uri;
    private $host;
    private $conduitToken;
    private $submitQueueToken;
    private $timeout;

    public function __construct($uri, $conduitToken, $timeout=10) {
        $this->uri = new PhutilURI($uri);
        if (!strlen($this->uri->getDomain())) {
            throw new Exception(
                pht("SubmitQueue URI '%s' must include a valid host.", $uri));
        }
        $this->host = $this->uri->getDomain();
        $this->conduitToken = $conduitToken;
        $this->submitQueueToken = (string)getenv(self::SUBMITQUEUE_TOKEN_ENV);
        $this->timeout = $timeout;
    }

    public function getHost() {
        return $this->host;
    }

    public function getConduitToken() {
        return $this->conduitToken;
    }

    public function getSubmitQueueToken() {
        return $this->submitQueueToken;
    }

    public function submitMergeRequest($remoteUrl, $diffId, $revisionId, $shouldShadow, $targetOnto) {
        $params = array(
          'remote' => $remoteUrl,
          'diffId' => $diffId,
          'revisionId' => $revisionId,
          'targetOnto' => $targetOnto,
          'conduitToken' => $this->conduitToken,
        );
        if ($shouldShadow) {
          $params['shouldShadow'] = "true";
        }
        return $this->callMethodSynchronous("POST", "/merge_requests", $params);
    }

  public function submitMergeStackRequest($remoteUrl, $stack, $shouldShadow, $targetOnto) {
    $params = array(
      'remote' => $remoteUrl,
      'targetOnto' => $targetOnto,
      'conduitToken' => $this->conduitToken,
      'stack' => json_encode($stack)
    );
    if ($shouldShadow) {
      $params['shouldShadow'] = "true";
    }
    return $this->callMethodSynchronous("POST", "/merge_requests", $params);
  }

    private function callMethodSynchronous($method, $api, array $params) {
        return $this->callMethod($method, $api, $params)->resolve();
    }

    private function callMethod($method, $api, array $params) {
        $req = id(clone $this->uri)->setPath('/api'.$api.'?'.http_build_query($params));
        // Always use the cURL-based HTTPSFuture, for proxy support and other
        // protocol edge cases that HTTPFuture does not support.
        $core_future = new HTTPSFuture($req);
        $core_future->addHeader('Host', $this->getHost());

        if (strlen($this->submitQueueToken)) {
            $core_future->addHeader(
                'Authorization',
                'Bearer '.$this->submitQueueToken);
        }

        $core_future->setMethod($method);
        $core_future->setTimeout($this->timeout);

        $json_future = new UberSubmitQueueFuture($core_future);
        $json_future->isReady();

        return $json_future;
    }
}
