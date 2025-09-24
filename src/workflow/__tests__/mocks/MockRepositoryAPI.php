<?php

/**
 * Mock repository API for ArcanistPatchWorkflowTestCase.
 */
final class PatchWorkflowMockRepositoryAPI extends stdClass {
  private $options;

  public function __construct($options) {
    $this->options = $options;
  }

  public function execPassthru($command, $args) {
    if ($command === 'fetch --no-tags %s +%s:%s') {
      return $this->options['fetch_success'] ? 0 : 1;
    }
    throw new Exception("Unexpected execPassthru command: '$command'");
  }

  public function execManualLocal($command, $args) {
    if ($command === 'merge-base %s HEAD') {
      return array($this->options['has_common_ancestor'] ? 0 : 1, $this->options['merge_base_output']);
    } elseif ($command === 'merge-base --is-ancestor %s HEAD') {
      return array($this->options['merge_base_ancestor_output']);
    } elseif ($command === 'rev-parse %s') {
      return array($this->options['base_rev_parse_success'] ? 0 : 1, $this->options['base_rev_parse_output']);
    }
    throw new Exception("Unexpected execManualLocal command: '$command'");
  }
}
