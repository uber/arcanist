<?php

/**
 * Retrieve buildable information for a revision using either ID or PHID.
 */
final class ArcanistBuildableWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'buildable';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **buildable** --id=__revision_id__
      **buildable** --phid=__revision_phid__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: http, https
          Retrieve buildable information for a revision by specifying either
          the revision ID or PHID.
          
          Either --id or --phid must be specified.
          
          This command calls the harbormaster.getbuildablesummary Conduit API
          method to fetch buildable information for the specified revision.

          Examples:
            Get buildable for revision ID 123:
            $ arc buildable --id=123

            Get buildable for revision PHID:
            $ arc buildable --phid=PHID-DREV-abc123def456
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      'id' => array(
        'param' => 'revision_id',
        'help' => pht('Specify the revision ID to get the buildable for.'),
      ),
      'phid' => array(
        'param' => 'revision_phid',
        'help' => pht('Specify the revision PHID to get the buildable for.'),
      ),
    );
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

    // Validate that exactly one of id or phid is provided
    if (!$revision_id && !$revision_phid) {
      throw new ArcanistUsageException(
        pht('You must specify either --id or --phid to identify the revision.'));
    }

    if ($revision_id && $revision_phid) {
      throw new ArcanistUsageException(
        pht('You cannot specify both --id and --phid. Choose one.'));
    }

    $conduit = $this->getConduit();
    $params = array();

    // Set the revisionID parameter based on the provided input
    if ($revision_id) {
      // Validate that the ID is numeric
      if (!ctype_digit($revision_id)) {
        throw new ArcanistUsageException(
          pht('Revision ID must be a number.'));
      }
      $params['revisionID'] = (int)$revision_id;
    } else {
      // When PHID is provided, we need to convert it to a revision ID
      // First, let's get the revision information from the PHID
      try {
        $revision_info = $conduit->callMethodSynchronous(
          'differential.revision.search',
          array(
            'constraints' => array(
              'phids' => array($revision_phid),
            ),
          ));
        
        if (empty($revision_info['data'])) {
          throw new ArcanistUsageException(
            pht('No revision found for PHID: %s', $revision_phid));
        }
        
        $revision = reset($revision_info['data']);
        $params['revisionID'] = (int)$revision['id'];
        
      } catch (ConduitClientException $ex) {
        throw new ArcanistUsageException(
          pht('Failed to resolve PHID "%s": %s', $revision_phid, $ex->getMessage()));
      }
    }

    // Call the harbormaster.getbuildablesummary method
    try {
      $result = $conduit->callMethodSynchronous(
        'harbormaster.getbuildablesummary',
        $params);

      // Format and display the result
      $this->displayBuildableInfo($result, $revision_id, $revision_phid);

    } catch (ConduitClientException $ex) {
      throw new ArcanistUsageException(
        pht('Failed to get buildable summary: %s', $ex->getMessage()));
    }

    return 0;
  }

  private function displayBuildableInfo($result, $revision_id, $revision_phid) {
    $console = PhutilConsole::getConsole();
    
    // Display header
    if ($revision_id) {
      $console->writeOut("**Buildable Summary for Revision ID: %s**\n\n", $revision_id);
    } else {
      $console->writeOut("**Buildable Summary for Revision PHID: %s**\n\n", $revision_phid);
    }

    // Display the result as formatted JSON for now
    // This can be enhanced to show more structured output based on the actual API response
    if (is_array($result) || is_object($result)) {
      $console->writeOut("%s\n", json_encode($result, JSON_PRETTY_PRINT));
    } else {
      $console->writeOut("%s\n", $result);
    }
  }

}