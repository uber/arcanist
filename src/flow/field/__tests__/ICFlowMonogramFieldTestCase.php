<?php

final class ICFlowMonogramFieldTestCase extends PhutilTestCase {

  private function createMockFeature($revision_id) {
    $feature = $this->getMockBuilder('ICFlowFeature')
      ->setMethods(array('getRevisionID'))
      ->getMock();
    
    $feature->method('getRevisionID')
      ->willReturn($revision_id);
    
    return $feature;
  }

  public function testGetFieldKey() {
    $field = new ICFlowMonogramField();
    $this->assertEqual('monogram', $field->getFieldKey());
  }

  public function testGetValues() {
    $field = new ICFlowMonogramField();

    // Test with valid revision ID
    $feature = $this->createMockFeature(12345);
    $values = $field->getValues($feature);
    $expected = array('revision-id' => 12345);
    $this->assertEqual($expected, $values);

    // Test with null revision ID
    $feature = $this->createMockFeature(null);
    $values = $field->getValues($feature);
    $this->assertEqual(null, $values);

    // Test with zero revision ID
    $feature = $this->createMockFeature(0);
    $values = $field->getValues($feature);
    $this->assertEqual(null, $values);
  }

  public function testRenderValuesWithoutHyperlinks() {
    $field = new ICFlowMonogramField();

    // Test basic monogram rendering
    $values = array('revision-id' => 12345);
    
    // Disable ANSI to test plain text fallback
    PhutilConsoleFormatter::disableANSI(true);
    $result = $field->renderValues($values);
    $this->assertEqual('D12345', $result);

    // Reset ANSI state
    PhutilConsoleFormatter::disableANSI(false);
  }

  public function testRenderValuesWithHyperlinks() {
    $field = new ICFlowMonogramField();

    // Test hyperlink rendering with ANSI enabled
    $values = array('revision-id' => 12345);
    
    PhutilConsoleFormatter::disableANSI(false);
    $result = $field->renderValues($values);
    
    // Should contain OSC 8 escape sequences
    $this->assertTrue(
      strpos($result, chr(27).']8;;') !== false,
      pht('Result should contain OSC 8 hyperlink escape sequences'));
    
    // Should contain the expected URL
    $this->assertTrue(
      strpos($result, 'https://code.uberinternal.com/D12345') !== false,
      pht('Result should contain the expected URL'));
    
    // Should contain the monogram text
    $this->assertTrue(
      strpos($result, 'D12345') !== false,
      pht('Result should contain the monogram text'));
  }

  public function testRenderValuesEdgeCases() {
    $field = new ICFlowMonogramField();

    // Test with empty values
    $result = $field->renderValues(array());
    $this->assertEqual('D', $result);

    // Test with null revision-id
    $values = array('revision-id' => null);
    $result = $field->renderValues($values);
    $this->assertEqual('D', $result);

    // Test with different revision IDs
    $test_cases = array(1, 999, 123456789);
    
    foreach ($test_cases as $revision_id) {
      $values = array('revision-id' => $revision_id);
      $result = $field->renderValues($values);
      
      // With ANSI disabled, should return plain monogram
      PhutilConsoleFormatter::disableANSI(true);
      $plain_result = $field->renderValues($values);
      $this->assertEqual("D{$revision_id}", $plain_result);
      
      // With ANSI enabled, should contain hyperlink
      PhutilConsoleFormatter::disableANSI(false);
      $hyperlink_result = $field->renderValues($values);
      $this->assertTrue(
        strpos($hyperlink_result, "D{$revision_id}") !== false,
        pht("Result should contain D{$revision_id}"));
    }

    // Reset ANSI state
    PhutilConsoleFormatter::disableANSI(false);
  }

  public function testBuildDifferentialURL() {
    $field = new ICFlowMonogramField();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('buildDifferentialURL');
    $method->setAccessible(true);

    // Test URL building
    $result = $method->invoke($field, 12345);
    $this->assertEqual(
      'https://code.uberinternal.com/D12345',
      $result);

    // Test with different revision ID
    $result = $method->invoke($field, 67890);
    $this->assertEqual(
      'https://code.uberinternal.com/D67890',
      $result);
  }

  public function testGetDifferentialBaseURL() {
    $field = new ICFlowMonogramField();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('getDifferentialBaseURL');
    $method->setAccessible(true);

    // Test default URL
    $result = $method->invoke($field);
    $this->assertEqual(
      'https://code.uberinternal.com',
      $result);
  }

  public function testGetDifferentialBaseURLWithEnvironmentVariable() {
    $field = new ICFlowMonogramField();
    
    // Use reflection to test private method
    $reflection = new ReflectionClass($field);
    $method = $reflection->getMethod('getDifferentialBaseURL');
    $method->setAccessible(true);

    // Test with environment variable
    $original_env = getenv('UBER_DIFFERENTIAL_BASE_URL');
    putenv('UBER_DIFFERENTIAL_BASE_URL=https://custom.uber.internal');
    
    $result = $method->invoke($field);
    $this->assertEqual(
      'https://custom.uber.internal',
      $result);

    // Restore original environment
    if ($original_env !== false) {
      putenv('UBER_DIFFERENTIAL_BASE_URL=' . $original_env);
    } else {
      putenv('UBER_DIFFERENTIAL_BASE_URL');
    }
  }

  public function testGetSummary() {
    $field = new ICFlowMonogramField();
    $summary = $field->getSummary();
    
    $this->assertTrue(
      is_string($summary) && strlen($summary) > 0,
      pht('Summary should be a non-empty string'));
    
    $this->assertTrue(
      strpos($summary, 'DNNN') !== false,
      pht('Summary should mention the DNNN format'));
  }

  public function testGetDefaultFieldOrder() {
    $field = new ICFlowMonogramField();
    $order = $field->getDefaultFieldOrder();
    
    $this->assertTrue(
      is_int($order),
      pht('Field order should be an integer'));
    
    $this->assertEqual(3, $order);
  }

}
