<?php

/**
 * Lands a branch by rebasing, merging and amending it.
 */
final class ArcanistLandWorkflow extends ArcanistWorkflow {

  private $isGit;
  private $isGitSvn;
  private $isHg;
  private $isHgSvn;

  private $oldBranch;
  private $branch;
  private $onto;
  private $ontoRemoteBranch;
  private $remote;
  private $useSquash;
  private $keepBranch;
  private $shouldUpdateWithRebase;
  private $branchType;
  private $ontoType;
  private $preview;
  private $shouldRunUnit;
  private $shouldUseSubmitQueue;
  private $submitQueueRegex;
  private $submitQueueUri;
  private $submitQueueShadowMode;
  private $submitQueueClient;
  private $tbr;
  private $submitQueueTags;

  private $revision;
  private $messageFile;

  const REFTYPE_BRANCH = 'branch';
  const REFTYPE_BOOKMARK = 'bookmark';

  public function getRevisionDict() {
    return $this->revision;
  }

  public function getWorkflowName() {
    return 'land';
  }

  public function getCommandSynopses() {
    return phutil_console_format(<<<EOTEXT
      **land** [__options__] [__ref__]
EOTEXT
      );
  }

  public function getCommandHelp() {
    return phutil_console_format(<<<EOTEXT
          Supports: git, hg

          Publish an accepted revision after review. This command is the last
          step in the standard Differential pre-publish code review workflow.

          This command merges and pushes changes associated with an accepted
          revision that are currently sitting in __ref__, which is usually the
          name of a local branch. Without __ref__, the current working copy
          state will be used.

          Under Git: branches, tags, and arbitrary commits (detached HEADs)
          may be landed.

          Under Mercurial: branches and bookmarks may be landed, but only
          onto a target of the same type. See T3855.

          The workflow selects a target branch to land onto and a remote where
          the change will be pushed to.

          A target branch is selected by examining these sources in order:

            - the **--onto** flag;
            - the upstream of the current branch, recursively (Git only);
            - the __arc.land.onto.default__ configuration setting;
            - or by falling back to a standard default:
              - "master" in Git;
              - "default" in Mercurial.

          A remote is selected by examining these sources in order:

            - the **--remote** flag;
            - the upstream of the current branch, recursively (Git only);
            - or by falling back to a standard default:
              - "origin" in Git;
              - the default remote in Mercurial.

          After selecting a target branch and a remote, the commits which will
          be landed are printed.

          With **--preview**, execution stops here, before the change is
          merged.

          The change is merged with the changes in the target branch,
          following these rules:

          In repositories with mutable history or with **--squash**, this will
          perform a squash merge (the entire branch will be represented as one
          commit after the merge).

          In repositories with immutable history or with **--merge**, this will
          perform a strict merge (a merge commit will always be created, and
          local commits will be preserved).

          The resulting commit will be given an up-to-date commit message
          describing the final state of the revision in Differential.

          In Git, the merge occurs in a detached HEAD. The local branch
          reference (if one exists) is not updated yet.

          With **--hold**, execution stops here, before the change is pushed.

          The change is pushed into the remote.

          Consulting mystical sources of power, the workflow makes a guess
          about what state you wanted to end up in after the process finishes
          and the working copy is put into that state.

          The branch which was landed is deleted, unless the **--keep-branch**
          flag was passed or the landing branch is the same as the target
          branch.

EOTEXT
      );
  }

  public function requiresWorkingCopy() {
    return true;
  }

  public function requiresConduit() {
    return true;
  }

  public function requiresAuthentication() {
    return true;
  }

  public function requiresRepositoryAPI() {
    return true;
  }

  public function getArguments() {
    return array(
      'onto' => array(
        'param' => 'master',
        'help' => pht(
          "Land feature branch onto a branch other than the default ".
          "('master' in git, 'default' in hg). You can change the default ".
          "by setting '%s' with `%s` or for the entire project in %s.",
          'arc.land.onto.default',
          'arc set-config',
          '.arcconfig'),
      ),
      'hold' => array(
        'help' => pht(
          'Prepare the change to be pushed, but do not actually push it.'),
      ),
      'keep-branch' => array(
        'help' => pht(
          'Keep the feature branch after pushing changes to the '.
          'remote (by default, it is deleted).'),
      ),
      'remote' => array(
        'param' => 'origin',
        'help' => pht(
          "Push to a remote other than the default ('origin' in git)."),
      ),
      'merge' => array(
        'help' => pht(
          'Perform a %s merge, not a %s merge. If the project '.
          'is marked as having an immutable history, this is the default '.
          'behavior.',
          '--no-ff',
          '--squash'),
        'supports' => array(
          'git',
        ),
        'nosupport'   => array(
          'hg' => pht(
            'Use the %s strategy when landing in mercurial.',
            '--squash'),
        ),
      ),
      'squash' => array(
        'help' => pht(
          'Perform a %s merge, not a %s merge. If the project is '.
          'marked as having a mutable history, this is the default behavior.',
          '--squash',
          '--no-ff'),
        'conflicts' => array(
          'merge' => pht(
            '%s and %s are conflicting merge strategies.',
            '--merge',
            '--squash'),
        ),
      ),
      'delete-remote' => array(
        'help' => pht(
          'Delete the feature branch in the remote after landing it.'),
        'conflicts' => array(
          'keep-branch' => true,
        ),
      ),
      'update-with-rebase' => array(
        'help' => pht(
          "When updating the feature branch, use rebase instead of merge. ".
          "This might make things work better in some cases. Set ".
          "%s to '%s' to make this the default.",
          'arc.land.update.default',
          'rebase'),
        'conflicts' => array(
          'merge' => pht(
            'The %s strategy does not update the feature branch.',
            '--merge'),
          'update-with-merge' => pht(
            'Cannot be used with %s.',
            '--update-with-merge'),
        ),
        'supports' => array(
          'git',
        ),
      ),
      'update-with-merge' => array(
        'help' => pht(
          "When updating the feature branch, use merge instead of rebase. ".
          "This is the default behavior. Setting %s to '%s' can also be ".
          "used to make this the default.",
          'arc.land.update.default',
          'merge'),
        'conflicts' => array(
          'merge' => pht(
            'The %s strategy does not update the feature branch.',
            '--merge'),
          'update-with-rebase' => pht(
            'Cannot be used with %s.',
            '--update-with-rebase'),
        ),
        'supports' => array(
          'git',
        ),
      ),
      'revision' => array(
        'param' => 'id',
        'help' => pht(
          'Use the message from a specific revision, rather than '.
          'inferring the revision based on branch content.'),
      ),
      'preview' => array(
        'help' => pht(
          'Prints the commits that would be landed. Does not '.
          'actually modify or land the commits.'),
      ),
      '*' => 'branch',
      'tbr' => array(
        'help' => pht(
          'tbr: To-Be-Reviewed. Skips the submit-queue if the submit-queue '.
          'is enabled for this repo.'),
        'supports' => array(
          'git',
        ),
      ),
      'uber-skip-update' => array(
        'help' => pht('uber-skip-update: Skip updating working copy'),
        'supports' => array('git',),
      ),
      'nounit' => array(
        'help' => pht('Do not run unit tests.'),
      ),
      'use-sq' => array(
        'help' => pht(
          'force using the submit-queue if the submit-queue is configured '.
          'for this repo.'),
        'supports' => array(
          'git',
        ),
      ),
    );
  }

