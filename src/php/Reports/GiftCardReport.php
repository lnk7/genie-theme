<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Theme\Objects\GiftCard;
use Theme\Objects\Order;
use Theme\Theme;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Filesystem;
use Throwable;
use WP_CLI;

class GiftCardReport
{


    static $blankRow = [
        'Card Number'          => '',
        'Type'                 => '',
        'Balance'              => '',
        'Expiry Date'          => '',
        'Bought Order ID'      => '',
        'Bought Delivery Date' => '',
        'Used Order ID'        => '',
        'Used Delivery Date'   => '',
    ];


    public static function run($ftp = false)
    {
        $report = [];

        global $wpdb;

        $giftCardFolder = Theme::getCahDataFolder();

        $reportDate = Carbon::now()->format('Y-m-d_H-i-s');

        $giftCardFilename = "giftcards_{$reportDate}.csv";
        $giftCardPathAndFileName = $giftCardFolder . $giftCardFilename;
        $writer = Writer::createFromPath($giftCardPathAndFileName, "w");
        CharsetConverter::addTo($writer, 'utf-8', 'iso-8859-15');

        $row = static::$blankRow;
        foreach (static::$blankRow as $column => $data) {
            $row[$column] = $column;
        }
        $report[] = $row;

        $results = $wpdb->get_results("
            SELECT 
                ID,
                post_title,
                post_type 
            FROM
                wp_posts 
            WHERE 
                  (
                      (post_title LIKE 'BS-%' AND post_type = 'shop_coupon') 
                      OR
                      (post_title LIKE 'GC-BS-%' AND post_type = 'gift-card')
                  )
                  AND
                  post_status = 'publish'
        ");

        foreach ($results as $result) {

            $row = static::$blankRow;

            $row['Card Number'] = $result->post_title;

            try {

                if ($result->post_type === 'shop_coupon') {
                    $boughtWithOrderId = get_field('order_id', $result->ID);
                    $usedOnOrderID = get_field('used_order_id', $result->ID);
                    $row['Type'] = 'slot';
                    $row['Balance'] = $usedOnOrderID ? 0 : 10;

                    $row['Bought Order ID'] = $boughtWithOrderId;

                    try {
                        $order = Order::find($boughtWithOrderId);
                    } catch (Throwable $e) {
                        continue;

                    }
                    $shippingData = $order->get_shipping_item();
                    if (!$shippingData) {
                        $row['Bought Delivery Date'] = "Can't find Shipping data";
                        continue;
                    }
                    $row['Bought Delivery Date'] = $shippingData->date;
                    $row['Expiry Date'] = $shippingData->date;
                    if ($usedOnOrderID) {
                        $order = Order::find($usedOnOrderID);
                        $shippingData = $order->get_shipping_item();
                        $row['Used Order ID'] = $usedOnOrderID;
                        $row['Used Delivery Date'] = $shippingData->date;
                    }
                } else {
                    $card = new GiftCard($result->ID);
                    $row['Type'] = 'slot';
                    $row['Balance'] = $card->used_order_id ? 0 : 10;

                    $row['Bought Order ID'] = $card->order_id;

                    try {
                        $order = Order::find($card->order_id);
                    } catch (Throwable $e) {
                        continue;
                    }

                    $shippingData = $order->get_shipping_item();
                    if (!$shippingData) {
                        $row['Bought Delivery Date'] = "Can't find Shipping data";
                        continue;
                    }

                    $row['Bought Delivery Date'] = $shippingData->date;
                    $row['Expiry Date'] = $shippingData->date;
                    if ($card->used_order_id) {
                        $order = Order::find($card->used_order_id);
                        $shippingData = $order->get_shipping_item();
                        $row['Used Order ID'] = $card->used_order_id;
                        $row['Used Delivery Date'] = $shippingData->date;
                    }
                }

                $report[] = $row;
            } catch (Throwable $e) {
                if (defined('WP_CLI')) {
                    WP_CLI::log($e->getMessage());
                }
                continue;
            }

        }

        $writer->insertAll($report);

        if ($ftp && Theme::inProduction() && defined('FTP_HOST') && defined('COTE_FTP_REPORTS') && COTE_FTP_REPORTS) {

            $filesystem = new Filesystem(new Ftp([
                'host'     => FTP_HOST,
                'port'     => FTP_PORT,
                'username' => FTP_USER,
                'password' => FTP_PASSWORD,
                'passive'  => true,
                'ssl'      => true,
                'root'     => '/',
                'timeout'  => 10,
            ]));

            $filesystem->put($giftCardFilename, file_get_contents($giftCardPathAndFileName)); // upload file

            // Remove the file so we don't clutter the data directory
            unlink($giftCardFilename);

        }

    }

}
