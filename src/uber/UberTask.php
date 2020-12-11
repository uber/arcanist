<?php

// class which encapsulates complexity of getting jira issue
final class UberTask extends Phobject {
  private $future;
  private $issues = false;

  const URL = 'https://arcanist-the-service.uberinternal.com/';
  const JIRA_CREATE_IN_URL = 'https://t3.uberinternal.com/secure/'.
    'CreateIssueDetails!init.jspa?pid=%s&issuetype=10002&assignee=%s'.
    '&summary=%s&description=%s';
  const JIRA_CREATE_URL = 'https://t3.uberinternal.com/secure/CreateIssue'.
    '!default.jspa';

  public function __construct($jql = '', $url = self::URL) {
    $usso = new UberUSSO();
    $hostname = parse_url($url, PHP_URL_HOST);
    $token = $usso->maybeUseUSSOToken($hostname);
    if (!$token) {
      $token = $usso->getUSSOToken($hostname);
    }
    $payload = '{}';
    if ($jql) {
      $payload = json_encode(array('jql' => $jql));
    }
    $future = id(new HTTPSFuture($url, $payload))
      ->setFollowLocation(false)
      ->setMethod('POST')
      ->addHeader('Authorization', "Bearer ${token}")
      ->addHeader('Rpc-Caller', 'arcanist')
      ->addHeader('Rpc-Encoding', 'json')
      ->addHeader('Rpc-Procedure', 'ArcanistTheService::getIssues');
    $future->start();
    $this->future = $future;
  }

  public function getIssues() {
    if ($this->issues !== false) {
      return $this->issues;
    }
    list($body, $headers) = $this->future->resolvex();
    if (empty($body)) {
      $this->issues = array();
      return $this->issues;
    }
    $issues = phutil_json_decode($body);
    if (!$issues) {
      $this->issues = array();
      return $this->issues;
    }
    $this->issues = idx($issues, 'issues', array());
    return $this->issues;
  }

  public static function getJiraCreateIssueLink(
    $project_pid,
    $assignee,
    $summary,
    $description) {

    return sprintf(self::JIRA_CREATE_IN_URL,
                   urlencode($project_pid),
                   urlencode($assignee),
                   urlencode($summary),
                   urlencode($description));
  }

  public static function getTasksAndProjects($issues = array()) {
    $tasks = array();
    $projects = array();

    foreach ($issues as $issue) {
      $pkey = $issue['project']['key'];
      if (!isset($projects[$pkey])) {
        $projects[$pkey] = array(
          'id' => $issue['project']['id'],
          'tasks' => 0,
        );
      }
      $projects[$pkey]['tasks']++;
      $tasks[] = array('key' => $issue['key'], 'summary' => $issue['summary']);
    }
    return array($tasks, $projects);
  }
}
