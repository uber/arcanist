<?php

final class UberAndroidTestEngine extends ArcanistUnitTestEngine {

    public function run() {
        $results = $this->checkstyle();
        return $results;
    }

    private function run_command($command) {
        exec($command, $output, $return_code);

        $result = new ArcanistUnitTestResult();
        $result->setName($command);
        $result->setResult($return_code == 0 ? ArcanistUnitTestResult::RESULT_PASS : ArcanistUnitTestResult::RESULT_FAIL);

        return array($result);
    }

    private function checkstyle() {
        return $this->run_command('./gradlew checkstyleAll');
    }
}
