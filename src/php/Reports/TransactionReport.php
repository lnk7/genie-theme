<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Theme\Finance;
use Theme\Theme;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use Lnk7\Genie\Utilities\SendEmail;

class TransactionReport
{


    static $blankRow = [

        'Date'           => '',
        'Order ID'       => '',
        'Gross payments' => '',
        'Refunds'        => '',
        'Net payments'   => '',

    ];



    public static function run($period)
    {

        global $wpdb;

        $from = Carbon::createFromFormat('Y-m-d', Finance::$periodEnds[$period]['start'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', Finance::$periodEnds[$period]['end'])->endOfDay();

        set_time_limit(0);

        $report = [];

        $giftCardFolder = Theme::getCahDataFolder();
        $reportDate = Carbon::now()->format('Y-m-d_H-i-s');

        $filename = "transaction_{$reportDate}.csv";
        $pathAndFileName = $giftCardFolder . $filename;
        $writer = Writer::createFromPath($pathAndFileName, "w");
        CharsetConverter::addTo($writer, 'utf-8', 'iso-8859-15');

        $row = static::$blankRow;
        foreach (static::$blankRow as $column => $data) {
            $row[$column] = $column;
        }

        $report[] = $row;

        $fromFormat = $from->format('Y-m-d H:i:s');
        $toFormat = $to->format('Y-m-d H:i:s');

        $sql = "
            SELECT 
                OI.order_item_id,
                OI.order_id
            FROM
                wp_woocommerce_order_items OI,
                wp_woocommerce_order_itemmeta OIM
            WHERE
                order_item_type = 'cah_transaction'
                and 
                OI.order_item_id = OIM.order_item_id
                and
                OIM.meta_key = 'date'
                and
                str_to_date(OIM.meta_value, '%Y-%m-%d %H:%i:%s') > '$fromFormat'
                and  
                str_to_date(OIM.meta_value, '%Y-%m-%d %H:%i:%s') < '$toFormat'
        ";

        $results = $wpdb->get_results($sql);


        foreach ($results as $result) {

            $metaResults = $wpdb->get_results("select * from wp_woocommerce_order_itemmeta where order_item_id = {$result->order_item_id}");

            $meta = (object)[];

            foreach ($metaResults as $metaResult) {
                $meta->{$metaResult->meta_key} = $metaResult->meta_value;
            }

            $gross = $meta->transaction_type !== 'Refund' ? $meta->amount : 0;
            $net = $meta->transaction_type === 'Refund' ? $meta->amount * -1 : 0;

            $report[] = [
                'Date'           => Carbon::createFromFormat('Y-m-d H:i:s', $meta->date)->format('d/m/Y'),
                'Order ID'       => $result->order_id,
                'Gross payments' => $meta->transaction_type !== 'Refund' ? $meta->amount : 0,
                'Refunds'        => $meta->transaction_type === 'Refund' ? $meta->amount : 0,
                'Net payments'   => $gross - $net,
            ];
        }

        $writer->insertAll($report);


        $fromFormat = $from->format('d/m/Y');
        $toFormat = $to->format('d/m/Y');

        $to = defined('REPORT_TRANSACTION_EMAILS') ? REPORT_TRANSACTION_EMAILS : 'sunil@cote.co.uk';

        SendEmail::to($to)
            ->subject("[CAH TRANSACTIONS] $period ")
            ->body("Please find the transaction report for the period $period ($fromFormat - $toFormat)")
            ->addAttachment($pathAndFileName)
            ->send();

        unlink($pathAndFileName);


    }

}
