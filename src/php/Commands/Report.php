<?php

namespace Theme\Commands;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Theme\Reports\ComponentReport;
use Theme\Reports\FutureEvents;
use Theme\Reports\GiftCardReport;
use Theme\Reports\JosieProductReport;
use Theme\Reports\OrderReport;
use Theme\Reports\ProductReport;
use Theme\Reports\StatsReport;
use Theme\Utils\Time;
use Theme\WooCommerce;
use Lnk7\Genie\Options;
use WP_CLI;

class Report
{


    public function components($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args($assoc_args, ['ftp' => false]);

        ComponentReport::run($arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function futureEvents()
    {
        FutureEvents::run();
    }



    public function getLastRun()
    {
        $lastRun = Options::get('order_report_last_run_time', 0);

        $date = Carbon::createFromTimestampUTC($lastRun);

        WP_CLI::log($date->format('Y-m-d H:i:s'));
    }



    public function giftCards($args = [], $assoc_args = [])
    {
        // Parse our arguments
        $arguments = (object)wp_parse_args($assoc_args, ['ftp' => false]);

        GiftCardReport::run($arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function josie()
    {
        JosieProductReport::run();
        WP_CLI::log('Report Completed');
    }



    public function orderID($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args($assoc_args, ['id' => 0, 'ftp' => false]);

        if (!$arguments->id) {
            WP_CLI::error('Please specify an order ID');
        }
        OrderReport::orderID($arguments->id, $arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function orderIDsBetween($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'from'  => 0,
                'to'    => PHP_INT_MAX,
                'ftp'   => false,
                'delim' => null,
            ]
        );

        OrderReport::orderIDsBetween((int)$arguments->from, (int)$arguments->to, $arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function orderStatus($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args($assoc_args, ['status' => 'wc-processing', 'ftp' => false]);

        if (!$arguments->status) {
            WP_CLI::error('Please specify a status (wc-processing');
        }
        $statuses = WooCommerce::getOrderStatuses();
        if (!array_key_exists($arguments->status, $statuses)) {
            WP_CLI::error("{$arguments->status} in not a valid status. Valid options: " . implode(', ', array_keys($statuses)));
        }

        OrderReport::orderStatus($arguments->status, $arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function ordersBetween($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'from' => Time::now()->format('Y-m-d'),
                'to'   => Time::now()->format('Y-m-d'),
                'ftp'  => false,
            ]
        );

        OrderReport::ordersBetween($arguments->from, $arguments->to, $arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function ordersSQL($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'sql' => '',
                'ftp' => false,
            ]
        );

        if (!$arguments->sql) {

            WP_CLI::error("Please specify an sql command --sql=");
        }

        $sql = html_entity_decode($arguments->sql);

        OrderReport::do($sql, $arguments->ftp);

    }



    public function ordersSince($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args($assoc_args, ['from' => null, 'to' => null, 'ftp' => false]);

        OrderReport::runSince($arguments->from, $arguments->to, $arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function products($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args($assoc_args, ['ftp' => false]);

        ProductReport::run($arguments->ftp);
        WP_CLI::log('Report Completed');
    }



    public function setLastRun($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'time' => '',
            ]
        );

        if (!$arguments->time) {
            WP_CLI::error('Please specify a time (for example --time="2020-12-20 18:00:00") ');
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $arguments->time)->utc();
            Options::set('order_report_last_run_time', $date->getTimestamp());

            WP_CLI::success('lastRunRime set');

        } catch (InvalidFormatException $e) {
            WP_CLI::error('Sorry I didnt recognise that time. (try --time="2020-12-20 18:00:00") ');
        }

    }



    public function stats($args = [], $assoc_args = [])
    {

        $arguments = (object)wp_parse_args($assoc_args, ['days' => 30, 'email' => 1]);

        StatsReport::run($arguments->days, $arguments->email === 1);
        WP_CLI::log('Report Completed');
    }

}
