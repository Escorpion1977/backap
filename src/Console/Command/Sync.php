<?php

namespace Backap\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Backap\Storage\Storage;
use Carbon\Carbon;
use Backap\Validation\ConfigurationValidator;

class Sync extends Command
{
    protected $storage;
    protected $questioner;
    protected $validator;

    protected $availableCloudProviders;
    protected $cloudProvider;
    protected $action;
    protected $isPull;
    protected $isPush;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new ConfigurationValidator();
    }

    protected function configure()
    {
        $this
            ->setName('sync')
            ->setDescription('Synchronize backup files with cloud providers. Pull files from cloud or push file to remote storage providers.')
            ->addArgument(
                'action',
                InputArgument::REQUIRED,
                'Do you want to perform a pull or a push?'
            )
            ->addOption(
                'provider',
                'p',
                InputOption::VALUE_REQUIRED,
                "Especifiy the cloud provider",
                null
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                "Confirms action",
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validator->validate();
        $this->storage = new Storage();

        $this->questioner = $this->getHelper('question');

        $this->setAction($input, $output);

        $this->setCloudProvider($input, $output);

        $files = $this->storage->disk($this->isPull ? $this->cloudProvider : 'local')->files();

        if (count($files) == 0) {
            $output->displayErrorAndDie("There are no backup files to " . $this->action);
        }

        $this->confirmAction($input, $output);
    }

    protected function setAction($input, $output)
    {
        $this->action = trim($input->getArgument('action'));

        if ($this->action == 'pull') {
            $this->isPull = true;
        }
        elseif ($this->action == 'push') {
            $this->isPush = true;
        }
        else {
            $output->displayErrorAndDie("Action MUST be 'push' or  'pull'");
        }
    }

    protected function setCloudProvider($input, $output)
    {
        $this->cloudProvider = $input->getOption('provider');

        $this->availableCloudProviders = $this->storage->getCloudAdapters();

        if ($this->cloudProvider) {
            if (!in_array($this->cloudProvider, $this->availableCloudProviders)) {
                $output->displayErrorAndDie("There is no parameter configuration for cloud provider named '" . $this->cloudProvider . "' in the .backap.yaml file");
            }
        }

        if(is_null($this->cloudProvider))
        {
            if (count($this->availableCloudProviders) == 0) {
                $output->displayErrorAndDie("There are no cloud providers configured on .backap.yaml file");
            }

            $defaultIndex = count($this->availableCloudProviders) - 1;

            $question = new ChoiceQuestion(
                'Which cloud provider do you want to ' . $this->action . ($this->isPush ? ' to' : ' from') . '? (default ' . $this->availableCloudProviders[$defaultIndex] . ')',
                $this->availableCloudProviders,
                $defaultIndex
            );

            $question->setErrorMessage('Option %s is invalid.' . PHP_EOL);

            $this->cloudProvider = $this->questioner->ask($input, $output, $question);
        }

            $output->display(($this->isPull ? 'Pulling' : 'Pushing') . " files " . ($this->isPull ? 'from' : 'to') . " <info>" . $this->cloudProvider . "</info>");

    }

    protected function confirmAction($input, $output)
    {
        if (!$input->getOption('yes')) {
            $question = new ConfirmationQuestion('<question>Continue with sync ' . $this->action .'? (default NO)</question>', false, '/^(y|j)/i');

            if (!$this->questioner->ask($input, $output, $question)) {
                $output->displayErrorAndDie('Sync cancelled');
            }
        }

        $this->performAction($output);
    }

    protected function performAction($output)
    {
        if ($this->isPush) {
            $this->storage->syncPush($this->cloudProvider);
        }
        else {
            $this->storage->syncPull($this->cloudProvider);
        }
    }
}