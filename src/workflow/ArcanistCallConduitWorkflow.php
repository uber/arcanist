<?php

/**
 * Provides command-line access to the Conduit API.
 */
final class ArcanistCallConduitWorkflow extends ArcanistWorkflow {

  public function getWorkflowName() {
    return 'call-conduit';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **call-conduit** __method__
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: http, https
          Allows you to make a raw Conduit method call:

            - Run this command from a working directory.
            - Call parameters are REQUIRED and read as a JSON blob from stdin.
            - Results are written to stdout as a JSON blob.
            - Requires a valid Conduit API token set in ~/.arcrc (run `arc install-certificate` to get one)

          This workflow is primarily useful for writing scripts which integrate
          with Phabricator.

          Examples:
            
            Ping (check connectivity):
            $ echo '{}' | arc call-conduit conduit.ping

            Get Current User:
            $ echo '{}' | arc call-conduit user.whoami

            Get User Info:
            $ echo '{"constraints":{"usernames":["wua", "foo"]}}' | arc call-conduit user.search

            Search Revisions by ID:
            $ echo '{"constraints":{"ids":[123,456,789]}}' | arc call-conduit differential.revision.search
            
            Search Revisions by PHID:
            $ echo '{"constraints":{"phids":["PHID-DREV-wd4ovydtv4m5nsz6enax"]}}' | arc call-conduit differential.revision.search
            
            Search Revisions by User:
            $ echo '{"constraints":{"authorPHIDs":["PHID-USER-g34zkv234n3rk3xlhgke"]}}' | arc call-conduit differential.revision.search

            Update Revision Title:
            $ echo '{"transactions":[{"type":"title","value":"my new title"}],"objectIdentifier":"PHID-DREV-zp4o4lfpsfwhnkglxln3"}' | arc call-conduit differential.revision.edit
            
            Update Revision Jira:
            $ echo '{"transactions":[{"type":"uber-jira.issues","value":["CODE-204"]}],"objectIdentifier":"PHID-DREV-zp4o4lfpsfwhnkglxln3"}' | arc call-conduit differential.revision.edit
            
            Update Revision Accept:
            $ echo '{"transactions":[{"type":"accept","value":true}],"objectIdentifier":"PHID-DREV-sangdbezeh5nfeicqzeg"}' | arc call-conduit differential.revision.edit

            Add Projects to Revision:
            $ echo '{"transactions":[{"type":"projects.add","value":["PHID-PROJ-u4i3446wedyolppkckbp"]}],"objectIdentifier":"PHID-DREV-zp4o4lfpsfwhnkglxln3"}' | arc call-conduit differential.revision.edit
            
            Remove Projects from Revision:
            $ echo '{"transactions":[{"type":"projects.remove","value":["PHID-PROJ-u4i3446wedyolppkckbp"]}],"objectIdentifier":"PHID-DREV-zp4o4lfpsfwhnkglxln3"}' | arc call-conduit differential.revision.edit
            
            Set Projects on Revision:
            $ echo '{"transactions":[{"type":"projects.set","value":["PHID-PROJ-u4i3446wedyolppkckbp"]}],"objectIdentifier":"PHID-DREV-zp4o4lfpsfwhnkglxln3"}' | arc call-conduit differential.revision.edit

            Get Revision's Projects (aka Tags):
            $ echo '{"constraints":{"ids":[13050281]},"attachments":{"projects":true}}' | arc call-conduit differential.revision.search
            
            Get Projects Details:
            $ echo '{"constraints":{"phids":["PHID-PROJ-u4i3446wedyolppkckbp"]}}' | arc call-conduit project.search

            Get Comments:
            $ echo '{"objectIdentifier":"D13050281"}' | arc call-conduit transaction.search
            
            Create General Comment:
            $ echo '{"revision_id":13050281,"message":"hi"}' | arc call-conduit differential.createcomment
            
            Create General Comment & Accept:
            $ echo '{"revision_id":13050281,"message":"a general comment!","action":"accept"}' | arc call-conduit differential.createcomment
            
            Create Inline Comment:
            $ echo '{"revisionID":13050281,"diffID":36482097,"filePath":"src/infra/devplatform/code-infra/code-review-ux/goo","isNewFile":true,"lineNumber":1,"content":"making an inline comment"}' | arc call-conduit differential.createinline

            Create Inline Comment (threaded comment):
            $ echo '{"revisionID":13050281,"diffID":36482097,"filePath":"src/infra/devplatform/code-infra/code-review-ux/goo","isNewFile":true,"lineNumber":1,"content":"making a comment thread","replyToCommentID":114219845}' | arc call-conduit differential.createinline

            Get Buildable:
            $ echo '{"constraints":{"containerPHIDs":["PHID-DREV-zp4o4lfpsfwhnkglxln3"]}}' | arc call-conduit harbormaster.buildable.search
            
            Get Build:
            $ echo '{"constraints":{"buildables":["PHID-HMBB-qdryny55lxb5k3zjcpwr"]}}' | arc call-conduit harbormaster.build.search
            
            Get Build Targets:
            $ echo '{"constraints":{"buildPHIDs":["PHID-HMBD-bx3g3fvgi62z5txlgnom"]},"order":"newest"}' | arc call-conduit harbormaster.target.search
            
            Get Build Target Logs:
            $ echo '{"constraints":{"buildTargetPHIDs":["PHID-HMBT-cdnilgbxily6lmkss3oz"]},"order":"newest"}' | arc call-conduit harbormaster.log.search

            Get File (returns file as base64-encoded string):
            $ echo "{\"phid\":\"PHID-FILE-477xrgjyhhxd4qcurgtx\"}" | arc call-conduit file.download

            Get Repository:
            $ echo '{"constraints":{"phids":["PHID-REPO-uexvk77yeovy63fhokqw"]}}' | arc call-conduit diffusion.repository.search

            Get Repository by Callsign:
            $ echo '{"constraints":{"callsigns":["GOCODVJ"]}}' | arc call-conduit diffusion.repository.search
EOTEXT
      );
  }

  public function getArguments() {
    return array(
      '*' => 'method',
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
    $method = $this->getArgument('method', array());
    if (count($method) !== 1) {
      throw new ArcanistUsageException(
        pht('Provide exactly one Conduit method name.'));
    }
    $method = reset($method);

    $console = PhutilConsole::getConsole();
    if (!function_exists('posix_isatty') || posix_isatty(STDIN)) {
      $console->writeErr(
        "%s\n",
        pht('Waiting for JSON parameters on stdin...'));
    }
    $params = @file_get_contents('php://stdin');
    try {
      $params = phutil_json_decode($params);
    } catch (PhutilJSONParserException $ex) {
      throw new ArcanistUsageException(
        pht('Provide method parameters on stdin as a JSON blob.'));
    }

    $error = null;
    $error_message = null;
    try {
      $result = $this->getConduit()->callMethodSynchronous(
        $method,
        $params);
    } catch (ConduitClientException $ex) {
      $error = $ex->getErrorCode();
      $error_message = $ex->getMessage();
      $result = null;
    }

    echo json_encode(array(
      'error'         => $error,
      'errorMessage'  => $error_message,
      'response'      => $result,
    ))."\n";

    return 0;
  }

}
