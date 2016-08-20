<?php

namespace Backap;

use Backap\Console\Application as ConsoleApplication;
use Backap\Console\ConsoleOutput;
use Backap\Console\Command\Init;
use Backap\Console\Command\Files;
use Backap\Console\Command\Sync;
use Backap\Console\Command\MysqlDump;
use Backap\Console\Command\MysqlRestore;
use Backap\Console\Command\Update;
use Backap\Storage\Storage;
use Backap\Validation\DependenciesValidator;
use Backap\Backap;

class Application
{
    private $output;

    public function __construct()
    {
        $this->defineConstants();
        $this->output = new ConsoleOutput();

        $application = new ConsoleApplication();
        $application->add(new Init());
        $application->add(new Files());
        $application->add(new Sync());
        $application->add(new MysqlDump());
        $application->add(new MysqlRestore());
        if (isPhar()) {
            $application->add(new Update());
        }
        $application->run();
    }

    protected function defineConstants()
    {
        define('CONFIG_YAML_PATH', WORKING_DIR . DIRECTORY_SEPARATOR . ".backap.yaml");
        define('CONFIG_YAML_EXAMPLE_PATH', __DIR__ . DIRECTORY_SEPARATOR . ".backap.yaml.example");
    }
}