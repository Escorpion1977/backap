<?php

namespace Backap\Support;

use RecursiveFilterIterator;

class ExcludeDevFilterIterator extends RecursiveFilterIterator {

    public static $FILTERS = array(
        'backap.phar',
        '.backap.yaml',
        '.idea',
        '.git',
        'bin',
        '.gitignore',
        'composerr.json',
        'composerr.lock',
        'readme',
    );

    public function accept() {
        return !in_array(
            $this->current()->getFilename(),
            self::$FILTERS,
            true
        );
    }

}