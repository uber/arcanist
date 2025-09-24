<?php

/**
 * Mock UberRefProvider for ArcanistPatchWorkflowTestCase.
 */
final class PatchWorkflowMockUberRefProvider extends stdClass {
  public function getBaseRefName($prefix, $id, $current_value = null) {
    return "refs/{$prefix}/base/{$id}";
  }

  public function getDiffRefName($prefix, $id, $current_value = null) {
    return "refs/{$prefix}/diff/{$id}";
  }
}
