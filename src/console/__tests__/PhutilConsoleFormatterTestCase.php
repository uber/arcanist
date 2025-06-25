<?php

final class PhutilConsoleFormatterTestCase extends PhutilTestCase {

  public function testFormatHyperlink() {
    $url = 'https://example.com/D12345';
    $text = 'D12345';

    // Test with ANSI enabled (default)
    PhutilConsoleFormatter::disableANSI(false);
    $result = PhutilConsoleFormatter::formatHyperlink($url, $text);
    
    // Expected OSC 8 hyperlink format: \033]8;;URL\033\TEXT\033]8;;\033\
    $esc = chr(27);
    $expected = $esc.']8;;'.$url.$esc.'\\'.$text.$esc.']8;;'.$esc.'\\';
    
    $this->assertEqual(
      $expected,
      $result,
      pht('formatHyperlink should produce OSC 8 escape sequences when ANSI is enabled'));

    // Test with ANSI disabled
    PhutilConsoleFormatter::disableANSI(true);
    $result = PhutilConsoleFormatter::formatHyperlink($url, $text);
    
    $this->assertEqual(
      $text,
      $result,
      pht('formatHyperlink should return plain text when ANSI is disabled'));

    // Reset ANSI state
    PhutilConsoleFormatter::disableANSI(false);
  }

  public function testFormatHyperlinkEdgeCases() {
    // Test empty URL
    $result = PhutilConsoleFormatter::formatHyperlink('', 'text');
    $esc = chr(27);
    $expected = $esc.']8;;'.$esc.'\\'.'text'.$esc.']8;;'.$esc.'\\';
    $this->assertEqual($expected, $result);

    // Test empty text
    $result = PhutilConsoleFormatter::formatHyperlink('https://example.com', '');
    $esc = chr(27);
    $expected = $esc.']8;;https://example.com'.$esc.'\\'.$esc.']8;;'.$esc.'\\';
    $this->assertEqual($expected, $result);

    // Test special characters in URL
    $url = 'https://example.com/path?query=value&other=123#fragment';
    $text = 'Link Text';
    $result = PhutilConsoleFormatter::formatHyperlink($url, $text);
    $esc = chr(27);
    $expected = $esc.']8;;'.$url.$esc.'\\'.$text.$esc.']8;;'.$esc.'\\';
    $this->assertEqual($expected, $result);
  }

  public function testDisableANSI() {
    // Test initial state
    $this->assertFalse(
      PhutilConsoleFormatter::getDisableANSI(),
      pht('ANSI should be enabled by default'));

    // Test disabling
    PhutilConsoleFormatter::disableANSI(true);
    $this->assertTrue(
      PhutilConsoleFormatter::getDisableANSI(),
      pht('ANSI should be disabled after calling disableANSI(true)'));

    // Test re-enabling
    PhutilConsoleFormatter::disableANSI(false);
    $this->assertFalse(
      PhutilConsoleFormatter::getDisableANSI(),
      pht('ANSI should be enabled after calling disableANSI(false)'));
  }

  public function testFormatString() {
    PhutilConsoleFormatter::disableANSI(false);

    // Test bold formatting
    $result = PhutilConsoleFormatter::formatString('bold', 'test');
    $this->assertEqual("\033[1mtest\033[0m", $result);

    // Test color formatting
    $result = PhutilConsoleFormatter::formatString('red', 'test');
    $this->assertEqual("\033[31mtest\033[0m", $result);

    // Test combined formatting
    $result = PhutilConsoleFormatter::formatString('bold red', 'test');
    $this->assertEqual("\033[1;31mtest\033[0m", $result);

    // Test with ANSI disabled
    PhutilConsoleFormatter::disableANSI(true);
    $result = PhutilConsoleFormatter::formatString('bold red', 'test');
    $this->assertEqual('test', $result);

    // Reset ANSI state
    PhutilConsoleFormatter::disableANSI(false);
  }

  public function testStripANSI() {
    $input = "\033[1;31mHello\033[0m \033[32mWorld\033[0m";
    $expected = "Hello World";
    $result = PhutilConsoleFormatter::stripANSI($input);
    
    $this->assertEqual(
      $expected,
      $result,
      pht('stripANSI should remove all ANSI escape sequences'));

    // Test with hyperlink sequences
    $esc = chr(27);
    $hyperlink = $esc.']8;;https://example.com'.$esc.'\\text'.$esc.']8;;'.$esc.'\\';
    $result = PhutilConsoleFormatter::stripANSI($hyperlink);
    
    // stripANSI only removes color sequences, not OSC 8
    $this->assertEqual($hyperlink, $result);
  }

  public function testEscapeFormat() {
    $input = '**bold** __italic__ ##code##';
    $expected = '\\*\\*bold\\*\\* \\_\\_italic\\_\\_ \\#\\#code\\#\\#';
    $result = PhutilConsoleFormatter::escapeFormat($input);
    
    $this->assertEqual(
      $expected,
      $result,
      pht('escapeFormat should escape format characters'));
  }

  public function testUnescapeFormat() {
    $input = '\\\\(**bold**) \\\\(__italic__) \\\\(##code##)';
    $expected = '(**bold**) (__italic__) (##code##)';
    $result = PhutilConsoleFormatter::unescapeFormat($input);
    
    $this->assertEqual(
      $expected,
      $result,
      pht('unescapeFormat should unescape format sequences'));
  }

}
