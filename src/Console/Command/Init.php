<?php

namespace Backap\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Init extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('init')
            ->setDescription('Create a .backap.yaml file');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!file_exists(CONFIG_YAML_PATH)) {
            copy(CONFIG_YAML_EXAMPLE_PATH, CONFIG_YAML_PATH);
            $output->displayInfo(".backap.yaml file created successfully");
        } else {
            $output->displayInfo(".backap.yaml file already exists");
        }
    }
}