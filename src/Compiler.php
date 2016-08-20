<?php

namespace Backap;

use Phar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Backap\Support\ExcludeDevFilterIterator;
use Exception;

class Compiler
{
	private $srcRoot;
	private $buildRoot;

	public function __construct()
	{
		$this->srcRoot = __DIR__ . DIRECTORY_SEPARATOR ."..".DIRECTORY_SEPARATOR.'src';
		$this->buildRoot = __DIR__ . DIRECTORY_SEPARATOR ."..".DIRECTORY_SEPARATOR;
	}

	public function complie()
	{
		try {
			$phar = new Phar($this->buildRoot . "/backap.phar", 0, "backap.phar");
			

			$iterator = new RecursiveDirectoryIterator($this->buildRoot, RecursiveDirectoryIterator::SKIP_DOTS);

			$filterIterator = new ExcludeDevFilterIterator($iterator);

			$phar->buildFromIterator(new RecursiveIteratorIterator($filterIterator), $this->buildRoot . DIRECTORY_SEPARATOR);

			$this->addBackapBin($phar);

			$phar->compressFiles(Phar::GZ);

			$phar->setStub($this->getStub());

			printf("Backap Phar file succesfully created at " . $this->buildRoot . PHP_EOL);
		} catch (Exception $e) {
			echo $e->getMessage();
		}
	}

	private function addBackapBin($phar)
    {
        $content = file_get_contents(__DIR__.'/../bin/backap');
        $content = preg_replace('{^#!/usr/bin/env php\s*}', '', $content);
        $phar->addFromString('bin/backap', $content);
    }

	private function getStub()
	{
		$stub = <<<'EOF'
#!/usr/bin/env php
<?php

EOF;

		return $stub . <<<'EOF'
require_once "phar://" . __FILE__ . '/bin/backap';

__HALT_COMPILER();
EOF;
	}

}