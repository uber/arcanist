<?php

final class ICFlowMonogramField extends ICFlowField {

  public function getFieldKey() {
    return 'monogram';
  }

  public function getSummary() {
    return pht(
      'The short object name (DNNN) for the revision corresponding to HEAD of '.
      'the branch, if any.');
  }

  public function getDefaultFieldOrder() {
    return 3;
  }

  protected function renderValues(array $values) {
    $revision_id = idx($values, 'revision-id');
    $monogram = 'D'.$revision_id;

    // Create clickable link if we have a revision ID
    if ($revision_id) {
      $url = $this->buildDifferentialURL($revision_id);
      if ($url) {
        return PhutilConsoleFormatter::formatHyperlink($url, $monogram);
      }
    }

    return $monogram;
  }

  public function getValues(ICFlowFeature $feature) {
    $revision_id = $feature->getRevisionID();
    if ($revision_id) {
      return array('revision-id' => $revision_id);
    }
    return null;
  }

  /**
   * Build the URL for a differential revision.
   *
   * @param int $revision_id The revision ID (e.g., 123 for D123)
   * @return string|null The full URL or null if no base URL configured
   */
  private function buildDifferentialURL($revision_id) {
    // Try to get the base URL from configuration
    $base_url = $this->getDifferentialBaseURL();
    if (!$base_url) {
      return null;
    }

    // Ensure base URL ends with a slash
    $base_url = rtrim($base_url, '/');

    return $base_url.'/D'.$revision_id;
  }

  /**
   * Get the base URL for differential from configuration.
   *
   * @return string|null The base URL or null if not configured
   */
  private function getDifferentialBaseURL() {
    // Try multiple configuration sources in order of preference

    // 1. Check for Uber-specific configuration
    $uber_url = getenv('UBER_DIFFERENTIAL_BASE_URL');
    if ($uber_url) {
      return $uber_url;
    }

    // 2. Default to Uber internal URL
    return 'https://code.uberinternal.com';
  }

}
