<?php

/**
 * Mock repository API for UberArcanistSubmitQueueEngineTestCase.
 */
final class SubmitQueueMockRepositoryAPI extends ArcanistRepositoryAPI {
  private $remote_url;

  public function __construct($remote_url) {
    $this->remote_url = $remote_url;
  }

  public function uberGetGitRemotePushUrl($remote) {
    return $this->remote_url;
  }

  // Required abstract methods from ArcanistRepositoryAPI
  public function getSourceControlSystemName() {
    return 'git';
  }

  public function getMetadataPath() {
    return '.git';
  }

  public function getSourceControlBaseRevision() {
    return 'HEAD~1';
  }

  public function getCanonicalRevisionName($string) {
    return $string;
  }

  public function getBranchName() {
    return 'mock-branch';
  }

  public function getSourceControlPath() {
    return '/mock/path';
  }

  public function isHistoryDefaultImmutable() {
    return false;
  }

  public function supportsAmend() {
    return true;
  }

  public function getWorkingCopyRevision() {
    return 'mock-revision';
  }

  public function updateWorkingCopy() {
    return;
  }

  public function getLocalCommitInformation() {
    return array();
  }

  public function getAllLocalChanges() {
    return array();
  }

  public function supportsLocalCommits() {
    return true;
  }

  public function hasLocalCommits() {
    return false;
  }

  public function getCommitMessage($commit) {
    return 'Mock commit message';
  }

  public function loadWorkingCopyDifferentialRevisions(ConduitClient $conduit, array $query) {
    return array();
  }

  public function updateLocalHashes(array $hashes) {
    return;
  }

  protected function buildLocalFuture(array $argv) {
    return new ExecFuture('echo mock');
  }

  public function getFinalizedRevisionMessage() {
    return 'Mock finalized message';
  }

  public function execxLocal($pattern /* , ... */) {
    return array('mock output');
  }

  public function getCommitSummary($commit) {
    return 'Mock summary';
  }

  // Additional required abstract methods
  protected function buildUncommittedStatus() {
    return array();
  }

  protected function buildCommitRangeStatus() {
    return array();
  }

  public function getAllFiles() {
    return array();
  }

  public function getBlame($path) {
    return array();
  }

  public function getRawDiffText($path) {
    return '';
  }

  public function getOriginalFileData($path) {
    return '';
  }

  public function getCurrentFileData($path) {
    return '';
  }

  public function getRemoteURI() {
    return $this->remote_url;
  }

  public function supportsLocalBranchMerge() {
    return true;
  }

  public function supportsCommitRanges() {
    return true;
  }
}
