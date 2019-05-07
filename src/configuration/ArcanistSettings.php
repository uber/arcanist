<?php

final class ArcanistSettings extends Phobject {

  private function getOptions() {
    return array(
      'default' => array(
        'type' => 'string',
        'help' => pht(
          'The URI of a Phabricator install to connect to by default, if '.
          '%s is run in a project without a Phabricator URI or run outside '.
          'of a project.',
          'arc'),
        'example' => '"http://phabricator.example.com/"',
      ),
      'base' => array(
        'type' => 'string',
        'help' => pht(
          'Base commit ruleset to invoke when determining the start of a '.
          'commit range. See "Arcanist User Guide: Commit Ranges" for '.
          'details.'),
        'example' => '"arc:amended, arc:prompt"',
      ),
      'load' => array(
        'type' => 'list',
        'legacy' => 'phutil_libraries',
        'help' => pht(
          'A list of paths to phutil libraries that should be loaded at '.
          'startup. This can be used to make classes available, like lint '.
          'or unit test engines.'),
        'default' => array(),
        'example' => '["/var/arc/customlib/src"]',
      ),
      'repository.callsign' => array(
        'type' => 'string',
        'example' => '"X"',
        'help' => pht(
          'Associate the working copy with a specific Phabricator repository. '.
          'Normally, %s can figure this association out on its own, but if '.
          'your setup is unusual you can use this option to tell it what the '.
          'desired value is.',
          'arc'),
      ),
      'phabricator.uri' => array(
        'type' => 'string',
        'legacy' => 'conduit_uri',
        'example' => '"https://phabricator.mycompany.com/"',
        'help' => pht(
          'Associates this working copy with a specific installation of '.
          'Phabricator.'),
      ),
      'lint.engine' => array(
        'type' => 'string',
        'legacy' => 'lint_engine',
        'help' => pht(
          'The name of a default lint engine to use, if no lint engine is '.
          'specified by the current project.'),
        'example' => '"ExampleLintEngine"',
      ),
      'unit.engine' => array(
        'type' => 'string',
        'legacy' => 'unit_engine',
        'help' => pht(
          'The name of a default unit test engine to use, if no unit test '.
          'engine is specified by the current project.'),
        'example' => '"ExampleUnitTestEngine"',
      ),
      'arc.feature.start.default' => array(
        'type' => 'string',
        'help' => pht(
          'The name of the default branch to create the new feature branch '.
          'off of.'),
        'example' => '"develop"',
      ),
      'arc.land.onto.default' => array(
        'type' => 'string',
        'help' => pht(
          'The name of the default branch to land changes onto when '.
          '`%s` is run.',
          'arc land'),
        'example' => '"develop"',
      ),
      'arc.lint.cache' => array(
        'type' => 'bool',
        'help' => pht(
          'Enable the lint cache by default. When enabled, `%s` attempts to '.
          'use cached results if possible. Currently, the cache is not always '.
          'invalidated correctly and may cause `%s` to report incorrect '.
          'results, particularly while developing linters. This is probably '.
          'worth enabling only if your linters are very slow.',
          'arc lint',
          'arc lint'),
        'default' => false,
        'example' => 'false',
      ),
      'history.immutable' => array(
        'type' => 'bool',
        'legacy' => 'immutable_history',
        'help' => pht(
          'If true, %s will never change repository history (e.g., through '.
          'amending or rebasing). Defaults to true in Mercurial and false in '.
          'Git. This setting has no effect in Subversion.',
          'arc'),
        'example' => 'false',
      ),
      'editor' => array(
        'type' => 'string',
        'help' => pht(
          'Command to use to invoke an interactive editor, like `%s` or `%s`. '.
          'This setting overrides the %s environmental variable.',
          'nano',
          'vim',
          'EDITOR'),
        'example' => '"nano"',
      ),
      'https.cabundle' => array(
        'type' => 'string',
        'help' => pht(
          "Path to a custom CA bundle file to be used for arcanist's cURL ".
          "calls. This is used primarily when your conduit endpoint is ".
          "behind HTTPS signed by your organization's internal CA."),
        'example' => 'support/yourca.pem',
      ),
      'https.blindly-trust-domains' => array(
        'type' => 'list',
        'help' => pht(
          'List of domains to blindly trust SSL certificates for. '.
          'Disables peer verification.'),
        'default' => array(),
        'example' => '["secure.mycompany.com"]',
      ),
      'browser' => array(
        'type' => 'string',
        'help' => pht('Command to use to invoke a web browser.'),
        'example' => '"gnome-www-browser"',
      ),
      'events.listeners' => array(
        'type' => 'list',
        'help' => pht('List of event listener classes to install at startup.'),
        'default' => array(),
        'example' => '["ExampleEventListener"]',
      ),
      'http.basicauth.user' => array(
        'type' => 'string',
        'help' => pht('Username to use for basic auth over HTTP transports.'),
        'example' => '"bob"',
      ),
      'http.basicauth.pass' => array(
        'type' => 'string',
        'help' => pht('Password to use for basic auth over HTTP transports.'),
        'example' => '"bobhasasecret"',
      ),
      'arc.autostash' => array(
        'type' => 'bool',
        'help' => pht(
          'Whether %s should permit the automatic stashing of changes in the '.
          'working directory when requiring a clean working copy. This option '.
          'should only be used when users understand how to restore their '.
          'working directory from the local stash if an Arcanist operation '.
          'causes an unrecoverable error.',
          'arc'),
        'default' => false,
        'example' => 'false',
      ),
      'aliases' => array(
        'type' => 'aliases',
        'help' => pht(
          'Configured command aliases. Use "arc alias" to define aliases.'),
      ),
      'uber.sirmixalot.enrolled' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, the setting will enable sirmixalot enrollment for this '.
          'repo. This means that the merged commit produced while landing a '.
          'diff will be put to `%s` branch instead of the master branch.',
          'landed/TIMESTAMP'),
        'default' => false,
        'example' => 'true',
      ),
      'uber.land.buildables-check' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, `%s` will check that changes you are about to land '.
          'does not land if you have failed harbormaster buildables. ',
          'arc land'),
        'default' => false,
        'example' => 'false',
      ),
      'uber.land.prevent-unaccepted-changes' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, `%s` will prevent developers to land changes that'.
          'are not accepted.',
          'arc land'),
        'default' => false,
        'example' => 'false',
      ),
      'uber.land.review-check' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, `%s` will check that local changes you are about to land '.
          'match diff that was submitted for review to Differential.',
          'arc land'),
        'default' => false,
        'example' => 'false',
      ),
      'uber.land.run.unit' => array(
        'type' => 'bool',
        'help' => pht('If true, `arc land` will run arc unit before landing'),
        'default' => false,
        'example' => 'false',
      ),
      'uber.land.submitqueue.regex' => array(
        'type' => 'string',
        'help' => pht(
          'If set, the regex will be used to filter the set of diffs that '.
          'need to go through SubmitQueue during arc land.'),
        'default' => '',
        'example' => '/apps\/iphone/'
      ),
      'uber.land.submitqueue.enable' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, `arc land` will merge changes on the server-side using'.
          'submitqueue'),
        'default' => false,
        'example' => 'false',
      ),
      'uber.land.submitqueue.uri' => array(
        'type' => 'string',
        'help' => pht('URI to use for the submitqueue backend'),
        'example' => '"https://submitqueue.uberinternal.com"',
      ),
      'uber.land.submitqueue.shadow' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, `arc land` will submit requests to submitqueue with'.
          'shadow_option=true, and on success land the request using the '.
          'arcanist gitland engine'
        ),
        'default' => false,
        'example' => 'false',
      ),
      'uber.land.submitqueue.tags' => array(
        'type' => 'list',
        'help' => pht('List of tags to be used when creating tbr excuse tasks.'),
        'default' => array(),
        'example' => '["SubmitQueue-Mobile"]',
      ),
      'uber.land.submitqueue.owners' => array(
        'type' => 'list',
        'help' => pht(
          'List of owners for the given repository who need to be tagged'.
          'on tbr excuse tasks.'),
        'default' => array(),
        'example' => '["X, Y, Z"]',
      ),
      'uber.diff.git.push.verify' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, `arc diff` will run `git push` with `--verify` flag, '.
          'and if missing (or false), `arc diff` will run `git push` with '.
          '`--no-verify` flag.'),
        'default' => false,
      ),
      'uber.diff.staging.uri.replace' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, and staging environment is setup, then it will replace '.
          'staging uri with git remote name defined for it. It parses '.
          '"git remote -v" output and uses first remote name where remote url '.
          'matches staging uri.'),
        'default' => false,
      ),
      'uber.diff.prompt.allow-empty-binary' => array(
        'type' => 'bool',
        'help' => pht(
          'If true, diff with empty binary upload will produce exceptions'.
          'unless diff explicitly with `--skip-binaries` option'),
          'default' => true,
      ),
    );
  }

  private function getOption($key) {
    return idx($this->getOptions(), $key, array());
  }

  public function getAllKeys() {
    return array_keys($this->getOptions());
  }

  public function getHelp($key) {
    return idx($this->getOption($key), 'help');
  }

  public function getExample($key) {
    return idx($this->getOption($key), 'example');
  }

  public function getType($key) {
    return idx($this->getOption($key), 'type', 'wild');
  }

  public function getLegacyName($key) {
    return idx($this->getOption($key), 'legacy');
  }

  public function getDefaultSettings() {
    $defaults = array();
    foreach ($this->getOptions() as $key => $option) {
      if (array_key_exists('default', $option)) {
        $defaults[$key] = $option['default'];
      }
    }
    return $defaults;
  }

  public function willWriteValue($key, $value) {
    $type = $this->getType($key);
    switch ($type) {
      case 'bool':
        if (strtolower($value) === 'false' ||
            strtolower($value) === 'no' ||
            strtolower($value) === 'off' ||
            $value === '' ||
            $value === '0' ||
            $value === 0 ||
            $value === false) {
          $value = false;
        } else if (strtolower($value) === 'true' ||
                   strtolower($value) === 'yes' ||
                   strtolower($value) === 'on' ||
                   $value === '1' ||
                   $value === 1 ||
                   $value === true) {
          $value = true;
        } else {
          throw new ArcanistUsageException(
            pht(
              "Type of setting '%s' must be boolean, like 'true' or 'false'.",
              $key));
        }
        break;
      case 'list':
        if (is_array($value)) {
          break;
        }

        if (is_string($value)) {
          $list = json_decode($value, true);
          if (is_array($list)) {
            $value = $list;
            break;
          }
        }

        throw new ArcanistUsageException(
          pht(
            "Type of setting '%s' must be list. You can specify a list ".
            "in JSON, like: %s",
            $key,
            '["apple", "banana", "cherry"]'));

      case 'string':
        if (!is_scalar($value)) {
          throw new ArcanistUsageException(
            pht(
              "Type of setting '%s' must be string.",
              $key));
        }
        $value = (string)$value;
        break;
      case 'wild':
        break;
      case 'aliases':
        throw new Exception(
          pht(
            'Use "arc alias" to configure aliases, not "arc set-config".'));
        break;

    }

    return $value;
  }

  public function willReadValue($key, $value) {
    $type = $this->getType($key);
    switch ($type) {
      case 'string':
        if (!is_string($value)) {
          throw new ArcanistUsageException(
            pht(
              "Type of setting '%s' must be string.",
              $key));
        }
        break;
      case 'bool':
        if ($value !== true && $value !== false) {
          throw new ArcanistUsageException(
            pht(
              "Type of setting '%s' must be boolean.",
              $key));
        }
        break;
      case 'list':
        if (!is_array($value)) {
          throw new ArcanistUsageException(
            pht(
              "Type of setting '%s' must be list.",
              $key));
        }
        break;
      case 'wild':
      case 'aliases':
        break;
    }

    return $value;
  }

  public function formatConfigValueForDisplay($key, $value) {
    if ($value === false) {
      return 'false';
    }

    if ($value === true) {
      return 'true';
    }

    if ($value === null) {
      return 'null';
    }

    if (is_string($value)) {
      return '"'.$value.'"';
    }

    if (is_array($value)) {
      // TODO: Both json_encode() and PhutilJSON do a bad job with one-liners.
      // PhutilJSON splits them across a bunch of lines, while json_encode()
      // escapes all kinds of stuff like "/". It would be nice if PhutilJSON
      // had a mode for pretty one-liners.
      $value = json_encode($value);

      // json_encode() unnecessarily escapes "/" to prevent "</script>" stuff,
      // optimistically unescape it for display to improve readability.
      $value = preg_replace('@(?<!\\\\)\\\\/@', '/', $value);
      return $value;
    }

    return $value;
  }

}