  private function uberTbrGetExcuse($prompt, $history) {
    $console = PhutilConsole::getConsole();
    $history = $this->getRepositoryAPI()->getScratchFilePath($history);
    $excuse = phutil_console_prompt($prompt, $history);
    if ($excuse == '') {
      throw new ArcanistUserAbortException();
    }
    return $excuse;
  }

  private function uberCreateTask($revision) {
    if (empty($this->submitQueueTags)) {
      return;
    }

    $console = PhutilConsole::getConsole();
    $excuse = $this->uberTbrGetExcuse(
      pht('Provide explanation for skipping SubmitQueue or press Enter to abort.'),
      'tbr-excuses');
    $args = array(
      pht('%s is skipping SubmitQueue', 'D' . $revision['id']),
      '--uber-description',
      pht("%s is skipping SubmitQueue\n Author: %s\n Excuse: %s\n",
        'D' . $revision['id'],
        $this->getUserName(),
        $excuse),
      '--browse');
    foreach ($this->submitQueueTags as $tag) {
      array_push($args, "--project", $tag);
    }

    $owners = $this->getConfigFromAnySource("uber.land.submitqueue.owners");
    foreach ($owners as $owner) {
      array_push($args, "--cc", $owner);
    }

    $todo_workflow = $this->buildChildWorkflow('todo', $args);
    $todo_workflow->run();
  }

  /**
   * @task lintunit
   */
  private function uberRunUnit() {
    if ($this->getArgument('nounit')) {
      return ArcanistUnitWorkflow::RESULT_SKIP;
    }
    $console = PhutilConsole::getConsole();

    $repository_api = $this->getRepositoryAPI();

    $console->writeOut("%s\n", pht('Running unit tests...'));
    try {
      $argv = $this->getPassthruArgumentsAsArgv('unit');
      if ($repository_api->supportsCommitRanges()) {
        $argv[] = '--rev';
        $argv[] = $repository_api->getBaseCommit();
      }
      $unit_workflow = $this->buildChildWorkflow('unit', $argv);
      $unit_result = $unit_workflow->run();

      switch ($unit_result) {
        case ArcanistUnitWorkflow::RESULT_OKAY:
          $console->writeOut(
            "<bg:green>** %s **</bg> %s\n",
            pht('UNIT OKAY'),
            pht('No unit test failures.'));
          break;
        case ArcanistUnitWorkflow::RESULT_UNSOUND:
          if ($this->getArgument('ignore-unsound-tests')) {
            echo phutil_console_format(
              "<bg:yellow>** %s **</bg> %s\n",
              pht('UNIT UNSOUND'),
              pht(
                'Unit testing raised errors, but all '.
                'failing tests are unsound.'));
          } else {
            $continue = $console->confirm(
              pht(
                'Unit test results included failures, but all failing tests '.
                'are known to be unsound. Ignore unsound test failures?'));
            if (!$continue) {
              throw new ArcanistUserAbortException();
            }
          }
          break;
        case ArcanistUnitWorkflow::RESULT_FAIL:
          $console->writeOut(
            "<bg:red>** %s **</bg> %s\n",
            pht('UNIT ERRORS'),
            pht('Unit testing raised errors!'));
          $ok = phutil_console_confirm(pht("Revision does not pass arc unit. Continue anyway?"));
          if (!$ok) {
            throw new ArcanistUserAbortException();
          }
          break;
      }

      $testResults = array();
      foreach ($unit_workflow->getTestResults() as $test) {
        $testResults[] = $test->toDictionary();
      }

      return $unit_result;
    } catch (ArcanistNoEngineException $ex) {
      $console->writeOut(
        "%s\n",
        pht('No unit test engine is configured for this project.'));
    } catch (ArcanistNoEffectException $ex) {
      $console->writeOut("%s\n", $ex->getMessage());
    }

    return null;
  }

  private function uberShouldRunSubmitQueue($revision, $regex) {
    if (empty($regex)) {
      return true;
    }

    $diff = head(
      $this->getConduit()->callMethodSynchronous(
        'differential.querydiffs',
        array('ids' => array(head($revision['diffs'])))));
    $changes = array();
    foreach ($diff['changes'] as $changedict) {
      $changes[] = ArcanistDiffChange::newFromDictionary($changedict);
    }

    foreach ($changes as $change) {
      if (preg_match($regex, $change->getOldPath())) {
        return true;
      }

      if (preg_match($regex, $change->getCurrentPath())) {
        return true;
      }
    }

    return false;
  }

  public function run() {
    $this->readArguments();
    if ($this->shouldRunUnit) {
      $this->uberRunUnit();
    }

    $engine = null;
    $uberShadowEngine = null;
    if ($this->isGit && !$this->isGitSvn) {
      $engine = new ArcanistGitLandEngine();
      if ($this->shouldUseSubmitQueue) {
        $revision = $this->uberGetRevision();
        if ($this->uberShouldRunSubmitQueue($revision, $this->submitQueueRegex)) {
          if ($this->tbr) {
            $this->uberCreateTask($revision);
          } else {
            // If the shadow-mode is on, then initialize the shadowEngine
            if ($this->submitQueueShadowMode) {
              $uberShadowEngine = new UberArcanistSubmitQueueEngine(
                $this->submitQueueClient,
                $this->getConduit());
              $uberShadowEngine =
                $uberShadowEngine
                  ->setRevision($revision)
                  ->setSkipUpdateWorkingCopy($this->getArgument('uber-skip-update'));
            } else {
              $engine = new UberArcanistSubmitQueueEngine(
                $this->submitQueueClient,
                $this->getConduit());
              $engine =
                $engine
                  ->setRevision($revision)
                  ->setSkipUpdateWorkingCopy($this->getArgument('uber-skip-update'));
            }
          }
        }
      }
    }

    if ($engine) {
      $this->readEngineArguments();

      $obsolete = array(
        'delete-remote',
        'update-with-merge',
        'update-with-rebase',
      );

      foreach ($obsolete as $flag) {
        if ($this->getArgument($flag)) {
          throw new ArcanistUsageException(
            pht(
              'Flag "%s" is no longer supported under Git.',
              '--'.$flag));
        }
      }

      $this->requireCleanWorkingCopy();

      $should_hold = $this->getArgument('hold');

      $engine
        ->setWorkflow($this)
        ->setRepositoryAPI($this->getRepositoryAPI())
        ->setSourceRef($this->branch)
        ->setTargetRemote($this->remote)
        ->setTargetOnto($this->onto)
        ->setShouldHold($should_hold)
        ->setShouldKeep($this->keepBranch)
        ->setShouldSquash($this->useSquash)
        ->setShouldPreview($this->preview)
        ->setBuildMessageCallback(array($this, 'buildEngineMessage'));

      // initialize the shadow engine and execute it if uberShadowEngine is initialized
      if ($uberShadowEngine) {
        $uberShadowEngine
          ->setWorkflow($this)
          ->setRepositoryAPI($this->getRepositoryAPI())
          ->setSourceRef($this->branch)
          ->setTargetRemote($this->remote)
          ->setTargetOnto($this->onto)
          ->setShouldHold($should_hold)
          ->setShouldKeep($this->keepBranch)
          ->setShouldSquash($this->useSquash)
          ->setShouldPreview($this->preview)
          ->setBuildMessageCallback(array($this, 'buildEngineMessage'))
          ->setShouldShadow(true);
        $uberShadowEngine->execute();
      }

      $engine->execute();

      if (!$should_hold && !$this->preview) {
        $this->didPush();
      }

      return 0;
    }

    $this->validate();

    try {
      $this->pullFromRemote();
    } catch (Exception $ex) {
      $this->restoreBranch();
      throw $ex;
    }

    $this->printPendingCommits();
    if ($this->preview) {
      $this->restoreBranch();
      return 0;
    }

    $this->checkoutBranch();
    $this->findRevision();

    if ($this->useSquash) {
      $this->rebase();
      $this->squash();
    } else {
      $this->merge();
    }

    $this->push();

    if (!$this->keepBranch) {
      $this->cleanupBranch();
    }

    if ($this->oldBranch != $this->onto) {
      // If we were on some branch A and the user ran "arc land B",
      // switch back to A.
      if ($this->keepBranch || $this->oldBranch != $this->branch) {
        $this->restoreBranch();
      }
    }

    echo pht('Done.'), "\n";

    return 0;
  }

