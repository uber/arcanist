<?php

/**
 * Compares a given commit's contents to the contents of the last diff
 * of the referenced revision.
 *
 * Returns 0 - if a revision is found in commit message, or is explicitly
 *             specified, and content matches
 * Returns 1 - if an error occurs
 * Returns 2 - if no revision is found in commit message and not explicitly
 *             specified
 * Returns 3 - if a revision is found in commit message, or is explicitly
 *             specified, and content doesn't match
 *
 *
 * To manually test this workflow:
 *    `git log -n 100 --format=%H | xargs -n 1 ~/uber-repos/arcanist/bin/arc \
 *      compare-commit-to-revision --commit`
 */
final class CompareCommitToRevisionWorkflow extends ArcanistWorkflow {

  private $commit;
  private $revisionId;
  private $revision;
  private $repositoryId;
  private $useLocalEnlistment;

  public function getRevisionDict() {
    return $this->revision;
  }

  public function getWorkflowName() {
    return 'compare-commit-to-revision';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **compare-commit-to-revision** [__options__] [__ref__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, git/p4, hg

            Compare a commit to an associated revision.

EOTEXT
      );
  }

  public function requiresWorkingCopy() {
    return $this->getArgument('use-local-enlistment');
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return $this->getArgument('use-local-enlistment');
  }

  public function getArguments() {
    return array(
      'revision' => array(
        'param' => 'revision-id',
        'help' => pht(
          'the phab revision to use in the comparison'),
      ),

      'commit' => array(
        'param' => 'commit',
        'help' => pht(
          'The commit to use in the comparison.'),
      ),

      'repo-call-sign' => array(
        'param' => 'call-sign',
        'help' => pht(
          'The phab repo call-sign for the commit. e.g., GOCODVJ for go-code.'),
      ),

      'keep-diffs' => array(
        'param' => 'directory',
        'help' => pht(
          'Save the output to a file in the specified directory.'.
          '  File will be <commit>-<revision>-landed-commit.diff and '.
          '<commit>-<revision>-reviewed.diff '.
          ' for the landed commit and reviewed diff respectively.'),
      ),

      'no-normalize-line-numbers' => array(
        'help' => pht(
          'specify if we do want to normalize context line numbers from the '.
          'diffs we compare.'),
      ),

      'use-local-enlistment' => array(
        'help' => pht(
          'specify if we to get commit information from a local git enlistment'.
          ' instead of getting the data from conduit.'),
      ),

    );
  }


  public function run() {

    $this->commit = $this->getArgument('commit');
    $this->revisionId =  $this->getArgument('revision');
    $this->useLocalEnlistment = $this->getArgument('use-local-enlistment');

    if (!$this->commit) {
      echo pht('No commit specified.  Exiting');
      return 1;
    }


    // if a call-sign is specified, look up the repository PHID
    $repo_call_sign = $this->getArgument('repo-call-sign');
    if ($repo_call_sign) {
      $this->repositoryId =
          $this->getRepositoryPHIDFromCallSignConduit($repo_call_sign);
      if (!$this->repositoryId) {
        echo pht('No repository found for call sign %s.  Exiting',
                 $repo_call_sign);
        return 1;
      }
    }

    // if a revision is not specified, get revision from the commit message
    if (!$this->revisionId) {
      $commit_msg = '';
      if ($this->useLocalEnlistment) {
        // get the commit message from the local repository
        $repository_api = $this->getRepositoryAPI();
        $commit_msg = $repository_api->getCommitMessage($this->commit);
      } else {
        // get the commit message from conduit
        $commit_data = $this->getCommitDataConduit($this->commit,
                                                   $this->repositoryId);

        $data = $commit_data['data'];
        if (count($data) < 1) {
          echo pht('No commit found for commit %s.  Exiting', $this->commit);
          return 1;
        }

        if (count($data) > 1) {
            echo pht('Found %d commits in phabricator for commit sha %s. '.
            'Specify repo-call-sign param to select one commit. Exiting'
            , count($data), $this->commit);
            return 1;
        }

        $commit_msg = $data[0]['fields']['message'];

        if (!$this->repositoryId) {
          $this->repositoryId = $data[0]['fields']['repositoryPHID'];
        }
      }

      // parse commit message for revision id
      $matches = array();
      preg_match_all(
        '/Differential Revision\:\s*https:\/\/code.uberinternal.com\/D(\d*)/',
                     $commit_msg, $matches);
      if (count($matches) != 2) {
        echo pht('unexpected result from regex.  Expected 2, got %d',
                 count($matches)), "\n";
      }

      if (count($matches[1]) > 0) {
        # use last revision id found in commit message
        $this->revisionId = $matches[1][count($matches[1]) - 1];
      }
    }

    if (!$this->revisionId) {
      echo pht('No revision found for commit %s.  Exiting', $this->commit),
           "\n";
      return 2;
    }

    $this->revision = $this->loadRevision($this->revisionId);

    // get the patch for the commit
    $diff_id = head($this->revision['diffs']);
    $base_commit = sprintf('%s~1', $this->commit);
    $commit_diff = '';
    if ($this->useLocalEnlistment) {
      $commit_diff =  $repository_api->getFullGitDiff(
             $base_commit,
             $this->commit);
      } else {
        $commit_diff = $this->getCommitDiffConduit(
          $this->repositoryId, $this->commit, $base_commit);
    }
    $local_diff = $this->normalizeDiff($commit_diff);

    // get the patch for the last diff of the revision
    $reviewed_diff = $this->normalizeDiff(
      $this->getConduit()->callMethodSynchronous(
        'differential.getrawdiff',
        array('diffID' => $diff_id)));

    // compare the two
    if ($local_diff !== $reviewed_diff) {
      // optionally save the diffs to a files for debugging
      $diff_command = $this->saveDiffs($local_diff, $reviewed_diff);

      $msg = pht('BAD: Content did not match.  Compared commit %s with '.
                 'revision https://code.uberinternal.com/D%s, diff %s',
                 $this->commit, $this->revisionId, $diff_id);
      if ($diff_command != "") {
        $msg = pht('%s. %s', $msg, $diff_command);
      }
      echo $msg, "\n";

      return 3;
    } else {
      echo pht('GOOD: Content matched. Compared commit %s with revision '.
              'https://code.uberinternal.com/D%s, diff %s',
      $this->commit, $this->revisionId, $diff_id), "\n";
    }

    return 0;
  }

