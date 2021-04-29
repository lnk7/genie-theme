<?php

namespace Theme\APIs;

use Theme\Exceptions\CoteAtHomeException;
use Theme\Log;
use Lnk7\Genie\Config;
use Lnk7\Genie\Utilities\ApiCall;

class SagePay
{

    /**
     * @param $transactionID
     * @param $amount
     * @param $description
     *
     * @return ApiCall
     */
    public static function charge($orderID, $transactionID, $amount)
    {

        $vendorTxCode = uniqid('ch_') . '-' . $orderID . '-' . uniqid();

        $data = [
            'transactionType'        => 'Repeat',
            'referenceTransactionId' => $transactionID,
            'vendorTxCode'           => $vendorTxCode,
            'amount'                 => round($amount * 100, 0),
            'currency'               => 'GBP',
            'description'            => 'Order ' . $orderID,
            'credentialType'         =>
                [
                    'cofUsage'      => 'Subsequent',
                    'initiatedType' => 'MIT',
                    'mitType'       => 'Unscheduled',
                ],
        ];
        Log::info(static::class . '::charge data', $data);
        $result = static::makeCall("transactions", $data, 'POST');
        Log::info(static::class . '::charge result ' . print_r($result->getResponseBody(), true));
        return $result;

    }



    /**
     * @param $orderID
     * @param $transactionID
     * @param $amount
     * @return ApiCall
     *
     * @throws CoteAtHomeException
     */
    public static function refund($orderID, $transactionID, $amount)
    {

        // pass in order ID

        $vendorTxCode = uniqid('rf_') . '-' . $orderID . '-' . uniqid();

        $data = [
            'transactionType'        => 'Refund',
            'referenceTransactionId' => $transactionID,
            'vendorTxCode'           => $vendorTxCode,
            'amount'                 => round($amount * 100, 0),
            'description'            => 'Order ' . $orderID,
        ];
        Log::info(static::class . '::refund data', $data);
        $result = static::makeCall("transactions", $data, 'POST');
        if ($result->failed()) {
            Log::error(static::class . '::refund failed', (array)$result);
            throw CoteAtHomeException::withMessage('Refund Failed');
        }
        Log::info(static::class . '::refund', (array)$result->getResponseBody());
        return $result;

    }



    /**
     * Do the API Call.
     *
     * @param $endpoint
     * @param $payload
     * @param string $method
     *
     * @return ApiCall
     */
    protected static function makeCall($endpoint, $payload, $method = 'GET')
    {


        $options = get_option('woocommerce_sagepaydirect_settings');
        $sageApiEndpoint = Config::get('SAGEPAY_ENDPOINT_' . strtoupper($options['status']));
        $sageApiKey = Config::get('SAGEPAY_KEY_' . strtoupper($options['status']));

        $url = $sageApiEndpoint . $endpoint;

        return ApiCall::to($url)
            ->usingMethod($method)
            ->addHeader('Authorization', 'Basic ' . $sageApiKey)
            ->withJson($payload)
            ->send();

    }

}
