<?php

namespace Backap\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Backap\Storage\Storage;
use Carbon\Carbon;
use Backap\Validation\ConfigurationValidator;

class MysqlDump extends Command
{
    protected $storage;
    protected $validator;

    protected $isCompressionEnabled;
    protected $cloudAdapters;
    protected $sync;

    public function __construct()
    {
        parent::__construct();
        $this->validator = new ConfigurationValidator();
    }

    protected function configure()
    {
        $this
            ->setName('mysql:dump')
            ->setDescription('Dumps a MySQL database to a file')
            ->addOption(
                'connection',
                'c',
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                "Especifiy the database connection names",
                array()
            )
            ->addOption(
                'no-compress',
                null,
                InputOption::VALUE_NONE,
                "Disable file compression regardless if is enabled in .backap.yaml file. This option will be always overwrited by --compress option",
                null
            )
            ->addOption(
                'compress',
                null,
                InputOption::VALUE_NONE,
                "Enable file compression regardless if is disabled in .backap.yaml file. This option will always overwrite --no-compress option",
                null
            )
            ->addOption(
                'sync',
                's',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                "Synchronize dump file with cloud providers. This option will be always overwrited by --sync-all option",
                null
            )
            ->addOption(
                'sync-all',
                'S',
                InputOption::VALUE_NONE,
                "Synchronize dump file with all cloud providers. This option will always overwrite --sync option",
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->validator->validate();
        $this->storage = new Storage();

        $availableDbConnections = $GLOBALS['DB_CONNECTIONS'];

        $connectionNames = $input->getOption('connection');

        if (empty($connectionNames)) {
            array_push($connectionNames, $GLOBALS['DEFAULT_DB_CONNECTION']);
        }

        $availableCloudProviders = $this->storage->getCloudAdapters();

        $this->cloudAdapters = $input->getOption('sync');

        $syncAll = $input->getOption('sync-all');

        $this->sync = count($this->cloudAdapters) >= 1 || $syncAll;

        if (!$syncAll) {
            foreach ($this->cloudAdapters as $provider) {
                if (!in_array($provider, $availableCloudProviders)) {
                    $output->displayErrorAndDie("There is no parameter configuration for cloud provider named '$provider' in the .backap.yaml file");
                }
            }
        } else {
            $this->cloudAdapters = $availableCloudProviders;
        }

        $compress = $input->getOption('compress');
        $noCompress = $input->getOption('no-compress');

        if ($compress) {
            $this->isCompressionEnabled = true;
        } elseif ($noCompress) {
            $this->isCompressionEnabled = false;
        } else {
            $this->isCompressionEnabled = ENABLE_COMPRESSION;
        }

        foreach ($connectionNames as $connectionName) {
            if (!array_key_exists($connectionName, $availableDbConnections)) {
                $output->displayErrorAndDie("There is no parameter configuration for connection named '$connectionName' in the .backap.yaml file");
            }
        }

        foreach ($connectionNames as $connectionName) {
            $this->dumpDatabase($availableDbConnections[$connectionName], $output);
        }
    }

    protected function dumpDatabase(array $connection, OutputInterface $output)
    {
        $hostname = escapeshellarg($connection['hostname']);
        $port = $connection['port'];
        $database = $connection['database'];
        $username = escapeshellarg($connection['username']);
        $password = $connection['password'];

        $databaseArg = escapeshellarg($database);
        $portArg = !empty($port) ? "-P ". escapeshellarg($port) : "";
        $passwordArg = !empty($password) ? "-p" . escapeshellarg($password) : "";

        $compressionMessage = $this->isCompressionEnabled ? "and compressed" : "";

        $filename = Carbon::now()->format('YmdHis') . "_" . $database . ".sql" . ($this->isCompressionEnabled ? '.gz' : '');

        $path = LOCAL_STORAGE_PATH . DIRECTORY_SEPARATOR . $filename;
        
        $dumpCommand = MYSQLDUMP_PATH . " -C -h $hostname $portArg -u$username $passwordArg --single-transaction --skip-lock-tables --quick $databaseArg";

        exec($dumpCommand, $dumpResult, $result);

        if ($result == 0) {
            $dumpResult = implode(PHP_EOL, $dumpResult);
            $dumpResult = $this->isCompressionEnabled ? gzcompress($dumpResult, 9) : $dumpResult;
            $this->storage->write($filename, $dumpResult);
            $output->display("Database <info>$database</info> dumped $compressionMessage successfully to <info>$path</info>");
            if ($this->sync) {
                $this->storage->syncFile($filename, $this->cloudAdapters);
            }
        } else {
            $output->displayError("Database $database cannot be dumped");
        }
    }
}