  /**
   * Save the patches to files for offline review.  Returns a diff command
   * to diff the two patch files.
   */
  private function saveDiffs($landed_diff, $reviewed_diff) {
    $save_dir = $this->getArgument('keep-diffs');
    if ($save_dir) {
      $landed_filename = sprintf('%s/%s-%s-landed-commit.diff', $save_dir,
                                 $this->commit, $this->revisionId);
      file_put_contents($landed_filename, $landed_diff);
      $reviewed_filename = sprintf('%s/%s-%s-reviewed.diff', $save_dir,
                                   $this->commit, $this->revisionId);
      file_put_contents($reviewed_filename, $reviewed_diff);
      $diff_command = sprintf('diff %s %s', $landed_filename,
                              $reviewed_filename);
      return $diff_command;
    }
    return "";
  }

  /**
   * Given a phab repository call-sign, look up the repository PHID
   */
  private function getRepositoryPHIDFromCallSignConduit($repo_call_sign) {
    $params = array(
      'constraints' => array(
          'callsigns' => array($repo_call_sign)
    ),);

    $result = $this->getConduit()->callMethodSynchronous(
        'diffusion.repository.search', $params);

    $data = $result['data'];
    if (count($data) < 1) {
      return '';
    }

    $repo_phid = $data[0]['phid'];
    return $repo_phid;
  }


  /**
   * Given a commit sha, look up the commit data from conduit
   */
  private function getCommitDataConduit($commit_sha, $repository_id = NULL) {
    $params = array(
      'constraints' => array(
      'identifiers' => array($commit_sha),
    ),);

    if ($repository_id) {
      $params['constraints']['repositories'] = array($repository_id);
    }

    $result = $this->getConduit()->callMethodSynchronous(
        'diffusion.commit.search', $params);
    return $result;
  }


  /**
   * Get the diff between two commits from conduit
   */
  private function getCommitDiffConduit($repository_id,
                                        $commit_sha,
                                        $base_commit) {
    $params = array(
      'repository' => $repository_id,
      'commit' => $commit_sha,
      'againstCommit' => $base_commit,
      'linesOfContext' => 3,
    );

    $result = $this->getConduit()->callMethodSynchronous(
        'diffusion.rawdiffquery', $params);


    if ($result['tooSlow']) {
      throw new Exception(
        pht ('Diff between %s and %s for repo %s is too slow to generate. '.
        'Exiting',
        $base_commit, $commit_sha, $repository_id));
    }

    if ($result['tooHuge']) {
      throw new Exception(
        pht ('Diff between %s and %s for repo %s is too big to generate. '.
        'Exiting',
        $base_commit, $commit_sha, $repository_id));
    }

    $phid  = $result['filePHID'];
    $params = array(
      'phid' => $phid,
    );

    $result = $this->getConduit()->callMethodSynchronous(
        'file.download', $params);
    $diff = base64_decode($result);

    return $diff;
  }

  /**
   * normalize a textual diff for comparison purposes
   */
  private function normalizeDiff($text) {
    $changes = id(new ArcanistDiffParser())->parseDiff($text);
    ksort($changes);
    $val = ArcanistBundle::newFromChanges($changes)->toGitPatch();
    if (!$this->getArgument('no-normalize-line-numbers')) {
      $val = preg_replace('/^[<>]?\s*@@\s*[+-]\d*,\d*\s*[+-]\d*,\d*\s*@@$/m',
                          '@@ omitted-line-number @@', $val);
    }

    // Strip out all lines of "context", since sometimes that causes incorrect
    // matches. A "context line" in a patch is a line that starts with a space.
    $val = preg_replace('/^ .*$/m', '', $val);

    // sometimes, the patches differ only by copy or rename.  Normlize them.
    $val = preg_replace('/^(copy|rename)(.*)$/m', 'copy-or-rename$2', $val);
    return $val;
  }

  /**
   * load a phabricator revision from conduit
   */
  private function loadRevision($revision_id) {
    $revision_id = $this->normalizeRevisionID($revision_id);
    $revisions = $this->getConduit()->callMethodSynchronous(
        'differential.query',
        array(
          'ids' => array($revision_id),
        ));
      if (!$revisions) {
        throw new ArcanistUsageException(pht(
          "No such revision '%s'!",
          "D{$revision_id}"));
      }

    if (!count($revisions)) {
      throw new ArcanistUsageException(pht(
        'Bad revision specified.  Revision %s Not found',
        $revision_id));
    } else if (count($revisions) > 1) {
      throw new ArcanistUsageException(pht(
        'More than one revision found.  Found %d revisions for revision ID %s'.
        '. Expected 1.',
        count($revisions),
        $revision_id));
    }

    return head($revisions);
  }
}
