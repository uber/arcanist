<?php

final class UberMultiTestEngine extends ArcanistUnitTestEngine {

    public function run() {
        $config = $this->getConfigurationManager();
        $engines = $config->getConfigFromAnySource('unit.engine.multi.engines');
        $results = array();

        foreach ($engines as $engine_or_configuration) {
            if (is_array($engine_or_configuration)) {
                $engine_class = $engine_or_configuration['engine'];
                foreach ($engine_or_configuration as $configuration => $value) {
                    if ($configuration != 'engine') {
                        $config->setRuntimeConfig($configuration, $value);
                    }
                }
            } else {
                $engine_class = $engine_or_configuration;
            }

            $engine = $this->instantiateEngine($engine_class);

            $unitEngineCommand = $engine_or_configuration['unit.engine.command'] ?? null;
            $buildSystem = $engine_or_configuration['build-system'] ?? null;
            $target = $engine_or_configuration['target'] ?? null;

            $unit_check = '';
            if ($unitEngineCommand) {
                $unit_check = $unitEngineCommand;
            } elseif ($buildSystem && $target) {
                $unit_check = $buildSystem . ' ' . $target;
            }

            # paths use for backward compatibility with changes at UberArcanistConfiguration
            $paths = [$unit_check];
            $profiler = PhutilServiceProfiler::getInstance();
            $id = $profiler->beginServiceCall(array(
                'type' => 'unit',
                'paths' => $paths,
                'engine' => $engine_class,
            ));
            $results = array_merge($results, $engine->run());
            $profiler->endServiceCall($id, array());
        }

        return $results;
    }

    private function instantiateEngine($engine_class) {
        $is_test_engine = is_subclass_of($engine_class, 'ArcanistBaseUnitTestEngine') || is_subclass_of($engine_class, 'ArcanistUnitTestEngine');
        if (!class_exists($engine_class) || !$is_test_engine) {
            throw new ArcanistUsageException(
                "Configured unit test engine '{$engine_class}' is not a subclass of 'ArcanistUnitTestEngine'."
                );
        }

        $engine = newv($engine_class, array());
        $engine->setWorkingCopy($this->getWorkingCopy());
        $engine->setConfigurationManager($this->getConfigurationManager());
        $engine->setRunAllTests($this->getRunAllTests());
        $engine->setPaths($this->getPaths());
        return $engine;
    }

}
