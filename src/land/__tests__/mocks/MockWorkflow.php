<?php

/**
 * Mock workflow for UberArcanistSubmitQueueEngineTestCase.
 */
final class SubmitQueueMockWorkflow extends ArcanistWorkflow {
  public function getWorkflowName() {
    return 'mock-workflow';
  }

  public function getCommandSynopses() {
    return array();
  }

  public function getCommandHelp() {
    return 'Mock workflow for testing';
  }

  public function run() {
    return 0;
  }
}
