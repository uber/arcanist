<?php

final class ArcanistBuildableWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'buildable';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **buildable** [--id __id__ | --phid __phid__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: http, https
          Fetch a Harbormaster buildable summary for a Differential revision.

          Exactly one of these must be provided:
            - **--id**: Differential revision ID (e.g. 123 or D123)
            - **--phid**: Differential revision PHID (e.g. PHID-DREV-...)

          Examples:
            $ arc buildable --id=123
            $ arc buildable --phid=PHID-DREV-abc123
EOTEXT
      );
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function getArguments() {
    return array(
      'id' => array(
        'param' => 'id',
        'help'  => pht('Differential revision ID (e.g. 123 or D123).'),
      ),
      'phid' => array(
        'param' => 'phid',
        'help'  => pht('Differential revision PHID (e.g. PHID-DREV-...).'),
        'conflicts' => array(
          'id' => pht('Specify either --id or --phid, but not both.'),
        ),
      ),
    );
  }

  public function run() {
    $revision_id = $this->getArgument('id');
    $revision_phid = $this->getArgument('phid');

    if (!$revision_id && !$revision_phid) {
      throw new ArcanistUsageException(
        pht('Specify either --id or --phid.'));
    }

    if ($revision_id) {
      $revision_id = $this->normalizeRevisionID($revision_id);
      if (!ctype_digit((string)$revision_id)) {
        throw new ArcanistUsageException(
          pht('Revision id must be a numeric value like 123 or D123.'));
      }
      $params = array('revisionID' => (int)$revision_id);
    } else {
      $params = array('revisionPHID' => (string)$revision_phid);
    }

    $result = $this->getConduit()->callMethodSynchronous(
      'harbormaster.getbuildablesummary',
      $params);

    echo json_encode($result)."\n";

    return 0;
  }
}

