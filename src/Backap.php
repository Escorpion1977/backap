<?php

namespace Backap;

class Backap
{
	static $version = '1.0.0';

	public static function getVersion()
	{
		return isPhar() ? '@package_version@' : self::$version;
	}
}