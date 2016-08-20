<?php

namespace Backap\Storage;

use Exception;
use DirectoryIterator;
use League\Flysystem\MountManager;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Dropbox\DropboxAdapter;
use Dropbox\Client as DropboxClient;
use Dropbox\RootCertificates;
use Backap\Console\ConsoleOutput;
use Backap\Support\YamlLoader;

class Storage
{
	private $output;
	private $adapters = [];
	private $cloudAdapters = [];
	private $manager;
	private $selectedAdapter = 'local';

	public function __construct()
	{
		$this->output = new ConsoleOutput();
        $this->initLocalAdapter();
        $this->initCloudAdapters();
		$this->manager = new MountManager($this->adapters);
	}

	public function disk($adapter = 'local')
	{
		if (isset($this->adapters[$adapter])) {
			$this->selectedAdapter = $adapter;
			return $this;
		}
		$this->output->displayErrorAndDie("The '$adapter' adapter is not available");
	}

	public function getCloudAdapters()
	{
		return $this->cloudAdapters;
	}

	public function hasAdapter($adapter)
	{
		return in_array($adapter, array_keys($this->adapters));
	}

	public function syncFile($filename, $cloudAdapters = null)
	{
		$cloudAdapters = is_null($cloudAdapters) ? $this->cloudAdapters : $cloudAdapters;

		foreach ($cloudAdapters as $adapter) {
			if (!$this->disk($adapter)->has($filename)) {
				$this->manager->copy($this->appendAdapterPrefix($filename), $this->appendAdapterPrefix($filename, $adapter));
				$this->output->display("Database dump <info>$filename</info> synchronized successfully with <info>$adapter</info>");
			}
		}
	}

	public function syncFileFromProvider($adapter = null, $filename, $localFilename = null)
	{
		$localFilename = $localFilename ? $localFilename : $filename;

		$this->manager->put($this->appendAdapterPrefix($localFilename, 'local'), $this->manager->read($this->appendAdapterPrefix($filename, $adapter)));
	}

	public function syncPull($cloudProvider)
	{
		$files = $this->disk($cloudProvider)->files();

		foreach ($files as $file) {
		    $update = false;
		    $new = false;

		    if ( ! $this->manager->has("local://" .$file['path'])) {
		        $new = true;
		    } elseif ($this->manager->getTimestamp("$cloudProvider://" . $file['path']) > $this->manager->getTimestamp("local://" . $file['path'])) {
		        $update = true;
			} else {
				$this->output->display("file <info>" . $file['path'] . "</info> already synced");
		    }

		    if ($update) {
		        $this->manager->put("local://" . $file['path'], $this->manager->read("$cloudProvider://" . $file['path']));
		    	$this->output->display("file <info>" . $file['path'] . "</info> updated");
		    }

		    if ($new) {
		        $this->manager->copy("$cloudProvider://" . $file['path'], "local://" . $file['path']);
		    	$this->output->display("file <info>" . $file['path'] . "</info> copied");
		    }
		}
	}

	public function syncPush($cloudProviders = null)
	{
		$cloudProviders = is_null($cloudProviders) ? $this->cloudAdapters : $cloudProviders;
		$cloudProviders = is_array($cloudProviders) ? $cloudProviders : [$cloudProviders];

		$files = $this->disk('local')->files();

		foreach ($files as $file) {
			foreach ($cloudProviders as $provider) {
			    $update = false;
			    $new = false;

			    if ( ! $this->manager->has("$provider://" .$file['path'])) {
			        $new = true;
			    } elseif ($this->manager->getTimestamp("local://" . $file['path']) > $this->manager->getTimestamp("$provider://" . $file['path'])) {
			        $update = true;
			    } else {
					$this->output->display("file <info>" . $file['path'] . "</info> already synced");
				}

			    if ($update) {
			        $this->manager->put("$provider://" . $file['path'], $this->manager->read("local://" . $file['path']));
		    		$this->output->display("file <info>" . $file['path'] . "</info> updated");
			    }

			    if ($new) {
			        $this->manager->copy("local://" . $file['path'], "$provider://" . $file['path']);
		    	$this->output->display("file <info>" . $file['path'] . "</info> copied");
			    }
		    }
		}
	}

	public function has($filename)
	{
		return $this->manager->has($this->appendSelectedAdapterPrefix($filename));
	}

	public function files()
	{
		$files = $this->manager->listContents($this->appendSelectedAdapterPrefix());
		cleanFiles($files);
		return $files;
	}

	public function absFilePath($filename)
	{
		if($this->has($filename)) {
			return $this->getRealPathPrefix() . $filename;
		}
		return null;
	}

	public function write($filename, $contents)
	{
		$this->manager->write($this->appendSelectedAdapterPrefix($filename), $contents);
	}

	public function read($filename)
	{
		$content = $this->manager->read($this->appendSelectedAdapterPrefix($filename));
		return $content;
	}

	public function delete($filename)
	{
		if ($this->has($filename)) {
			$content = $this->manager->delete($this->appendSelectedAdapterPrefix($filename));
		}
	}

	protected function initLocalAdapter()
	{
		if ($this->is_absolute_path(LOCAL_STORAGE_PATH) === false) {
			$this->output->displayError("Storage path '" . LOCAL_STORAGE_PATH . "' MUST BE an ABSOLUTE PATH");
			die();
		}
		if (!is_null($errorPath = $this->is_a_file_in_path(LOCAL_STORAGE_PATH))) {

			$this->output->displayError("'$errorPath' MUST BE a FOLDER");
			die();
		}
		$local = new Filesystem(new Local(LOCAL_STORAGE_PATH, 0));
		$this->adapters['local'] = $local;
	}

	protected function initCloudAdapters()
	{
		$cloudProviders = $GLOBALS['CLOUD_PROVIDERS'];

		foreach ($cloudProviders as $provider => $adapters) {
			if($provider == 'dropbox') {
				foreach ($adapters as $adapterName => $adapterData) {
					$this->initDropboxAdapter($adapterName, $adapterData);
					RootCertificates::useExternalPaths();
				}
			}
		}
	}

	protected function initDropboxAdapter($adapterName, array $dropboxData)
	{
		$dropboxClient = new DropboxClient($dropboxData['access_token'], $dropboxData['app_secret']);
		$dropboxAdapter = new DropboxAdapter($dropboxClient, $dropboxData['path']);
		$dropbox = new Filesystem($dropboxAdapter);
		$this->adapters[$adapterName] = $dropbox;
		array_push($this->cloudAdapters, $adapterName);
	}

	protected function is_absolute_path($path) {
	    if($path === null || $path === '') throw new Exception("Empty path");
	    return $path[0] === DIRECTORY_SEPARATOR || preg_match('~\A[A-Z]:(?![^/\\\\])~i',$path) > 0;
	}

	protected function is_a_file_in_path($path)
	{
		$dirParts = explode(DIRECTORY_SEPARATOR, $path);
		$assemblePath = [];
		$current = '';
		foreach ($dirParts as $dir) {
			array_push($assemblePath, $dir);
			$current = implode(DIRECTORY_SEPARATOR, $assemblePath);
			if (file_exists($current) && is_file($current)) {
				return $current;
			}
		}
		return null;
	}

	protected function appendSelectedAdapterPrefix($file = null)
	{
		return $this->selectedAdapter . "://" . $file;
	}

	protected function appendAdapterPrefix($file = null, $adapter = 'local')
	{
		return $adapter . "://" . $file;
	}

	protected function getRealPathPrefix()
	{
		return $this->manager->getFilesystem($this->selectedAdapter)->getAdapter()->getPathPrefix();
	}
}