  private function getUpstreamMatching($branch, $pattern) {
    if ($this->isGit) {
      $repository_api = $this->getRepositoryAPI();
      list($err, $fullname) = $repository_api->execManualLocal(
        'rev-parse --symbolic-full-name %s@{upstream}',
        $branch);
      if (!$err) {
        $matches = null;
        if (preg_match($pattern, $fullname, $matches)) {
          return last($matches);
        }
      }
    }
    return null;
  }

  private function readEngineArguments() {
    // NOTE: This is hard-coded for Git right now.
    // TODO: Clean this up and move it into LandEngines.

    $onto = $this->getEngineOnto();
    $remote = $this->getEngineRemote();

    // This just overwrites work we did earlier, but it has to be up in this
    // class for now because other parts of the workflow still depend on it.
    $this->onto = $onto;
    $this->remote = $remote;
    $this->ontoRemoteBranch = $this->remote.'/'.$onto;
  }

  private function getEngineOnto() {
    $onto = $this->getArgument('onto');
    if ($onto !== null) {
      $this->writeInfo(
        pht('TARGET'),
        pht(
          'Landing onto "%s", selected by the --onto flag.',
          $onto));
      return $onto;
    }

    $api = $this->getRepositoryAPI();
    $path = $api->getPathToUpstream($this->branch);

    if ($path->getLength()) {
      $cycle = $path->getCycle();
      if ($cycle) {
        $this->writeWarn(
          pht('LOCAL CYCLE'),
          pht(
            'Local branch tracks an upstream, but following it leads to a '.
            'local cycle; ignoring branch upstream.'));

        echo tsprintf(
          "\n    %s\n\n",
          implode(' -> ', $cycle));

      } else {
        if ($path->isConnectedToRemote()) {
          $onto = $path->getRemoteBranchName();
          $this->writeInfo(
            pht('TARGET'),
            pht(
              'Landing onto "%s", selected by following tracking branches '.
              'upstream to the closest remote.',
              $onto));
          return $onto;
        } else {
          $this->writeInfo(
            pht('NO PATH TO UPSTREAM'),
            pht(
              'Local branch tracks an upstream, but there is no path '.
              'to a remote; ignoring branch upstream.'));
        }
      }
    }

    $config_key = 'arc.land.onto.default';
    $onto = $this->getConfigFromAnySource($config_key);
    if ($onto !== null) {
      $this->writeInfo(
        pht('TARGET'),
        pht(
          'Landing onto "%s", selected by "%s" configuration.',
          $onto,
          $config_key));
      return $onto;
    }

    $onto = 'master';
    $this->writeInfo(
      pht('TARGET'),
      pht(
        'Landing onto "%s", the default target under git.',
        $onto));
    return $onto;
  }

  private function getEngineRemote() {
    $remote = $this->getArgument('remote');
    if ($remote !== null) {
      $this->writeInfo(
        pht('REMOTE'),
        pht(
          'Using remote "%s", selected by the --remote flag.',
          $remote));
      return $remote;
    }

    $api = $this->getRepositoryAPI();
    $path = $api->getPathToUpstream($this->branch);

    $remote = $path->getRemoteRemoteName();
    if ($remote !== null) {
      $this->writeInfo(
        pht('REMOTE'),
        pht(
          'Using remote "%s", selected by following tracking branches '.
          'upstream to the closest remote.',
          $remote));
      return $remote;
    }

    $remote = 'origin';
    $this->writeInfo(
      pht('REMOTE'),
      pht(
        'Using remote "%s", the default remote under git.',
        $remote));
    return $remote;
  }


  private function readArguments() {
    $repository_api = $this->getRepositoryAPI();
    $this->isGit = $repository_api instanceof ArcanistGitAPI;
    $this->isHg = $repository_api instanceof ArcanistMercurialAPI;

    if ($this->isGit) {
      $repository = $this->loadProjectRepository();
      $this->isGitSvn = (idx($repository, 'vcs') == 'svn');
    }

    if ($this->isHg) {
      $this->isHgSvn = $repository_api->isHgSubversionRepo();
    }

    $branch = $this->getArgument('branch');
    if (empty($branch)) {
      $branch = $this->getBranchOrBookmark();
      if ($branch) {
        $this->branchType = $this->getBranchType($branch);

        // TODO: This message is misleading when landing a detached head or
        // a tag in Git.

        echo pht("Landing current %s '%s'.", $this->branchType, $branch), "\n";
        $branch = array($branch);
      }
    }

    if (count($branch) !== 1) {
      throw new ArcanistUsageException(
        pht('Specify exactly one branch or bookmark to land changes from.'));
    }
    $this->branch = head($branch);
    $this->keepBranch = $this->getArgument('keep-branch');

    $update_strategy = $this->getConfigFromAnySource('arc.land.update.default');
    $this->shouldUpdateWithRebase = $update_strategy == 'rebase';
    if ($this->getArgument('update-with-rebase')) {
      $this->shouldUpdateWithRebase = true;
    } else if ($this->getArgument('update-with-merge')) {
      $this->shouldUpdateWithRebase = false;
    }

    $this->preview = $this->getArgument('preview');

    if (!$this->branchType) {
      $this->branchType = $this->getBranchType($this->branch);
    }

    $onto_default = $this->isGit ? 'master' : 'default';
    $onto_default = nonempty(
      $this->getConfigFromAnySource('arc.land.onto.default'),
      $onto_default);
    $onto_default = coalesce(
      $this->getUpstreamMatching($this->branch, '/^refs\/heads\/(.+)$/'),
      $onto_default);
    $this->onto = $this->getArgument('onto', $onto_default);
    $this->ontoType = $this->getBranchType($this->onto);

    $remote_default = $this->isGit ? 'origin' : '';
    $remote_default = coalesce(
      $this->getUpstreamMatching($this->onto, '/^refs\/remotes\/(.+?)\//'),
      $remote_default);
    $this->remote = $this->getArgument('remote', $remote_default);

    if ($this->getArgument('merge')) {
      $this->useSquash = false;
    } else if ($this->getArgument('squash')) {
      $this->useSquash = true;
    } else {
      $this->useSquash = !$this->isHistoryImmutable();
    }

    $this->ontoRemoteBranch = $this->onto;
    if ($this->isGitSvn) {
      $this->ontoRemoteBranch = 'trunk';
    } else if ($this->isGit) {
      $this->ontoRemoteBranch = $this->remote.'/'.$this->onto;
    }

    $this->oldBranch = $this->getBranchOrBookmark();
    $this->shouldRunUnit = nonempty(
      $this->getConfigFromAnySource('uber.land.run.unit'),
      false
    );

    $this->shouldUseSubmitQueue = nonempty(
        $this->getConfigFromAnySource('uber.land.submitqueue.enable'),
        $this->getArgument('use-sq'),
        false
    );

    if ($this->getArgument('tbr')) {
      $this->tbr = true;
    } else {
      $this->tbr = false;
    }
    if ($this->shouldUseSubmitQueue) {
      $this->submitQueueUri = $this->getConfigFromAnySource('uber.land.submitqueue.uri');
      $this->submitQueueShadowMode = $this->getConfigFromAnySource('uber.land.submitqueue.shadow');
      $this->submitQueueRegex = $this->getConfigFromAnySource('uber.land.submitqueue.regex');
      if(empty($this->submitQueueUri)) {
        $message = pht(
            "You are trying to use submitqueue, but the submitqueue URI for your repo is not set");
        throw new ArcanistUsageException($message);
      }
      $this->submitQueueClient =
        new UberSubmitQueueClient(
          $this->submitQueueUri,
          $this->getConduit()->getConduitToken());
      $this->submitQueueTags = $this->getConfigFromAnySource('uber.land.submitqueue.tags');
    }
  }

