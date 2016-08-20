<?php

namespace Backap\Console;

use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\ConsoleOutput as BaseConsoleOutput;

class ConsoleOutput extends BaseConsoleOutput
{
    public function __construct()
    {
        $formatter = new OutputFormatter(null, [
            'highlight' => new OutputFormatterStyle('red'),
            'primary' => new OutputFormatterStyle('cyan'),
            'warning' => new OutputFormatterStyle('black', 'yellow'),
        ]);
        parent::__construct(BaseConsoleOutput::VERBOSITY_NORMAL, null, $formatter);
    }

    public function display($message)
    {
        $this->writeln($message . PHP_EOL);
    }

    public function displayAndDie($message)
    {
        $this->writeln($message . PHP_EOL);
        die();
    }

    public function displayInfo($message)
    {
        $this->writeln("<info>$message</info>" . PHP_EOL);
    }

    public function displayError($message)
    {
        $this->writeln("<error>$message</error>" . PHP_EOL);
    }

    public function displayErrorAndDie($message)
    {
        $this->displayError($message);
        die();
    }

}