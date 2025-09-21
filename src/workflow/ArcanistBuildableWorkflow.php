<?php

final class ArcanistBuildableWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'buildable';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **buildable** --id __revisionID__
      **buildable** --phid __revisionPHID__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: http, https
          Fetch Harbormaster buildable summary for a Differential revision.

            - Specify either --id or --phid to identify the revision.
            - Prints the raw JSON response to stdout.

          Examples:

            $ arc buildable --id=12345
            $ arc buildable --phid=PHID-DREV-abc123
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'id' => array(
        'param' => 'id',
        'help'  => pht('Differential revision ID (e.g., 12345).'),
        'conflicts' => array(
          'phid' => pht('Specify either --id or --phid, not both.'),
        ),
      ),
      'phid' => array(
        'param' => 'phid',
        'help'  => pht('Differential revision PHID (e.g., PHID-DREV-...).'),
        'conflicts' => array(
          'id' => pht('Specify either --phid or --id, not both.'),
        ),
      ),
    );
  }

  protected function shouldShellComplete() {
    return false;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function run() {
    $revision_id = $this->getArgument('id');
    $revision_phid = $this->getArgument('phid');

    if (!$revision_id && !$revision_phid) {
      throw new ArcanistUsageException(
        pht('You must specify either --id or --phid to identify the revision.'));
    }

    $params = array();
    if ($revision_id) {
      // Normalize like other commands do; accept forms like "D1234".
      $params['revisionID'] = (int)$this->normalizeRevisionID($revision_id);
    }
    if ($revision_phid) {
      $params['revisionPHID'] = (string)$revision_phid;
    }

    try {
      $result = $this->getConduit()->callMethodSynchronous(
        'harbormaster.getbuildablesummary',
        $params);
    } catch (ConduitClientException $ex) {
      // Match arc call-conduit output shape for errors.
      echo json_encode(array(
        'error' => $ex->getErrorCode(),
        'errorMessage' => $ex->getMessage(),
        'response' => null,
      ))."\n";
      return 1;
    }

    echo json_encode($result)."\n";

    return 0;
  }
}

