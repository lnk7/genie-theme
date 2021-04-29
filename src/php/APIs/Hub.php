<?php

namespace Theme\APIs;

use Lnk7\Genie\Utilities\ApiCall;

class Hub
{

    const DefaultEndpoint = 'https://hub.cote.co.uk/api/';





    /**
     * Fire a At Home review into Hub
     *
     * @param $data
     *
     * @return bool
     *
     */
    public static function createAtHomeReview($data)
    {

        $result = self::makeCall('at_home/reviews', $data, 'POST');

        if ($result->failed()) {
            return false;
        }

        return true;

    }



    /**
     * Fire a manual review into Hub
     *
     * @param $data
     *
     * @return bool
     *
     */
    public static function createReview($data)
    {

        $result = self::makeCall('reviews', $data, 'POST');

        if ($result->failed()) {
            return false;
        }

        return true;

    }



    /**
     *
     *
     * @param $order_id
     * @param $email
     * @param $code
     * @param $amount
     * @return ApiCall
     */
    public static function processOrderCoupon($order_id, $email, $code, $amount)
    {
        $data = [
            'order_id' => $order_id,
            'email'    => $email,
            'code'     => $code,
            'amount'   => $amount,
        ];

        return self::makeCall('at_home/process_order_coupon', $data, 'POST');

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
    protected static function makeCall($endpoint, $payload = null, $method = 'GET')
    {

        $url = static::DefaultEndpoint . $endpoint;

        $apiCall = ApiCall::to($url)
            ->usingMethod($method);
        if (!is_null($payload)) {
            $apiCall->withJson($payload);
        }

        return $apiCall->send();

    }

}
