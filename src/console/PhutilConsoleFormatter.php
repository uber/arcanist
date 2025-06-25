<?php

/**
 * Utilities for console formatting.
 */
final class PhutilConsoleFormatter {

  private static $disableANSI = false;
  private static $colorCodes = array(
    'red'     => 31,
    'green'   => 32,
    'yellow'  => 33,
    'blue'    => 34,
    'magenta' => 35,
    'cyan'    => 36,
    'white'   => 37,
    'default' => 39,
  );

  /**
   * Disable ANSI color and formatting codes.
   */
  public static function disableANSI($disable) {
    self::$disableANSI = $disable;
  }

  /**
   * Get the current ANSI disabled state.
   */
  public static function getDisableANSI() {
    return self::$disableANSI;
  }

  /**
   * Format a string with ANSI color codes.
   */
  public static function formatString($format, $string = null) {
    if (self::$disableANSI) {
      return $string;
    }

    $colors = self::$colorCodes;
    $codes = array();

    if (strpos($format, 'bold') !== false) {
      $codes[] = 1;
    }

    foreach ($colors as $color => $code) {
      if (strpos($format, $color) !== false) {
        $codes[] = $code;
      }
    }

    if (empty($codes)) {
      return $string;
    }

    $prefix = sprintf('\033[%sm', implode(';', $codes));
    $suffix = '\033[0m';

    return $prefix.$string.$suffix;
  }

  /**
   * Strip ANSI escape sequences from a string.
   */
  public static function stripANSI($string) {
    return preg_replace('/\x1b\[[0-9;]*m/', '', $string);
  }

  /**
   * Escape format codes in a string.
   */
  public static function escapeFormat($format) {
    return addcslashes($format, '*_#');
  }

  /**
   * Unescape format codes in a string.
   */
  public static function unescapeFormat($format) {
    return preg_replace('/\\\\(\*\*.*\*\*|__.*__|##.*##)/sU', '\1', $format);
  }

  /**
   * Create a terminal hyperlink using OSC 8 escape sequences.
   *
   * @param string $url The URL to link to
   * @param string $text The visible text to display
   * @return string Terminal hyperlink or just text if ANSI is disabled
   */
  public static function formatHyperlink($url, $text) {
    if (self::getDisableANSI()) {
      // If ANSI is disabled, just return the text
      return $text;
    }

    // OSC 8 hyperlink format: \033]8;;URL\033\TEXT\033]8;;\033\
    $esc = chr(27);
    return $esc.']8;;'.$url.$esc.'\\'.$text.$esc.']8;;'.$esc.'\\';
  }

}