  private function validate() {
    $repository_api = $this->getRepositoryAPI();

    if ($this->onto == $this->branch) {
      $message = pht(
        "You can not land a %s onto itself -- you are trying ".
        "to land '%s' onto '%s'. For more information on how to push ".
        "changes, see 'Pushing and Closing Revisions' in 'Arcanist User ".
        "Guide: arc diff' in the documentation.",
        $this->branchType,
        $this->branch,
        $this->onto);
      if (!$this->isHistoryImmutable()) {
        $message .= ' '.pht("You may be able to '%s' instead.", 'arc amend');
      }
      throw new ArcanistUsageException($message);
    }

    if ($this->isHg) {
      if ($this->useSquash) {
        if (!$repository_api->supportsRebase()) {
          throw new ArcanistUsageException(
            pht(
              'You must enable the rebase extension to use the %s strategy.',
              '--squash'));
        }
      }

      if ($this->branchType != $this->ontoType) {
        throw new ArcanistUsageException(pht(
          'Source %s is a %s but destination %s is a %s. When landing a '.
          '%s, the destination must also be a %s. Use %s to specify a %s, '.
          'or set %s in %s.',
          $this->branch,
          $this->branchType,
          $this->onto,
          $this->ontoType,
          $this->branchType,
          $this->branchType,
          '--onto',
          $this->branchType,
          'arc.land.onto.default',
          '.arcconfig'));
      }
    }

    if ($this->isGit) {
      list($err) = $repository_api->execManualLocal(
        'rev-parse --verify %s',
        $this->branch);

      if ($err) {
        throw new ArcanistUsageException(
          pht("Branch '%s' does not exist.", $this->branch));
      }
    }

    $this->requireCleanWorkingCopy();
  }

  private function checkoutBranch() {
    $repository_api = $this->getRepositoryAPI();
    if ($this->getBranchOrBookmark() != $this->branch) {
      $repository_api->execxLocal('checkout %s', $this->branch);
    }

    switch ($this->branchType) {
      case self::REFTYPE_BOOKMARK:
        $message = pht(
          'Switched to bookmark **%s**. Identifying and merging...',
          $this->branch);
        break;
      case self::REFTYPE_BRANCH:
      default:
        $message = pht(
          'Switched to branch **%s**. Identifying and merging...',
          $this->branch);
        break;
    }

    echo phutil_console_format($message."\n");
  }

  private function printPendingCommits() {
    $repository_api = $this->getRepositoryAPI();

    if ($repository_api instanceof ArcanistGitAPI) {
      list($out) = $repository_api->execxLocal(
        'log --oneline %s %s --',
        $this->branch,
        '^'.$this->onto);
    } else if ($repository_api instanceof ArcanistMercurialAPI) {
      $common_ancestor = $repository_api->getCanonicalRevisionName(
        hgsprintf('ancestor(%s,%s)',
          $this->onto,
          $this->branch));

      $branch_range = hgsprintf(
        'reverse((%s::%s) - %s)',
        $common_ancestor,
        $this->branch,
        $common_ancestor);

      list($out) = $repository_api->execxLocal(
        'log -r %s --template %s',
        $branch_range,
        '{node|short} {desc|firstline}\n');
    }

    if (!trim($out)) {
      $this->restoreBranch();
      throw new ArcanistUsageException(
        pht('No commits to land from %s.', $this->branch));
    }

    echo pht("The following commit(s) will be landed:\n\n%s", $out), "\n";
  }


  // copy of the first part of the findRevision()
  // reason it has been copied as a separate function is that this way it
  // is easier to maintain with the upstream changes
  public function uberGetRevision() {
    $this->findRevision();
    return $this->revision;
  }

