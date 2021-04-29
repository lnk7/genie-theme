<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Theme\Theme;
use Theme\Utils\Number;
use Theme\Utils\Time;
use Lnk7\Genie\Utilities\SendEmail;

class StatsReport

{


    public static function run($days = 30, $email = true)
    {

        global $wpdb;

        set_time_limit(0);

        $data = [];

        $today = Time::utcNow();

        $startDate = Time::utcNow()->subDays($days);

        $period = CarbonPeriod::create($startDate, $today);

        $devices = [
            'Desktop',
            'Ebook Reader',
            'Mobile Device',
            'Mobile Phone',
            'Tablet',
        ];

        $sources = [
            'search',
            'social',
        ];
        $rowNumber = 1;

        $wpdb->query("UPDATE wp_shop_sessions SET stat_date = cart_created_date WHERE stat_date IS NULL AND processed = 1 AND ( crawler = 0 or crawler is null)");

        foreach ($period as $date) {

            $startOfDay = $date->startOfDay()->format('Y-m-d');

            $dateWhere = "stat_date = '$startOfDay'";
            $paidWhere = "status in ('Paid','Produced','Fulfilled')";

            $carts = $wpdb->get_var("select count(*) from wp_shop_sessions where  $dateWhere");
            if ((int)$carts === 0) {
                continue;
            }
            $addedToCart = $wpdb->get_var("select count(*) from wp_shop_sessions where added_to_cart = 1 and $dateWhere");
            $addedDetails = $wpdb->get_var("select count(*) from wp_shop_sessions where added_details = 1 and $dateWhere");
            $paid = $wpdb->get_var("select count(*) from wp_shop_sessions where paid = 1 and $dateWhere");

            $newCustomers = $wpdb->get_var("select count(*) from wp_shop_sessions where new_customer != 0 and $dateWhere and $paidWhere");
            $newCustomersTotal = $wpdb->get_var("select sum(total_items) +  sum(total_coupons) +sum(total_delivery)  from wp_shop_sessions where new_customer != 0 and $dateWhere and $paidWhere");

            $existingCustomers = $wpdb->get_var("select count(*) from wp_shop_sessions where new_customer = 0 and $dateWhere and $paidWhere");
            $existingCustomersTotal = $wpdb->get_var("select sum(total_items) + sum(total_coupons) +sum(total_delivery)  from wp_shop_sessions where new_customer = 0 and $dateWhere and $paidWhere");

            $itemsTotal = $wpdb->get_var("select sum(total_items) from wp_shop_sessions where $dateWhere and $paidWhere");
            $couponTotal = $wpdb->get_var("select sum(total_coupons)  from wp_shop_sessions where $dateWhere and $paidWhere");
            $deliveryTotal = $wpdb->get_var("select sum(total_delivery)  from wp_shop_sessions where $dateWhere and $paidWhere");
            $giftCardTotal = $wpdb->get_var("select sum(total_gift_cards) from wp_shop_sessions where  $dateWhere and $paidWhere");
            $paymentsTotal = $wpdb->get_var("select sum(total_payments) *-1 from wp_shop_sessions where $dateWhere and $paidWhere");

            $refunds = ($itemsTotal + $deliveryTotal + $couponTotal + $giftCardTotal + $paymentsTotal) * -1;

            $row = [
                'Date' => $date->format('d/m/y'),

                'Funnel Tracking'    => '',
                'Carts'              => $carts,
                'Added To Cart'      => $addedToCart,
                'Added To Cart CR %' => Number::percentage($addedToCart / $carts * 100),
                'Added Details'      => $addedDetails,
                'Added Details CR %' => Number::percentage($addedDetails / $addedToCart * 100),
                'Purchased'          => $paid,
                'Purchased CR %'     => Number::percentage($paid / $addedDetails * 100),
                'Total CR %'         => Number::percentage($paid / $carts * 100),

                'Sales'            => '',
                'Items Sold'       => Number::decimal($itemsTotal),
                'Delivery Charges' => Number::decimal($deliveryTotal),
                'Coupons Used'     => Number::decimal($couponTotal),
                'Total Sold'       => Number::decimal($itemsTotal + $deliveryTotal + $couponTotal),
                'Gift Cards Used'  => Number::decimal($giftCardTotal),
                'Refunds'          => Number::decimal($refunds),
                'CC Payments'      => Number::decimal($paymentsTotal),

                'Customer Orders'      => '',
                'New Customers Orders' => $newCustomers,
                'New Customers Sold'   => Number::decimal($newCustomersTotal),

                'Returning Customer Orders' => $existingCustomers,
                'Returning Customers Sold'  => Number::decimal($existingCustomersTotal),

            ];

            $row['Devices'] = '';
            foreach ($devices as $device) {
                $carts = $wpdb->get_var("select count(*) from wp_shop_sessions where $dateWhere and device_type = '$device'");
                $orders = $wpdb->get_var("select count(*) from wp_shop_sessions where $dateWhere and paid =1 and device_type = '$device'");
                $orderTotal = $wpdb->get_var("select sum( total_items + total_delivery + total_coupons ) as total from wp_shop_sessions where $dateWhere and device_type = '$device' and $paidWhere");
                $row[$device . ' Carts'] = $carts;
                $row[$device . ' Orders'] = $orders;
                $row[$device . ' CR %'] = $carts > 0 ? (Number::percentage($orders / $carts * 100)) : 0;
                $row[$device . ' Total'] = Number::decimal($orderTotal);
            }

            $row['Sources'] = '';
            foreach ($sources as $source) {
                $carts = $wpdb->get_var("select count(*) from wp_shop_sessions where $dateWhere and source = '$source'");
                $orders = $wpdb->get_var("select count(*) from wp_shop_sessions where $dateWhere and paid=1 and source = '$source'");
                $orderTotal = $wpdb->get_var("select sum( total_items + total_delivery + total_coupons ) as total from wp_shop_sessions where  $dateWhere and source = '$source' and $paidWhere");
                $row[ucfirst($source) . ' Carts'] = $carts;
                $row[ucfirst($source) . ' Orders'] = $orders;
                $row[ucfirst($source) . ' CR %'] = $carts > 0 ? (Number::percentage($orders / $carts * 100)) : 0;
                $row[ucfirst($source) . ' Total'] = Number::decimal($orderTotal);
            }

            $data[$rowNumber] = $row;
            $rowNumber++;
        }

        $finished = [];
        $finished[] = array_keys($data[1]);
        foreach ($data as $row) {
            $finished[] = array_values($row);
        }

        $data = array_map(null, ...$finished);

        $file = '';
        foreach ($data as $row) {
            $file .= '"' . implode('","', $row) . '"' . "\n";
        }

        $orderReportFolder = Theme::getCahDataFolder();
        $reportDate = Carbon::now()->format('Y-m-d_H-i-s');
        $eventFilename = "stats_{$reportDate}.csv";
        $eventPathAndFileName = $orderReportFolder . $eventFilename;
        file_put_contents($eventPathAndFileName, $file);

        if ($email) {
            $to = defined('REPORT_STATS_EMAILS') ? REPORT_STATS_EMAILS : 'sunil@cote.co.uk';
            SendEmail::to($to)
                ->subject('[CAH STATS] ' . Time::now()->format('jS F Y'))
                ->body("Please find the last $days days stats attached")
                ->addAttachment($eventPathAndFileName)
                ->send();

            unlink($eventPathAndFileName);
        }

    }

}
