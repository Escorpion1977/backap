<?php

namespace Backap\Console;

use Backap\Backap;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

class Application extends BaseApplication
{
	private static $logo = '<primary>
    ____  ___   ________ __ ___    ____ 
   / __ )/   | / ____/ //_//   |  / __ \
  / __  / /| |/ /   / ,<  / /| | / /_/ /
 / /_/ / ___ / /___/ /| |/ ___ |/ ____/ 
/_____/_/  |_\____/_/ |_/_/  |_/_/      
</primary>
';

	public function __construct()
	{
		parent::__construct('Backap', Backap::getVersion());
	}

    /**
     * {@inheritDoc}
     */
    public function run(InputInterface $input = null, OutputInterface $output = null)
    {
        if (null === $output) {
            $output = new ConsoleOutput();
        }
        return parent::run($input, $output);
    }

    public function getHelp()
    {
        return self::$logo . parent::getHelp();
    }
}