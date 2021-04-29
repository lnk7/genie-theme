<?php

namespace Theme\Commands;

use Carbon\Carbon;
use Theme\Objects\Referral;
use League\Csv\Reader;
use League\Csv\Statement;
use WC_Coupon;
use WP_CLI;

class Referrals
{


    public function import()
    {

        set_time_limit(0);

        $file = 'referrals.csv';
        $cahData = dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cah-data';
        $filename = $cahData . DIRECTORY_SEPARATOR . $file;

        if (!file_exists($filename)) {
            WP_CLI::error('cannot find referrals.csv');
        }

        $csv = Reader::createFromPath($filename, 'r');
        $csv->setHeaderOffset(0); //set the CSV header offset

        $stmt = (new Statement())
            ->offset(0)
            ->limit(15000);

        $records = $stmt->process($csv);
        foreach ($records as $record) {
            $record = (object)$record;

            $created = new Carbon($record->created_at);

            $referral = Referral::getBySlug('hub_import_referral_' . $record->id);
            if (!$referral) {
                $referral = new Referral();
            }

            $referral->post_name = 'hub_import_referral_' . $record->id;
            $referral->from_first_name = '';
            $referral->from_email = $record->from_email;
            $referral->to_first_name = $record->to_first_name;
            $referral->to_email = $record->to_email;
            $referral->post_date = $created->format('Y-m-d H:i:s');

            if ($record->to_voucher !== 'NULL' && $record->to_expiry !== 'NULL') {
                $expiry = Carbon::createFromFormat('Y-m-d', $record->to_expiry);
                $coupon_id = wc_get_coupon_id_by_code($record->to_voucher);
                if (!$coupon_id) {
                    $toCoupon = new WC_Coupon();
                    $toCoupon->set_code($record->to_voucher);
                } else {
                    $toCoupon = new WC_Coupon($coupon_id);
                }
                $toCoupon->set_amount(10);
                $toCoupon->set_usage_limit(1);
                $toCoupon->set_usage_limit_per_user(1);
                $toCoupon->set_description("$record->from_email referred $record->to_email (Imported From Hub: $record->id)");
                $toCoupon->set_date_created($created->getTimestamp());
                $toCoupon->set_date_expires($expiry->getTimestamp());
//                if ($record->to_voucher_order_id !== 'NULL') {
//                 //   $toCoupon->set_usage_count(1);
//                }
                $toCoupon->save();
                $referral->to_coupon_id = $toCoupon->get_id();
            }

            if ($record->from_voucher !== 'NULL' && $record->from_expiry !== 'NULL') {

                $expiry = Carbon::createFromFormat('Y-m-d', $record->from_expiry);
                $coupon_id = wc_get_coupon_id_by_code($record->from_voucher);
                if (!$coupon_id) {
                    $fromCoupon = new WC_Coupon();
                    $fromCoupon->set_code($record->from_voucher);
                } else {
                    $fromCoupon = new WC_Coupon($coupon_id);
                }
                $fromCoupon->set_amount(10);
                $fromCoupon->set_usage_limit(1);
                $fromCoupon->set_usage_limit_per_user(1);
                $fromCoupon->set_description("$record->from_email received coupon after referral $record->to_email purchased (Imported From Hub: $record->id)");
                $fromCoupon->set_date_expires($expiry->getTimestamp());
                $fromCoupon->set_date_created($expiry->subDays(30)->getTimestamp());
                $fromCoupon->save();
                $referral->from_coupon_id = $fromCoupon->get_id();
            }

            if ($referral->to_coupon_id || $referral->from_coupon_id) {
                $referral->save();
            }

        }

        WP_CLI::log('Import Completed');
    }
}