  private function findRevision() {
    $repository_api = $this->getRepositoryAPI();

    $this->parseBaseCommitArgument(array($this->ontoRemoteBranch));

    $revision_id = $this->getArgument('revision');
    if ($revision_id) {
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
    } else {
      $revisions = $repository_api->loadWorkingCopyDifferentialRevisions(
        $this->getConduit(),
        array());
    }

    if (!count($revisions)) {
      throw new ArcanistUsageException(pht(
        "arc can not identify which revision exists on %s '%s'. Update the ".
        "revision with recent changes to synchronize the %s name and hashes, ".
        "or use '%s' to amend the commit message at HEAD, or use ".
        "'%s' to select a revision explicitly.",
        $this->branchType,
        $this->branch,
        $this->branchType,
        'arc amend',
        '--revision <id>'));
    } else if (count($revisions) > 1) {
      switch ($this->branchType) {
        case self::REFTYPE_BOOKMARK:
          $message = pht(
            "There are multiple revisions on feature bookmark '%s' which are ".
            "not present on '%s':\n\n".
            "%s\n".
            'Separate these revisions onto different bookmarks, or use '.
            '--revision <id> to use the commit message from <id> '.
            'and land them all.',
            $this->branch,
            $this->onto,
            $this->renderRevisionList($revisions));
          break;
        case self::REFTYPE_BRANCH:
        default:
          $message = pht(
            "There are multiple revisions on feature branch '%s' which are ".
            "not present on '%s':\n\n".
            "%s\n".
            'Separate these revisions onto different branches, or use '.
            '--revision <id> to use the commit message from <id> '.
            'and land them all.',
            $this->branch,
            $this->onto,
            $this->renderRevisionList($revisions));
          break;
      }

      throw new ArcanistUsageException($message);
    }

    $this->revision = head($revisions);

    $rev_status = $this->revision['status'];
    $rev_id = $this->revision['id'];
    $rev_title = $this->revision['title'];
    $rev_auxiliary = idx($this->revision, 'auxiliary', array());

    if ($this->revision['authorPHID'] != $this->getUserPHID()) {
      $other_author = $this->getConduit()->callMethodSynchronous(
        'user.query',
        array(
          'phids' => array($this->revision['authorPHID']),
        ));
      $other_author = ipull($other_author, 'userName', 'phid');
      $other_author = $other_author[$this->revision['authorPHID']];
      $ok = phutil_console_confirm(pht(
        "This %s has revision '%s' but you are not the author. Land this ".
        "revision by %s?",
        $this->branchType,
        "D{$rev_id}: {$rev_title}",
        $other_author));
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    $uber_prevent_unaccepted_changes = $this->getConfigFromAnySource(
      'uber.land.prevent-unaccepted-changes',
      false);
    if ($uber_prevent_unaccepted_changes && $rev_status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      throw new ArcanistUsageException(
        pht("Revision '%s' has not been accepted.", "D{$rev_id}: {$rev_title}"));
    }

    if ($rev_status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      $ok = phutil_console_confirm(pht(
        "Revision '%s' has not been accepted. Continue anyway?",
        "D{$rev_id}: {$rev_title}"));
      if (!$ok) {
        throw new ArcanistUserAbortException();
      }
    }

    $uber_review_check_enabled = $this->getConfigFromAnySource(
      'uber.land.review-check',
      false);
    if ($uber_review_check_enabled) {
      if (!$repository_api instanceof ArcanistGitAPI) {
        throw new ArcanistUsageException(pht(
          "'%s' is only supported for GIT repositories.",
          'uber.land.review-check'));
      }

      $local_diff = $this->normalizeDiff(
        $repository_api->getFullGitDiff(
          $repository_api->getBaseCommit(),
          $repository_api->getHeadCommit()));

      $reviewed_diff = $this->normalizeDiff(
        $this->getConduit()->callMethodSynchronous(
          'differential.getrawdiff',
          array('diffID' => head($this->revision['diffs']))));

      if ($local_diff !== $reviewed_diff) {
        $ok = phutil_console_confirm(pht(
          "Your working copy changes do not match diff submitted for review. ".
          "Continue anyway?"));
        if (!$ok) {
          throw new ArcanistUserAbortException();
        }
      }
    }

    if ($rev_auxiliary) {
      $phids = idx($rev_auxiliary, 'phabricator:depends-on', array());
      if ($phids) {
        $dep_on_revs = $this->getConduit()->callMethodSynchronous(
          'differential.query',
           array(
             'phids' => $phids,
             'status' => 'status-open',
           ));

        $open_dep_revs = array();
        foreach ($dep_on_revs as $dep_on_rev) {
          $dep_on_rev_id = $dep_on_rev['id'];
          $dep_on_rev_title = $dep_on_rev['title'];
          $dep_on_rev_status = $dep_on_rev['status'];
          $open_dep_revs[$dep_on_rev_id] = $dep_on_rev_title;
        }

        if (!empty($open_dep_revs)) {
          $open_revs = array();
          foreach ($open_dep_revs as $id => $title) {
            $open_revs[] = '    - D'.$id.': '.$title;
          }
          $open_revs = implode("\n", $open_revs);

          echo pht(
            "Revision '%s' depends on open revisions:\n\n%s",
            "D{$rev_id}: {$rev_title}",
            $open_revs);

          $ok = phutil_console_confirm(pht('Continue anyway?'));
          if (!$ok) {
            throw new ArcanistUserAbortException();
          }
        }
      }
    }

    $message = $this->getConduit()->callMethodSynchronous(
      'differential.getcommitmessage',
      array(
        'revision_id' => $rev_id,
      ));

    $this->messageFile = new TempFile();
    Filesystem::writeFile($this->messageFile, $message);

    echo pht(
      "Landing revision '%s'...",
      "D{$rev_id}: {$rev_title}")."\n";

    $diff_phid = idx($this->revision, 'activeDiffPHID');
    if ($diff_phid) {
      $this->checkForBuildables($diff_phid);
    }
  }

  private function normalizeDiff($text) {
    $changes = id(new ArcanistDiffParser())->parseDiff($text);
    ksort($changes);
    return ArcanistBundle::newFromChanges($changes)->toGitPatch();
  }

  private function pullFromRemote() {
    $repository_api = $this->getRepositoryAPI();

    $local_ahead_of_remote = false;
    if ($this->isGit) {
      $repository_api->execxLocal('checkout %s', $this->onto);

      echo phutil_console_format(pht(
        "Switched to branch **%s**. Updating branch...\n",
        $this->onto));

      try {
        $repository_api->execxLocal('pull --ff-only --no-stat');
      } catch (CommandException $ex) {
        if (!$this->isGitSvn) {
          throw $ex;
        }
      }
      list($out) = $repository_api->execxLocal(
        'log %s..%s',
        $this->ontoRemoteBranch,
        $this->onto);
      if (strlen(trim($out))) {
        $local_ahead_of_remote = true;
      } else if ($this->isGitSvn) {
        $repository_api->execxLocal('svn rebase');
      }

    } else if ($this->isHg) {
      echo phutil_console_format(pht('Updating **%s**...', $this->onto)."\n");

      try {
        list($out, $err) = $repository_api->execxLocal('pull');

        $divergedbookmark = $this->onto.'@'.$repository_api->getBranchName();
        if (strpos($err, $divergedbookmark) !== false) {
          throw new ArcanistUsageException(phutil_console_format(pht(
            "Local bookmark **%s** has diverged from the server's **%s** ".
            "(now labeled **%s**). Please resolve this divergence and run ".
            "'%s' again.",
            $this->onto,
            $this->onto,
            $divergedbookmark,
            'arc land')));
        }
      } catch (CommandException $ex) {
        $err = $ex->getError();
        $stdout = $ex->getStdout();

        // Copied from: PhabricatorRepositoryPullLocalDaemon.php
        // NOTE: Between versions 2.1 and 2.1.1, Mercurial changed the
        // behavior of "hg pull" to return 1 in case of a successful pull
        // with no changes. This behavior has been reverted, but users who
        // updated between Feb 1, 2012 and Mar 1, 2012 will have the
        // erroring version. Do a dumb test against stdout to check for this
        // possibility.
        // See: https://github.com/phacility/phabricator/issues/101/

        // NOTE: Mercurial has translated versions, which translate this error
        // string. In a translated version, the string will be something else,
        // like "aucun changement trouve". There didn't seem to be an easy way
        // to handle this (there are hard ways but this is not a common
        // problem and only creates log spam, not application failures).
        // Assume English.

        // TODO: Remove this once we're far enough in the future that
        // deployment of 2.1 is exceedingly rare?
        if ($err != 1 || !preg_match('/no changes found/', $stdout)) {
          throw $ex;
        }
      }

      // Pull succeeded. Now make sure master is not on an outgoing change
      if ($repository_api->supportsPhases()) {
        list($out) = $repository_api->execxLocal(
          'log -r %s --template %s', $this->onto, '{phase}');
        if ($out != 'public') {
          $local_ahead_of_remote = true;
        }
      } else {
        // execManual instead of execx because outgoing returns
        // code 1 when there is nothing outgoing
        list($err, $out) = $repository_api->execManualLocal(
          'outgoing -r %s',
          $this->onto);

        // $err === 0 means something is outgoing
        if ($err === 0) {
          $local_ahead_of_remote = true;
        }
      }
    }

    if ($local_ahead_of_remote) {
      throw new ArcanistUsageException(pht(
        "Local %s '%s' is ahead of remote %s '%s', so landing a feature ".
        "%s would push additional changes. Push or reset the changes in '%s' ".
        "before running '%s'.",
        $this->ontoType,
        $this->onto,
        $this->ontoType,
        $this->ontoRemoteBranch,
        $this->ontoType,
        $this->onto,
        'arc land'));
    }
  }

  private function rebase() {
    $repository_api = $this->getRepositoryAPI();

    chdir($repository_api->getPath());
    if ($this->isGit) {
      if ($this->shouldUpdateWithRebase) {
        echo phutil_console_format(pht(
          'Rebasing **%s** onto **%s**',
          $this->branch,
          $this->onto)."\n");
        $err = phutil_passthru('git rebase %s', $this->onto);
        if ($err) {
          throw new ArcanistUsageException(pht(
            "'%s' failed. You can abort with '%s', or resolve conflicts ".
            "and use '%s' to continue forward. After resolving the rebase, ".
            "run '%s' again.",
            sprintf('git rebase %s', $this->onto),
            'git rebase --abort',
            'git rebase --continue',
            'arc land'));
        }
      } else {
        echo phutil_console_format(pht(
          'Merging **%s** into **%s**',
          $this->branch,
          $this->onto)."\n");
        $err = phutil_passthru(
          'git merge --no-stat %s -m %s',
          $this->onto,
          pht("Automatic merge by '%s'", 'arc land'));
        if ($err) {
          throw new ArcanistUsageException(pht(
            "'%s' failed. To continue: resolve the conflicts, commit ".
            "the changes, then run '%s' again. To abort: run '%s'.",
            sprintf('git merge %s', $this->onto),
            'arc land',
            'git merge --abort'));
        }
      }
    } else if ($this->isHg) {
      $onto_tip = $repository_api->getCanonicalRevisionName($this->onto);
      $common_ancestor = $repository_api->getCanonicalRevisionName(
        hgsprintf('ancestor(%s, %s)', $this->onto, $this->branch));

      // Only rebase if the local branch is not at the tip of the onto branch.
      if ($onto_tip != $common_ancestor) {
        // keep branch here so later we can decide whether to remove it
        $err = $repository_api->execPassthru(
          'rebase -d %s --keepbranches',
          $this->onto);
        if ($err) {
          echo phutil_console_format("%s\n", pht('Aborting rebase'));
          $repository_api->execManualLocal('rebase --abort');
          $this->restoreBranch();
          throw new ArcanistUsageException(pht(
            "'%s' failed and the rebase was aborted. This is most ".
            "likely due to conflicts. Manually rebase %s onto %s, resolve ".
            "the conflicts, then run '%s' again.",
            sprintf('hg rebase %s', $this->onto),
            $this->branch,
            $this->onto,
            'arc land'));
        }
      }
    }

    $repository_api->reloadWorkingCopy();
  }

  private function squash() {
    $repository_api = $this->getRepositoryAPI();

    if ($this->isGit) {
      $repository_api->execxLocal('checkout %s', $this->onto);
      $repository_api->execxLocal(
        'merge --no-stat --squash --ff-only %s',
        $this->branch);
    } else if ($this->isHg) {
      // The hg code is a little more complex than git's because we
      // need to handle the case where the landing branch has child branches:
      // -a--------b  master
      //   \
      //    w--x  mybranch
      //        \--y  subbranch1
      //         \--z  subbranch2
      //
      // arc land --branch mybranch --onto master :
      // -a--b--wx  master
      //          \--y  subbranch1
      //           \--z  subbranch2

      $branch_rev_id = $repository_api->getCanonicalRevisionName($this->branch);

      // At this point $this->onto has been pulled from remote and
      // $this->branch has been rebased on top of onto(by the rebase()
      // function). So we're guaranteed to have onto as an ancestor of branch
      // when we use first((onto::branch)-onto) below.
      $branch_root = $repository_api->getCanonicalRevisionName(
        hgsprintf('first((%s::%s)-%s)',
          $this->onto,
          $this->branch,
          $this->onto));

      $branch_range = hgsprintf(
        '(%s::%s)',
        $branch_root,
        $this->branch);

      if (!$this->keepBranch) {
        $this->handleAlternateBranches($branch_root, $branch_range);
      }

      // Collapse just the landing branch onto master.
      // Leave its children on the original branch.
      $err = $repository_api->execPassthru(
        'rebase --collapse --keep --logfile %s -r %s -d %s',
        $this->messageFile,
        $branch_range,
        $this->onto);

      if ($err) {
        $repository_api->execManualLocal('rebase --abort');
        $this->restoreBranch();
        throw new ArcanistUsageException(
          pht(
            "Squashing the commits under %s failed. ".
            "Manually squash your commits and run '%s' again.",
            $this->branch,
            'arc land'));
      }

      if ($repository_api->isBookmark($this->branch)) {
        // a bug in mercurial means bookmarks end up on the revision prior
        // to the collapse when using --collapse with --keep,
        // so we manually move them to the correct spots
        // see: http://bz.selenic.com/show_bug.cgi?id=3716
        $repository_api->execxLocal(
          'bookmark -f %s',
          $this->onto);

        $repository_api->execxLocal(
          'bookmark -f %s -r %s',
          $this->branch,
          $branch_rev_id);
      }

      // check if the branch had children
      list($output) = $repository_api->execxLocal(
        'log -r %s --template %s',
        hgsprintf('children(%s)', $this->branch),
        '{node}\n');

      $child_branch_roots = phutil_split_lines($output, false);
      $child_branch_roots = array_filter($child_branch_roots);
      if ($child_branch_roots) {
        // move the branch's children onto the collapsed commit
        foreach ($child_branch_roots as $child_root) {
          $repository_api->execxLocal(
            'rebase -d %s -s %s --keep --keepbranches',
            $this->onto,
            $child_root);
        }
      }

      // All the rebases may have moved us to another branch
      // so we move back.
      $repository_api->execxLocal('checkout %s', $this->onto);
    }
  }

  /**
   * Detect alternate branches and prompt the user for how to handle
   * them. An alternate branch is a branch that forks from the landing
   * branch prior to the landing branch tip.
   *
   * In a situation like this:
   *   -a--------b  master
   *     \
   *      w--x  landingbranch
   *       \  \-- g subbranch
   *        \--y  altbranch1
   *         \--z  altbranch2
   *
   * y and z are alternate branches and will get deleted by the squash,
   * so we need to detect them and ask the user what they want to do.
   *
   * @param string The revision id of the landing branch's root commit.
   * @param string The revset specifying all the commits in the landing branch.
   * @return void
   */
  private function handleAlternateBranches($branch_root, $branch_range) {
    $repository_api = $this->getRepositoryAPI();

    // Using the tree in the doccomment, the revset below resolves as follows:
    // 1. roots(descendants(w) - descendants(x) - (w::x))
    // 2. roots({x,g,y,z} - {g} - {w,x})
    // 3. roots({y,z})
    // 4. {y,z}
    $alt_branch_revset = hgsprintf(
      'roots(descendants(%s)-descendants(%s)-%R)',
      $branch_root,
      $this->branch,
      $branch_range);
    list($alt_branches) = $repository_api->execxLocal(
      'log --template %s -r %s',
      '{node}\n',
       $alt_branch_revset);

    $alt_branches = phutil_split_lines($alt_branches, false);
    $alt_branches = array_filter($alt_branches);

    $alt_count = count($alt_branches);
    if ($alt_count > 0) {
      $input = phutil_console_prompt(pht(
        "%s '%s' has %s %s(s) forking off of it that would be deleted ".
        "during a squash. Would you like to keep a non-squashed copy, rebase ".
        "them on top of '%s', or abort and deal with them yourself? ".
        "(k)eep, (r)ebase, (a)bort:",
        ucfirst($this->branchType),
        $this->branch,
        $alt_count,
        $this->branchType,
        $this->branch));

      if ($input == 'k' || $input == 'keep') {
        $this->keepBranch = true;
      } else if ($input == 'r' || $input == 'rebase') {
        foreach ($alt_branches as $alt_branch) {
          $repository_api->execxLocal(
            'rebase --keep --keepbranches -d %s -s %s',
            $this->branch,
            $alt_branch);
        }
      } else if ($input == 'a' || $input == 'abort') {
        $branch_string = implode("\n", $alt_branches);
        echo
          "\n",
          pht(
            "Remove the %s starting at these revisions and run %s again:\n%s",
            $this->branchType.'s',
            $branch_string,
            'arc land'),
          "\n\n";
        throw new ArcanistUserAbortException();
      } else {
        throw new ArcanistUsageException(
          pht('Invalid choice. Aborting arc land.'));
      }
    }
  }

  private function merge() {
    $repository_api = $this->getRepositoryAPI();

    // In immutable histories, do a --no-ff merge to force a merge commit with
    // the right message.
    $repository_api->execxLocal('checkout %s', $this->onto);

    chdir($repository_api->getPath());
    if ($this->isGit) {
      $err = phutil_passthru(
        'git merge --no-stat --no-ff --no-commit %s',
        $this->branch);

      if ($err) {
        throw new ArcanistUsageException(pht(
          "'%s' failed. Your working copy has been left in a partially ".
          "merged state. You can: abort with '%s'; or follow the ".
          "instructions to complete the merge.",
          'git merge',
          'git merge --abort'));
      }
    } else if ($this->isHg) {
      // HG arc land currently doesn't support --merge.
      // When merging a bookmark branch to a master branch that
      // hasn't changed since the fork, mercurial fails to merge.
      // Instead of only working in some cases, we just disable --merge
      // until there is a demand for it.
      // The user should never reach this line, since --merge is
      // forbidden at the command line argument level.
      throw new ArcanistUsageException(
        pht('%s is not currently supported for hg repos.', '--merge'));
    }
  }

  private function push() {
    $repository_api = $this->getRepositoryAPI();

    // These commands can fail legitimately (e.g. commit hooks)
    try {
      if ($this->isGit) {
        $repository_api->execxLocal('commit -F %s', $this->messageFile);
        if (phutil_is_windows()) {
          // Occasionally on large repositories on Windows, Git can exit with
          // an unclean working copy here. This prevents reverts from being
          // pushed to the remote when this occurs.
          $this->requireCleanWorkingCopy();
        }
      } else if ($this->isHg) {
        // hg rebase produces a commit earlier as part of rebase
        if (!$this->useSquash) {
          $repository_api->execxLocal(
            'commit --logfile %s',
            $this->messageFile);
        }
      }
      // We dispatch this event so we can run checks on the merged revision,
      // right before it gets pushed out. It's easier to do this in arc land
      // than to try to hook into git/hg.
      $this->didCommitMerge();
    } catch (Exception $ex) {
      $this->executeCleanupAfterFailedPush();
      throw $ex;
    }

    if ($this->getArgument('hold')) {
      echo phutil_console_format(pht(
        'Holding change in **%s**: it has NOT been pushed yet.',
        $this->onto)."\n");
    } else {
      $sirmixalot_enrolled = $this->getConfigFromAnySource(
        'uber.sirmixalot.enrolled',
        false);
      // check, if this repo is enrolled to sirmixalot service
      if ($sirmixalot_enrolled) {
        // if repo is enrolled, land change on a specific remote branch
        $remote_landed_branch = sprintf('landed/%s', date("YmdHis"));
        $landed_branch = sprintf("HEAD:%s", $remote_landed_branch);
      } else {
        $remote_landed_branch = $landed_branch = $this->onto;
      }
      echo pht('Pushing change to %s', $remote_landed_branch), "\n\n";

      chdir($repository_api->getPath());

      if ($this->isGitSvn) {
        $err = phutil_passthru('git svn dcommit');
        $cmd = 'git svn dcommit';
      } else if ($this->isGit) {
        $err = phutil_passthru(
          'git push %s %s',
          $this->remote,
          $landed_branch);
        $cmd = 'git push';
        if ($sirmixalot_enrolled) {
          // clean up current branch (the one used for merging). if we don't,
          // current branch will have landed commits that are not on branch's
          // remote origin (future 'arc float' executions will fail)
          $repository_api->execxLocal('reset --hard HEAD^');
        }
      } else if ($this->isHgSvn) {
        // hg-svn doesn't support 'push -r', so we do a normal push
        // which hg-svn modifies to only push the current branch and
        // ancestors.
        $err = $repository_api->execPassthru('push %s', $this->remote);
        $cmd = 'hg push';
      } else if ($this->isHg) {
        if (strlen($this->remote)) {
          $err = $repository_api->execPassthru(
            'push -r %s %s',
            $this->onto,
            $this->remote);
        } else {
          $err = $repository_api->execPassthru(
            'push -r %s',
            $this->onto);
        }
        $cmd = 'hg push';
      }

      if ($err) {
        echo phutil_console_format(
          "<bg:red>**   %s   **</bg>\n",
          pht('PUSH FAILED!'));
        $this->executeCleanupAfterFailedPush();
        if ($this->isGit) {
          throw new ArcanistUsageException(pht(
            "'%s' failed! Fix the error and run '%s' again.",
            $cmd,
            'arc land'));
        }
        throw new ArcanistUsageException(pht(
          "'%s' failed! Fix the error and push this change manually.",
          $cmd));
      }

      $this->didPush();

      echo "\n";
    }
  }

  private function executeCleanupAfterFailedPush() {
    $repository_api = $this->getRepositoryAPI();
    if ($this->isGit) {
      $repository_api->execxLocal('reset --hard HEAD^');
      $this->restoreBranch();
    } else if ($this->isHg) {
      $repository_api->execxLocal(
        '--config extensions.mq= strip %s',
        $this->onto);
      $this->restoreBranch();
    }
  }

  private function cleanupBranch() {
    $repository_api = $this->getRepositoryAPI();

    echo pht('Cleaning up feature %s...', $this->branchType), "\n";
    if ($this->isGit) {
      list($ref) = $repository_api->execxLocal(
        'rev-parse --verify %s',
        $this->branch);
      $ref = trim($ref);
      $recovery_command = csprintf(
        'git checkout -b %s %s',
        $this->branch,
        $ref);
      echo pht('(Use `%s` if you want it back.)', $recovery_command), "\n";
      $repository_api->execxLocal('branch -D %s', $this->branch);
    } else if ($this->isHg) {
      $common_ancestor = $repository_api->getCanonicalRevisionName(
        hgsprintf('ancestor(%s,%s)', $this->onto, $this->branch));

      $branch_root = $repository_api->getCanonicalRevisionName(
        hgsprintf('first((%s::%s)-%s)',
          $common_ancestor,
          $this->branch,
          $common_ancestor));

      $repository_api->execxLocal(
        '--config extensions.mq= strip -r %s',
        $branch_root);

      if ($repository_api->isBookmark($this->branch)) {
        $repository_api->execxLocal('bookmark -d %s', $this->branch);
      }
    }

    if ($this->getArgument('delete-remote')) {
      if ($this->isGit) {
        list($err, $ref) = $repository_api->execManualLocal(
          'rev-parse --verify %s/%s',
          $this->remote,
          $this->branch);

        if ($err) {
          echo pht(
            'No remote feature %s to clean up.',
            $this->branchType);
          echo "\n";
        } else {

          // NOTE: In Git, you delete a remote branch by pushing it with a
          // colon in front of its name:
          //
          //   git push <remote> :<branch>

          echo pht('Cleaning up remote feature %s...', $this->branchType), "\n";
          $repository_api->execxLocal(
            'push %s :%s',
            $this->remote,
            $this->branch);
        }
      } else if ($this->isHg) {
        // named branches were closed as part of the earlier commit
        // so only worry about bookmarks
        if ($repository_api->isBookmark($this->branch)) {
          $repository_api->execxLocal(
            'push -B %s %s',
            $this->branch,
            $this->remote);
        }
      }
    }
  }

  public function getSupportedRevisionControlSystems() {
    return array('git', 'hg');
  }

  private function getBranchOrBookmark() {
    $repository_api = $this->getRepositoryAPI();
    if ($this->isGit) {
      $branch = $repository_api->getBranchName();

      // If we don't have a branch name, just use whatever's at HEAD.
      if (!strlen($branch) && !$this->isGitSvn) {
        $branch = $repository_api->getWorkingCopyRevision();
      }
    } else if ($this->isHg) {
      $branch = $repository_api->getActiveBookmark();
      if (!$branch) {
        $branch = $repository_api->getBranchName();
      }
    }

    return $branch;
  }

  private function getBranchType($branch) {
    $repository_api = $this->getRepositoryAPI();
    if ($this->isHg && $repository_api->isBookmark($branch)) {
      return 'bookmark';
    }
    return 'branch';
  }

  /**
   * Restore the original branch, e.g. after a successful land or a failed
   * pull.
   */
  private function restoreBranch() {
    $repository_api = $this->getRepositoryAPI();
    $repository_api->execxLocal('checkout %s', $this->oldBranch);
    if ($this->isGit) {
      $repository_api->execxLocal('submodule update --init --recursive');
    }
    echo pht(
      "Switched back to %s %s.\n",
      $this->branchType,
      phutil_console_format('**%s**', $this->oldBranch));
  }


  /**
   * Check if a diff has a running or failed buildable, and prompt the user
   * before landing if it does.
   */
  private function checkForBuildables($diff_phid) {
    // NOTE: Since Harbormaster is still beta and this stuff all got added
    // recently, just bail if we can't find a buildable. This is just an
    // advisory check intended to prevent human error.

    try {
      $buildables = $this->getConduit()->callMethodSynchronous(
        'harbormaster.querybuildables',
        array(
          'buildablePHIDs' => array($diff_phid),
          'manualBuildables' => false,
        ));
    } catch (ConduitClientException $ex) {
      return;
    }

    if (!$buildables['data']) {
      // If there's no corresponding buildable, we're done.
      return;
    }

    $console = PhutilConsole::getConsole();

    $buildable = head($buildables['data']);

    if ($buildable['buildableStatus'] == 'passed') {
      $console->writeOut(
        "**<bg:green> %s </bg>** %s\n",
        pht('BUILDS PASSED'),
        pht('Harbormaster builds for the active diff completed successfully.'));
      return;
    }

    switch ($buildable['buildableStatus']) {
      case 'building':
        $message = pht(
          'Harbormaster is still building the active diff for this revision:');
        $prompt = pht('Land revision anyway, despite ongoing build?');
        break;
      case 'failed':
        $message = pht(
          'Harbormaster failed to build the active diff for this revision. '.
          'Build failures:');
        $prompt = pht('Land revision anyway, despite build failures?');
        break;
      default:
        // If we don't recognize the status, just bail.
        return;
    }

    $builds = $this->getConduit()->callMethodSynchronous(
      'harbormaster.querybuilds',
      array(
        'buildablePHIDs' => array($buildable['phid']),
      ));

    $console->writeOut($message."\n\n");
    foreach ($builds['data'] as $build) {
      switch ($build['buildStatus']) {
        case 'failed':
          $color = 'red';
          break;
        default:
          $color = 'yellow';
          break;
      }

      $console->writeOut(
        "    **<bg:".$color."> %s </bg>** %s: %s\n",
        phutil_utf8_strtoupper($build['buildStatusName']),
        pht('Build %d', $build['id']),
        $build['name']);
    }

    $console->writeOut(
      "\n%s\n\n    **%s**: __%s__",
      pht('You can review build details here:'),
      pht('Harbormaster URI'),
      $buildable['uri']);

    if ($this->getConfigFromAnySource("uber.land.buildables-check") && !$this->tbr) {
      $console->writeOut("\n");
      throw new ArcanistUsageException(
        pht("All harbormaster buildables have not succeeded."));
    }

    if (!$console->confirm($prompt)) {
      throw new ArcanistUserAbortException();
    }
  }

  public function buildEngineMessage(ArcanistLandEngine $engine) {
    // TODO: This is oh-so-gross.
    $this->findRevision();
    $engine->setCommitMessageFile($this->messageFile);
  }

  public function didCommitMerge() {
    $this->dispatchEvent(
      ArcanistEventType::TYPE_LAND_WILLPUSHREVISION,
      array());
  }

  public function didPush() {
    if ($this->shouldUseSubmitQueue) {
      return;
    }
    $this->askForRepositoryUpdate();

    $mark_workflow = $this->buildChildWorkflow(
      'close-revision',
      array(
        '--finalize',
        '--quiet',
        $this->revision['id'],
      ));
    $mark_workflow->run();
    // UBER CODE
    $this->dispatchEvent(
      ArcanistEventType::TYPE_LAND_DIDPUSHREVISION,
      array());
    // END UBER CODE
  }

}
