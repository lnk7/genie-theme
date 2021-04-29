<?php

namespace Theme;

use Theme\Handlers\WordpressEmailHandler;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use studio24\Rotate\Rotate;
use Throwable;

class Log implements GenieComponent
{


    const fileName = 'cah.log';


    /**
     * @var Logger
     */
    private static $log;


    /**
     * Wordpress Hooks & Stuff
     */
    public static function setup()
    {

        // no need for a hook for this.. we really want to get it going
        static::$log = new Logger(COTE_DOMAIN);

        // Dump everything to our log
        static::$log->pushHandler(new StreamHandler(static::logPath() . Log::fileName, Logger::DEBUG));

        // Send the critical shit via email
        static::$log->pushHandler(new WordpressEmailHandler(Logger::ERROR));

        /**
         * Track all incoming API Data
         */
        HookInto::action('genie_received_api_request')
            ->run(function ($action, $data) {
                static::info('API data received from ' . $action, $data);
            });

        /**
         * Rotate our logs to keep them manageable
         */
        HookInto::action('cah_rotate_logs')
            ->run(function () {
                try {
                    $rotate = new Rotate(static::logPath() . Log::fileName);
                    $rotate->size("2MB");
                    $rotate->run();
                } catch (Throwable $e) {
                    Log::Error('Log rotate failed:' . $e->getMessage());
                }
            });
    }


    /**
     * Log an alert
     *
     * @param $message
     * @param array $data
     */
    public static function alert($message, $data = [])
    {
        static::$log->alert($message, $data);
    }


    /**
     * Log a critical message
     *
     * @param $message
     * @param array $data
     */
    public static function critical($message, $data = [])
    {
        static::$log->critical($message, $data);
    }


    /**
     * Debug message
     *
     * @param $message
     * @param array $data
     */
    public static function debug($message, $data = [])
    {
        static::$log->debug($message, $data);
    }


    /**
     * log an emergency message
     *
     * @param $message
     * @param array $data
     */
    public static function emergency($message, $data = [])
    {
        static::$log->emergency($message, $data);
    }


    /**
     * Log an error message
     *
     * @param $message
     * @param array $data
     */
    public static function error($message, $data = [])
    {
        static::$log->error($message, $data);

    }


    /**
     * Log Information message
     *
     * @param $message
     * @param array $data
     */
    public static function info($message, $data = [])
    {
        static::$log->info($message, $data);
    }


    /**
     * get the log path for the log file.
     *
     * @return string
     */
    public static function logPath()
    {
        return dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cah-logs/' . COTE_DOMAIN . '/';

    }


    /**
     * log a notice
     *
     * @param $message
     * @param array $data
     */
    public static function notice($message, $data = [])
    {
        static::$log->notice($message, $data);
    }


    /**
     * Log a warning
     *
     * @param $message
     * @param array $data
     */
    public static function warning($message, $data = [])
    {
        static::$log->warning($message, $data);
    }

}
