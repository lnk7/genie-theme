<?php

namespace Theme;

use Carbon\Carbon;
use Theme\APIs\Exponea;
use Theme\Objects\Product;
use Theme\Parsers\RefererParser;
use Theme\Reports\GiftCardReport;
use Theme\Reports\OrderReport;
use Theme\Reports\ProductReport;
use Theme\Reports\StatsReport;
use Theme\Reports\TransactionReport;
use Theme\Utils\Time;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Options;
use Lnk7\Genie\Utilities\HookInto;

class Scheduler implements GenieComponent
{


    public static function setup()
    {

        HookInto::action('init', 30)
            ->run(function () {

                // Only if we're running cron
                if (!wp_doing_cron()) {
                    return;
                }

                if (!wp_next_scheduled('cah_hourly')) {
                    wp_schedule_single_event(Time::now()->endOfHour()->getTimestamp(), 'cah_hourly');
                }
                if (!wp_next_scheduled('cah_daily')) {
                    wp_schedule_single_event(Time::now()->endOfDay()->subMinutes(15)->getTimestamp(), 'cah_daily');
                }

                if (!wp_next_scheduled('cah_monthly')) {
                    wp_schedule_single_event(Time::now()->endOfMonth()->getTimestamp(), 'cah_monthly');
                }

                if (!wp_next_scheduled('cah_dw_load')) {

                    $next = Time::now()->setMinute(0)->setSecond(0);
                    $hour = (int)$next->format('H');

                    if ($hour < 2) {
                        $next->setHour(2);
                    } else if ($hour < 8) {
                        $next->setHour(8);
                    } else if ($hour < 15) {
                        $next->setHour(15);
                    } else if ($hour < 19) {
                        $next->setHour(19);
                    } else {
                        $next->addDay()->setHour(2);
                    }

                    wp_schedule_single_event($next->getTimestamp(), 'cah_dw_load');
                }

                if (!wp_next_scheduled('cah_dw_load_next_2_days')) {

                    $next = Time::now()->setMinute(0)->setSecond(0);
                    $hour = (int)$next->format('H');

                    if ($hour < 5) {
                        $next->setHour(5);
                    } else if ($hour < 12) {
                        $next->setHour(12);
                    } else if ($hour < 17) {
                        $next->setHour(17);
                    } else {
                        $next->addDay()->setHour(5);
                    }

                    wp_schedule_single_event($next->getTimestamp(), 'cah_dw_load_next_2_days');
                }


                if (!wp_next_scheduled('cah_dw_load_days_3_to_5')) {


                    $next = Time::now()->setMinute(0)->setSecond(0);
                    $hour = (int)$next->format('H');

                    if ($hour < 21) {
                        $next->setHour(21);
                    } else {
                        $next->addDay()->setHour(21);
                    }

                    wp_schedule_single_event($next->getTimestamp(), 'cah_dw_load_days_3_to_5');
                }


                if (!wp_next_scheduled('cah_dw_load_days_6_to_10')) {

                    $next = Time::now()->setMinute(0)->setSecond(0);
                    $hour = (int)$next->format('H');

                    if ($hour < 23) {
                        $next->setHour(23);
                    } else {
                        $next->addDay()->setHour(23);
                    }

                    wp_schedule_single_event($next->getTimestamp(), 'cah_dw_load_days_6_to_10');
                }


                if (!wp_next_scheduled('cah_rotate_logs')) {
                    wp_schedule_single_event(Time::now()->addDay()->getTimestamp(), 'cah_rotate_logs');
                }

                if (!wp_next_scheduled('cah_send_stat_report')) {
                    wp_schedule_single_event(Time::now()->next(Carbon::MONDAY)->setTimeFromTimeString('8:00 am')->getTimestamp(), 'cah_send_stat_report');
                }

                if (!wp_next_scheduled('cah_check_exponea_lag')) {
                    wp_schedule_single_event(Time::now()->addMinutes(15)->setSecond(0)->getTimestamp(), 'cah_check_exponea_lag');
                }

                if (!wp_next_scheduled('cah_sync_products')) {
                    wp_schedule_single_event(Time::now()->endOfDay()->subHour()->getTimestamp(), 'cah_sync_products');
                }

            });


        HookInto::action('cah_dw_load_next_2_days')
            ->run(function () {

                if (Theme::inDevelopment()) {
                    return;
                }

                ProductReport::run();
                $from = Carbon::today()->addDays(1)->format('Y-m-d');
                $to = Carbon::today()->addDays(2)->format('Y-m-d');
                OrderReport::ordersBetween($from, $to);
            });


        HookInto::action('cah_dw_load_days_3_to_5')
            ->run(function () {

                if (Theme::inDevelopment()) {
                    return;
                }

                ProductReport::run();
                $from = Carbon::today()->addDays(3)->format('Y-m-d');
                $to = Carbon::today()->addDays(5)->format('Y-m-d');
                OrderReport::ordersBetween($from, $to);
            });


        HookInto::action('cah_dw_load_days_6_to_10')
            ->run(function () {

                if (Theme::inDevelopment()) {
                    return;
                }

                ProductReport::run();
                $from = Carbon::today()->addDays(6)->format('Y-m-d');
                $to = Carbon::today()->addDays(10)->format('Y-m-d');
                OrderReport::ordersBetween($from, $to);
            });


        HookInto::action('cah_dw_load')
            ->run(function () {

                if (Theme::inDevelopment()) {
                    return;
                }

                $lastRun = (int)Options::get('_ORDER_REPORT_TRACKER', 0);

                Log::info('Attempting cah_dw_load, last Ran at ' . Carbon::createFromTimestamp($lastRun)->format('Y-m-d H:i:s'));

                if ($lastRun > Time::now()->subHour()->getTimestamp()) {

                    Log::info('Aborting cah_dw_load  - last ran within the hour');
                    return;
                }

                Options::set('_ORDER_REPORT_TRACKER', Time::now()->getTimestamp());
                Log::info('Running cah_dw_load');

                if (Theme::inDevelopment()) {
                    return;
                }

                ProductReport::run();
                OrderReport::runSince();
                GiftCardReport::run(true);
            });

        HookInto::action('cah_send_stat_report')
            ->run(function () {
                StatsReport::run();
            });

        HookInto::action('cah_check_exponea_lag')
            ->run(function () {

                if (Theme::inDevelopment()) {
                    return;
                }

                Exponea::track([
                    'customer_ids' => [
                        'registered' => 'sunil@cote.co.uk',
                    ],
                    'event_type'   => 'lag_check',
                    'timestamp'    => Time::utcTimestamp(),
                    'properties'   => [
                        'sent' => Time::utcTimestamp(),
                    ],
                ]);


            });

        HookInto::action('cah_hourly')
            ->run(function () {
                if (Theme::inDevelopment()) {
                    return;
                }
                Monitoring::selfCheck();
                WooCommerce::revertEditedOrders();
                WooCommerce::processAbandonedCarts();
                WooCommerce::deleteOldCartsWithNoCustomerOrOrderData();
                WooCommerce::processShopSessions();
                WooCommerce::abandonedCartCleanup();
            });


        HookInto::action('cah_sync_products')
            ->run(function () {
                Product::syncAllProductsWithExponea();
            });


        HookInto::action('cah_daily')
            ->run(function () {

                WooCommerce::startOrderProcessing();
                Monitoring::selfCheck();
                Product::syncAllProductsWithExponea();

                // Make sure we have all events scheduled
                foreach (Finance::$periodEnds as $period => $dates) {

                    $when = Carbon::createFromFormat('Y-m-d', $dates['end'])->addDays(2)->setTimeFromTimeString('8:00 am');
                    if ($when->isAfter(Time::now())) {
                        if (!wp_next_scheduled('future_period_end', ['period' => $period])) {
                            wp_schedule_single_event($when->getTimestamp(), 'future_period_end', ['period' => $period]);
                        }
                    }
                }


            });

        HookInto::action('cah_monthly')
            ->run(function () {
                RefererParser::load();
                Browscap::importBrowscapFile();
            });

        HookInto::action('future_period_end')
            ->run(function ($period) {

                if (!isset(Finance::$periodEnds[$period])) {
                    return;
                }
                TransactionReport::run($period);
            });

    }
}
