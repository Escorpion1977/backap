<?php

namespace Backap\Validation;

use Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;
use Backap\Console\ConsoleOutput;

class ConfigurationValidator
{
    private $output;
    private $config;
    private $mandatoryAttributes;
    private $optionalAttributes;

	public function __construct()
	{
        $this->output = new ConsoleOutput();
        $this->mandatoryAttributes = ['default_connection', 'connections'];
        $this->optionalAttributes = ['timezone', 'backap_storage_path', 'mysqldump_path', 'mysql_path', 'enable_compression', 'cloud'];
        $this->arrayAttributes = ['connections', 'connections.*', 'cloud', 'cloud.*'];
        $this->stringAttributes = ['default_connection', 'backap_storage_path', 'mysqldump_path', 'mysql_path'];
        $this->booleanAttributes = ['enable_compression'];
	}

	public function validate()
	{
        if ($this->loadConfigYaml()) {
            $this->defineConstants();
            $this->defineGlobals();
        } else {
        	$this->output->displayError("Please create a .backap.yaml file before continue.");
        	$this->output->displayAndDie("Run <info>init</info> command to create one.");
        }
	}

    protected function existConfigYaml()
    {
        return file_exists(CONFIG_YAML_PATH);
    }

    protected function loadConfigYaml()
    {
        if ($this->existConfigYaml()) {
            try {
                $this->config = Yaml::parse(file_get_contents(CONFIG_YAML_PATH));
                $this->validateConfigAttributes();
                return true;
            } catch (ParseException $e) {
                printf("Unable to parse the YAML string: %s", $e->getMessage());
                die();
            }
        }
        return false;
    }

    protected function validateConfigAttributes()
    {
        foreach ($this->mandatoryAttributes as $attr) {
            if (!isset($this->config[$attr])) {
                $this->output->displayErrorAndDie("[$attr] attribute MUST exists in the '.backap.yaml' file");
            }
            if (is_null($this->config[$attr])) {
                $this->output->displayErrorAndDie("[$attr] attribute MUST have a value in the '.backap.yaml' file");
            }
        }

        foreach ($this->optionalAttributes as $attr) {
            if (!isset($this->config[$attr])) {
                $this->config[$attr] = in_array($attr, $this->arrayAttributes) ? [] : null;
            }
        }

        foreach ($this->arrayAttributes as $attrs) {
            $attrParts = explode('.', $attrs);
            $attrParts = count($attrParts) == 1 ? $attrParts[0] : $attrParts;

            if (is_array($attrParts)) {
                if ($attrParts[count($attrParts) - 1] == '*') {
                    array_pop($attrParts);
                    $path = implode('.', $attrParts);
                    foreach (getValueByPath($this->config, $path) as $key => $value) {
                        if (!is_array($value)) {
                            $redable = makeRedableConfigArray(array_merge($attrParts, [$key]));
                            $this->output->displayErrorAndDie("$redable attribute MUST be an ARRAY in the '.backap.yaml' file");
                        }
                    }
                } else {
                    $path = implode('.', $attrParts);
                    if (!is_array(getValueByPath($this->config, $path))) {
                        $redable = makeRedableConfigArray($attrParts);
                        $this->output->displayErrorAndDie("$redable attribute MUST be an ARRAY in the '.backap.yaml' file");
                    }
                }
            } else {
                if (!is_array($this->config[$attrParts])) {
                    $this->output->displayErrorAndDie("$attrParts attribute MUST be an ARRAY in the '.backap.yaml' file");
                }
            }

        }

        foreach ($this->stringAttributes as $attrs) {
            $attrParts = explode('.', $attrs);
            $attrParts = count($attrParts) == 1 ? $attrParts[0] : $attrParts;

            if (is_array($attrParts)) {
                if ($attrParts[count($attrParts) - 1] == '*') {
                    array_pop($attrParts);
                    $path = implode('.', $attrParts);
                    foreach (getValueByPath($this->config, $path) as $key => $value) {
                        if (!is_null($value) && !is_string($value)) {
                            $redable = makeRedableConfigArray(array_merge($attrParts, [$key]));
                            $this->output->displayErrorAndDie("$redable attribute MUST be a valid STRING in the '.backap.yaml' file");
                        }
                    }
                } else {
                    $path = implode('.', $attrParts);
                    $lastValue = getValueByPath($this->config, $path);
                    if (!is_null($lastValue) && !is_string($lastValue)) {
                        $redable = makeRedableConfigArray($attrParts);
                        $this->output->displayErrorAndDie("$redable attribute MUST be a valid STRING in the '.backap.yaml' file");
                    }
                }
            } else {
                if (!is_null($this->config[$attrParts]) && !is_string($this->config[$attrParts])) {
                    $this->output->displayErrorAndDie("[$attrParts] attribute MUST be a valid STRING in the '.backap.yaml' file");
                }
            }

        }

        $this->validateDefaultConnectionName();
    }

