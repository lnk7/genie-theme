<?php

namespace Theme\Abstracts;

/**
 * Class Release
 *
 * Abstract class for database releases
 *
 * @package Cote\Abstracts
 */
abstract class Release
{

    /**
     * Run this only once?  if set to false this runs on every release.
     *
     * @var bool
     */
    static $runOnce = true;



    /**
     * function to run when released
     */
    abstract public static function run();

}
