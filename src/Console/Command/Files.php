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

class Files extends Command
{
    protected $storage;
    protected $questioner;
    protected $validator;

    protected $availableCloudProviders;
    protected $cloudProvider;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new ConfigurationValidator();
    }

    protected function configure()
    {
        $this
            ->setName('files')
            ->setDescription('View all backups files')
            ->addOption(
                'from-cloud',
                'C',
                InputOption::VALUE_NONE,
                "Display a list of cloud providers to retrieve files from",
                null
            )
            ->addOption(
                'from-provider',
                'p',
                InputOption::VALUE_REQUIRED,
                "Especifiy the cloud provider to retrieve files from",
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validator->validate();
        $this->storage = new Storage();

        $this->questioner = $this->getHelper('question');

        $this->setCloudProvider($input, $output);

        $files = $this->storage->disk($this->cloudProvider ? $this->cloudProvider : 'local')->files();

        if (count($files) == 0) {
            $output->displayErrorAndDie("There are no backup files");
        }

        $headers = ['name', 'size', 'created at'];
        $rows = [];

        foreach ($files as $file) {
            array_push($rows, [
                $file['basename'],
                formatBytes($file['size']),
                Carbon::createFromTimestamp($file['timestamp'], BACKAP_TIMEZONE)->toDateTimeString(),
            ]);
        }

        $style = new TableStyle();

        $style->setHorizontalBorderChar('<fg=cyan>-</>')
            ->setVerticalBorderChar('<fg=cyan>|</>')
            ->setCrossingChar('<fg=cyan>+</>')
            -> setCellHeaderFormat('<fg=yellow>%s</>');

        $table = new Table($output);

        $table->setHeaders($headers)
            ->setRows($rows);
        $table->setStyle($style);
        $table->render();
    }

    protected function setCloudProvider($input, $output)
    {
        $fromCloud = $input->getOption('from-cloud');
        $this->cloudProvider = $input->getOption('from-provider');

        $this->availableCloudProviders = $this->storage->getCloudAdapters();

        if ($this->cloudProvider && $fromCloud) {
            if (!in_array($this->cloudProvider, $this->availableCloudProviders)) {
                $output->displayErrorAndDie("There is no parameter configuration for cloud provider named '" . $this->cloudProvider . "' in the .backap.yaml file");
            }
        }

        if(is_null($this->cloudProvider) && $fromCloud)
        {
            if (count($this->availableCloudProviders) == 0) {
                $output->displayErrorAndDie("There are no cloud providers configured on .backap.yaml file");
            }

            $defaultIndex = count($this->availableCloudProviders) - 1;

            $question = new ChoiceQuestion(
                'Wich cloud provider do you want to restore from? (default ' . $this->availableCloudProviders[$defaultIndex] . ')',
                $this->availableCloudProviders,
                $defaultIndex
            );

            $question->setErrorMessage('Option %s is invalid.' . PHP_EOL);

            $this->cloudProvider = $this->questioner->ask($input, $output, $question);
        }

        if (!is_null($this->cloudProvider)) {
            $output->display("Viewing files stored on <info>" . $this->cloudProvider . "</info>");
        } else {
            $output->display("Viewing files from <info>Local</info>, these files are stored on your own system");
        }

    }
}