    protected function validateDefaultConnectionName()
    {
        if (!in_array($this->config['default_connection'], array_keys($this->config['connections']))) {
            $this->output->displayErrorAndDie("[default_connection] attribute MUST be declared on [connections] in the '.backap.yaml' file");
        }
    }

    protected function defineConstants()
    {
        $isStorageDefault = empty($this->config['backap_storage_path']);

        $workingDirStoragePath = WORKING_DIR . DIRECTORY_SEPARATOR . "storage";
        $workingDirDatabasePath = $workingDirStoragePath . DIRECTORY_SEPARATOR . "database";

        $localStoragePath = $isStorageDefault ? $workingDirDatabasePath : $this->config['backap_storage_path'];

        define("LOCAL_STORAGE_PATH", $localStoragePath);

        define('MYSQLDUMP_PATH', empty($this->config['mysqldump_path']) ? 'mysqldump' : $this->config['mysqldump_path']);
        define('MYSQL_PATH', empty($this->config['mysql_path']) ? 'mysql' : $this->config['mysql_path']);
        define('ENABLE_COMPRESSION', $this->config['enable_compression'] ? true : false);
        define('BACKAP_TIMEZONE', $this->config['timezone'] ? $this->config['timezone'] : 'UTC');
    }

    protected function defineGlobals()
    {
        $GLOBALS['DB_CONNECTIONS'] = $this->validateDbConnections($this->config['connections']);
        $GLOBALS['CLOUD_PROVIDERS'] = $this->validateCloudData($this->config['cloud']);
        $GLOBALS['DEFAULT_DB_CONNECTION'] = $this->config['default_connection'];
    }

    private function validateDbConnections(array $connections)
    {
        if (count($connections) < 1) {
            $this->output->displayErrorAndDie("There aren't any database connection configured in the '.backap.yaml' file");
        }

        $mandatoryAttributes = ['hostname', 'database', 'username'];
        $optionalAttributes = ['port', 'password'];

        foreach ($connections as $connectionName => $connectionParameters) {
            foreach (array_merge($mandatoryAttributes, $optionalAttributes) as $parameter) {
                if (!array_key_exists($parameter, $connectionParameters)) {
                    if (in_array($parameter, $mandatoryAttributes)) {
                        $this->output->displayErrorAndDie(assembleDbEnvVarName([$connectionName, $parameter]) . " MUST BE configured in the '.backap.yaml' file");
                    } else {
                        $connections[$connectionName][$parameter] = '';
                    }
                } else {
                    if (in_array($parameter, $mandatoryAttributes) && empty($connectionParameters[$parameter])) {
                        $this->output->displayErrorAndDie(assembleDbEnvVarName([$connectionName, $parameter]) . " can't be empty in the '.backap.yaml' file");
                    }
                }
            }
        }
        return $connections;
    }

    private function validateCloudData(array $cloud)
    {
        $cloudAdapters = [];
        foreach ($cloud as $adapterName => $adapterParameters) {
            if ($adapterParameters['provider'] == 'dropbox') {
                $cloudAdapters['dropbox'][$adapterName] = $this->validateDropboxData($adapterParameters);
            }
        }
        return $cloudAdapters;
    }

    private function validateDropboxData(array $dropboxData)
    {
        $mandatoryAttributes = ['access_token', 'app_secret'];
        $optionalAttributes = ['path'];

        foreach (array_merge($mandatoryAttributes, $optionalAttributes) as $parameter) {
            if (!array_key_exists($parameter, $dropboxData)) {
                if (in_array($parameter, $mandatoryAttributes)) {
                    $this->output->displayErrorAndDie(assembleYamlVarName(['cloud', 'dropbox', $parameter]) . " MUST BE configured in the '.backap.yaml' file");
                } else {
                    $dropboxData[$parameter] = null;
                }
            } else {
                if (in_array($parameter, $mandatoryAttributes) && empty($dropboxData[$parameter])) {
                    $this->output->displayErrorAndDie(assembleYamlVarName(['cloud', 'dropbox', $parameter]) . " can't be empty in the '.backap.yaml' file");
                }
            }
        }

        return $dropboxData;
    }
}