<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Theme\Exceptions\CoteAtHomeException;
use Theme\Objects\Order;
use Theme\Theme;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Lnk7\Genie\Debug;
use Throwable;

class FutureEvents
{
    static $columns = [
        'Reminder At',
        'Order ID',
        'Reminder #',
        'Delivery Date',
    ];

    /**
     * @var Writer $writer
     */
    static $orderWriter;




    protected static function addReportHeader()
    {
        $row = [];
        foreach (static::$columns as $column) {
            $row[$column] = $column;
        }
        static::addReportRow($row);
    }



    protected static function addReportRow($row)
    {
        static::$orderWriter->insertOne($row);
    }


    public static function run()
    {

        $orderReportFolder = Theme::getCahDataFolder();

        $reportDate = Carbon::now()->format('Y-m-d_H-i-s');

        $eventFilename = "events_{$reportDate}.csv";
        $eventPathAndFileName = $orderReportFolder . $eventFilename;

        //load the CSV document from a string
        static::$orderWriter = Writer::createFromPath($eventPathAndFileName, "w");

        CharsetConverter::addTo(static::$orderWriter, 'utf-8', 'iso-8859-15');

        static::addReportHeader();

        $cron = _get_cron_array();

        foreach ($cron as $timestamp => $hooks) {

            $when = Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s');

            foreach ($hooks as $hook => $settings) {

                if ($hook === 'future_send_booking_slot_reminder') {

                    foreach ($settings as $key => $job) {

                        $orderID = $job['args']['order_id'];
                        $reminder = $job['args']['reminder'];

                        try {
                            $shippingData = Order::find($orderID)->get_shipping_item();
                        } catch (Throwable $e) {
                            continue;
                        }

                        static::addReportRow([
                            'Reminder At'=> $when,
                            'Order ID' => $orderID,
                            'Reminder #'=> $reminder,
                            'Delivery Date'=> $shippingData->date,
                        ]);

                    }

                }

            }

        }

    }

}
