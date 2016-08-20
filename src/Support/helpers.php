<?php

if (! function_exists('assembleDbEnvVarName')) {
    function assembleDbEnvVarName(array $parts)
    {
    	array_walk($parts, function (&$item, $key) {
		    $item = "[$item]";
		});

        return '[connections]' . implode('', $parts);
    }
}

if (! function_exists('assembleYamlVarName')) {
    function assembleYamlVarName(array $parts)
    {
    	array_walk($parts, function (&$item, $key) {
		    $item = "[$item]";
		});

        return implode('', $parts);
    }
}

if (! function_exists('makeRedableConfigArray')) {
    function makeRedableConfigArray(array $parts, $prefix = '')
    {
    	array_walk($parts, function (&$item, $key) {
		    $item = "[$item]";
		});

        return $prefix . implode('', $parts);
    }
}

if (! function_exists('getValueByPath')) {
	function getValueByPath($arr, $path) {
	    $keys = explode('.', $path);
	    foreach ($keys as $key) {
	        $arr = $arr[$key];
	    }
	    return $arr;
	}
}

if (! function_exists('setValueByPath')) {
	function setValueByPath(&$arr, $path, $value) {
	    $keys = explode('.', $path);
	    foreach ($keys as $key) {
	        $arr = &$arr[$key];
	    }
	    $arr = $value;
	}
}

if (! function_exists('cleanFiles')) {
    function cleanFiles(&$files) {
        foreach ($files as $key => $file) {
            if ( !($file['type'] == 'file' & ($file['extension'] == 'gz' || $file['extension'] == 'sql')) ) {
                unset($files[$key]);
            }
        }
    }
}

if (! function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) { 
        $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1); 
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow]; 
    }
}

if (! function_exists('isPhar')) {
    function isPhar() { 
        return starts_with(__DIR__, 'phar://');
    }
}