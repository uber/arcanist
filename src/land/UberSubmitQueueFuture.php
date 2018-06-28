<?php

final class UberSubmitQueueFuture extends FutureProxy {
	protected function didReceiveResult($result) {
		list($status, $body, $headers) = $result;
    // When a merge request is sent to SQ, SQ checks if there’s an existing request for
    // the same set of parameters, if it detects a duplicate it responds with 409 status
    // and includes the id of the request and the SQ url in the body
		if ($status->isError() && $status->getStatusCode() != 409) {
			throw $status;
		}

		$raw = $body;
		$shield = 'for(;;);';
    if (!strncmp($raw, $shield, strlen($shield))) {
      $raw = substr($raw, strlen($shield));
    }

    $data = null;
    try {
      $data = phutil_json_decode($raw);
    } catch (PhutilJSONParserException $ex) {
      throw new PhutilProxyException(
        pht(
          'Host returned HTTP/200, but invalid JSON data in response to '.
          'a SubmitQueue method call.'),
        $ex);
    }
    return $data['url'];
	}
}
