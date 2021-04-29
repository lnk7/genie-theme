<?php

namespace Theme;

use Theme\Exceptions\CoteAtHomeException;
use Theme\OrderItems\TransactionItem;
use Theme\Utils\Time;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;
use Throwable;
use WC_Order;

/**
 * Class SagePay
 *
 * @package CoteAtHome\WooCommerce
 * [
 *     'VPSProtocol'    => '3.00',
 *     'Status'         => 'OK',
 *     'StatusDetail'   => '0000 : The Authorisation was Successful.',
 *     'VPSTxId'        => '{E1F1DDC3-2140-B4E4-3DC8-6208C8CF4358}',
 *     'SecurityKey'    => 'FJFQST6Y4Z',
 *     'TxAuthNo'       => '7165116',
 *     'AVSCV2'         => 'SECURITY CODE MATCH ONLY',
 *     'AddressResult'  => 'NOTMATCHED',
 *     'PostCodeResult' => 'NOTMATCHED',
 *     'CV2Result'      => 'MATCHED',
 *     '3DSecureStatus' => 'NOTCHECKED',
 *     'DeclineCode'    => '00',
 *     'ExpiryDate'     => '0321',
 *     'BankAuthCode'   => '999777',
 * ];
 */
class SagePay implements GenieComponent
{


    public static function setup()
    {

        /**
         * When an order is complete, save the amount, so when an order is
         * changed we can request/refund the balance from the card.
         */
        HookInto::action('woocommerce_sagepay_direct_payment_complete')
            ->run(function ($result, WC_Order $order) {


                try {
                    if (isset($result['Status']) && strtolower($result['Status']) === 'ok') {

                        $status = explode(':', $result['StatusDetail']);

                        $status_code = '';
                        $status_detail = '';

                        if (!empty($status) && isset($status[0]) && isset($status[1])) {
                            $status_code = trim($status[0]);
                            $status_detail = trim($status[1]);
                        }

                        $transaction_id = isset($result['VPSTxId']) ? str_replace(['{', '}'], '', $result['VPSTxId']) : '';

                        if ($transaction_id) {

                            $transactionItems = $order->get_items(TransactionItem::$type);

                            /**
                             * @var TransactionItem $transactionItem
                             */
                            foreach ($transactionItems as $transactionItem) {
                                if ($transaction_id === $transactionItem->transaction_id) {
                                    throw CoteAtHomeException::withMessage("woocommerce_sagepay_direct_payment_complete: transaction_id: $transaction_id already processed");
                                }
                            }
                        }

                        $transaction = new TransactionItem();

                        $transaction->fill([
                            'order_id'                => $order->get_id(),
                            'transaction_id'          => $transaction_id,
                            'success'                 => true,
                            'amount'                  => $order->get_total(),
                            'balance'                 => $order->get_total(),
                            'amount_refunded'         => 0,
                            'status_code'             => $status_code,
                            'status_detail'           => $status_detail,
                            'transaction_type'        => 'CHARGE',
                            'bank_authorisation_code' => isset($result['BankAuthCode']) ? $result['BankAuthCode'] : 'Not found',
                            'related_transaction_ids' => [],
                            'date'                    => Time::utcNow()->format('Y-m-d H:i:s'),
                        ]);

                        $order->add_item($transaction);
                        $order->save();

                    }
                } catch (Throwable $e) {
                    Log::info("Error: woocommerce_sagepay_direct_payment_complete: order_id " . $e->getMessage(), $order->get_data());
                }

            });

    }

}
