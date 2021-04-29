<?php

namespace Theme\APIs;

use Lnk7\Genie\Utilities\ApiCall;

class Postcode
{


    /**
     * Do the API Call.
     *
     * @param $postcode
     *
     * @return ApiCall
     */
    public static function get($postcode)
    {

        $url = 'https://api.postcodes.io/postcodes/' . $postcode;

        return ApiCall::to($url)
            ->usingMethod('GET')
            ->send();

    }

    public static function getFallbackAPI($postcode)
    {

        $url = ' https://api.getAddress.io/find/' . $postcode . '?api-key='.GET_ADDRESS_API_KEY;

        return ApiCall::to($url)
            ->usingMethod('GET')
            ->send();

    }

}
