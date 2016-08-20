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

class MysqlRestore extends Command
{
    protected $storage;
    protected $questioner;
    protected $validator;

    protected $connectionName;
    protected $connection;
    protected $availableDbConnections;
    protected $filename;
    protected $localFilename;
    protected $backupFiles;
    protected $backupFileNames;
    protected $backupFileAlternatives;
    protected $restoreLatestBackup;
    protected $availableCloudProviders;
    protected $storageProvider;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new ConfigurationValidator();
        $this->backupFiles = [];
        $this->backupFileNames = [];
        $this->backupFileAlternatives = [];
    }

    protected function configure()
    {
        $this
            ->setName('mysql:restore')
            ->setDescription('Restores a MySQL database from a file')
            ->addOption(
                'connection',
                'c',
                InputOption::VALUE_OPTIONAL,
                "Especifiy the database connection name",
                null
            )
            ->addOption(
                'filename',
                'f',
                InputOption::VALUE_OPTIONAL,
                "Especifiy the database connection name",
                null
            )
            ->addOption(
                'all-backup-files',
                'A',
                InputOption::VALUE_NONE,
                "Display all backup files as selectable option",
                null
            )
            ->addOption(
                'restore-latest-backup',
                'L',
                InputOption::VALUE_NONE,
                "Use latest backup file to restore database",
                null
            )
            ->addOption(
                'yes',
                'y',
                InputOption::VALUE_NONE,
                "Confirms database restoration",
                null
            )
            ->addOption(
                'from-cloud',
                'C',
                InputOption::VALUE_NONE,
                "Display a list of cloud providers where to retrieve backup files.",
                null
            )
            ->addOption(
                'from-provider',
                'p',
                InputOption::VALUE_REQUIRED,
                "Explicit define the cloud provider where to retrieve backup files",
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validator->validate();
        $this->storage = new Storage();

        $this->questioner = $this->getHelper('question');

        $this->availableDbConnections = $GLOBALS['DB_CONNECTIONS'];

        $this->connectionName = $input->getOption('connection');
        
        if (!is_null($this->connectionName) && !array_key_exists($this->connectionName, $this->availableDbConnections)) {
            $output->displayErrorAndDie("There is no parameter configuration for connection named '" . $this->connectionName . "' in the file .backap.yaml");
        }

        $this->setStorageProvider($input, $output);

        $files = $this->storage->disk($this->storageProvider)->files();

        rsort($files);

        foreach ($files as $file) {
            if (ends_with($file['basename'], '.sql') || ends_with($file['basename'], '.sql.gz')) {
                array_push($this->backupFiles, $file);
                array_push($this->backupFileNames, $file['basename']);
            }
        }

        if (count($this->backupFiles) == 0) {
            $output->displayErrorAndDie("There are no backup files to restore");
        }

        $this->restoreLatestBackup = $input->getOption('restore-latest-backup');
        
        if (!$this->restoreLatestBackup) {
            $this->filename = $input->getOption('filename');

            if (!is_null($this->filename) && !in_array($this->filename, $this->backupFiles)) {
                $output->displayErrorAndDie("There is no backup file named '" . $this->filename . "'");
            }
        }

        $this->setConnection($input, $output);

        $this->setFilename($input, $output);

        $this->confirmRestoration($input, $output);
    }

    protected function setStorageProvider($input, $output)
    {
        $fromCloud = $input->getOption('from-cloud');
        $this->storageProvider = $input->getOption('from-provider');

        $this->availableCloudProviders = $this->storage->getCloudAdapters();

        if ($this->storageProvider && $fromCloud) {
            if (!in_array($this->storageProvider, $this->availableCloudProviders)) {
                $output->displayErrorAndDie("There is no parameter configuration for cloud provider named '" . $this->storageProvider . "' in the .backap.yaml file");
            }
        }

        if(is_null($this->storageProvider) && $fromCloud)
        {
            if (count($this->availableCloudProviders) == 0) {
                $output->displayErrorAndDie("There are no cloud providers configured on .backap.yaml file");
            }

            $defaultIndex = count($this->availableCloudProviders) - 1;

            $question = new ChoiceQuestion(
                'Which cloud provider do you want to restore from? (default ' . $this->availableCloudProviders[$defaultIndex] . ')',
                $this->availableCloudProviders,
                $defaultIndex
            );

            $question->setErrorMessage('Option %s is invalid.' . PHP_EOL);

            $this->storageProvider = $this->questioner->ask($input, $output, $question);
        }

        if (!is_null($this->storageProvider)) {
            $output->display("Using <info>" . $this->storageProvider . "</info> as cloud provider, data will be restored from there");
        } else {
            $this->storageProvider = 'local';
            $output->display("Using <info>Local</info> provider, data will be restored from your own system");
        }

    }

    protected function setConnection($input, $output)
    {
        $connectionNames = array_keys($this->availableDbConnections);

        if (is_null($this->connectionName) && count($connectionNames) > 1) {
            $question = new ChoiceQuestion(
                'Please select a database connection (default)',
                $connectionNames,
                0
            );
            $question->setErrorMessage('Option %s is invalid.' . PHP_EOL);

            $this->connectionName = $this->questioner->ask($input, $output, $question);

            $output->display("You have just selected <info>" . $this->connectionName . "</info> as connection");

            $this->connection = $this->availableDbConnections[$this->connectionName];
        }

        if (is_null($this->connectionName) && count($connectionNames) == 1) {
            $this->connectionName = $GLOBALS['DEFAULT_DB_CONNECTION'];
        }

        $this->connection = $this->availableDbConnections[$this->connectionName];

        $output->display("Using <info>" . $this->connectionName . "</info> connection, database <info>" . $this->connection['database'] . "</info> will be restored");
    }

    protected function setFilename($input, $output)
    {
        if (is_null($this->filename)) {
            if ($this->restoreLatestBackup) {
                $this->filename = $this->backupFiles[count($this->backupFiles) - 1];
            } else {
                if ($input->getOption('all-backup-files')) {
                    $this->backupFileAlternatives = $this->backupFileNames;
                } else {
                    $selectedFiles = [];
                    foreach ($this->backupFileNames as $index => $backupFileName) {
                        if (ends_with($backupFileName, '_' . $this->connection['database'] . '.sql') || ends_with($backupFileName, '_' . $this->connection['database'] . 'sql.gz')) {
                            array_push($this->backupFileAlternatives, $backupFileName);
                            array_push($selectedFiles, $this->backupFiles[$index]);
                        }
                    }
                    $this->backupFiles = $selectedFiles;
                }

                if (count($this->backupFileAlternatives) == 0) {
                    $output->displayErrorAndDie("There are no backup files for database '" . $this->connection['database'] . "' to restore");
                }

                $defaultIndex = 0;

                $question = new ChoiceQuestion(
                    'Which database backup file do you want to restore on <info>' . $this->connection['database'] . '</info>? (default ' . $this->backupFileAlternatives[$defaultIndex] . ')',
                    $this->backupFileAlternatives,
                    $defaultIndex
                );

                $question->setErrorMessage('Option %s is invalid.' . PHP_EOL);

                // show table
                $this->showBackupFilesTableData($this->backupFiles, $output);

                $this->filename = $this->questioner->ask($input, $output, $question);
            }

        }

        $output->display("You have just selected <info>" . $this->filename . "</info> to be restored in <info>" . $this->connection['database'] . "</info> database");
    }

    protected function confirmRestoration($input, $output) {
        if (!$input->getOption('yes')) {
            $question = new ConfirmationQuestion('<question>Continue with database restoration? (default NO)</question>', false, '/^(y|j)/i');

            if (!$this->questioner->ask($input, $output, $question)) {
                $output->displayErrorAndDie('Database restoration cancelled');
            }
        }

        $this->restoreDatabase($output);
    }

    protected function restoreDatabase($output)
    {
        $hostname = escapeshellarg($this->connection['hostname']);
        $port = $this->connection['port'];
        $database = $this->connection['database'];
        $username = escapeshellarg($this->connection['username']);
        $password = $this->connection['password'];

        $databaseArg = escapeshellarg($database);
        $portArg = !empty($port) ? "-P ". escapeshellarg($port) : "";
        $passwordArg = !empty($password) ? "-p" . escapeshellarg($password) : "";

        $this->localFilename = $this->filename;

        if ($this->storageProvider != 'local') {
            if (ends_with($this->filename, '.gz')) {
                $this->localFilename = str_replace('.sql.gz', '.cloud.sql.gz', $this->filename);
            } else {
                $this->localFilename = str_replace('.sql', '.cloud.sql', $this->filename);
            }
            $this->storage->syncFileFromProvider($this->storageProvider, $this->filename, $this->localFilename);
        }

        $this->storage->disk('local');

        $filename = $this->localFilename;

        if (ends_with($filename, '.gz')) {
            $fileContent = gzuncompress($this->storage->read($this->localFilename));
            $filename = str_replace('.sql.gz', '.tmp', $this->localFilename);
            $this->storage->write($filename, $fileContent);
            $isTempFilename = true;
        }

        $restoreCommand =  MYSQL_PATH . " -h $hostname $portArg -u$username $passwordArg $databaseArg < " . $this->storage->absFilePath($filename);

        exec($restoreCommand, $restoreResult, $result);

        if ($result == 0) {
            $output->display("Database <info>$database</info> restored successfully from <info>" . $this->filename . "</info>");
        } else {
            $output->displayError("Database $database cannot be restored");
        }

        if (isset($isTempFilename)) {
            $this->storage->delete($filename);
        }
        if ($this->localFilename != $this->filename) {
            $this->storage->delete($this->localFilename);
        }
    }

    protected function showBackupFilesTableData($files, $output)
    {
        $headers = ['option', 'name', 'size', 'created at'];
        $rows = [];

        foreach ($files as $index => $file) {
            array_push($rows, [
                $index,
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
}