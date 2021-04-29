<?php

namespace Theme\APIs;

use Lnk7\Genie\Debug;
use Lnk7\Genie\Utilities\ApiCall;

class Exponea
{


    private static $customerAttributes = [
        ["type" => "property", "property" => "first_name"],
        ["type" => "property", "property" => "last_name"],
        ["type" => "property", "property" => "birth_date"],
        ["type" => "property", "property" => "phone"],
        ["type" => "property", "property" => "email"],
        ["type" => "property", "property" => "title"],
        ["type" => "property", "property" => "gender"],
        ["type" => "property", "property" => "vegetarian"],
        ["type" => "property", "property" => "childrens_menu"],
        ["type" => "property", "property" => "gluten_free"],
        ["type" => "property", "property" => "postcode"],
        ["type" => "property", "property" => "referred_by"],
        ["type" => "property", "property" => "favourite_restaurant"],
        ["type" => "property", "property" => "favourite_merchant_id"],
        ["type" => "property", "property" => "receive_review_emails"],
        ["type" => "property", "property" => "newsletter_sign_up"],
        ["type" => "property", "property" => "stripe_customer_id"],
        ["type" => "property", "property" => "stripe_payment_method_id"],
        ["type" => "property", "property" => "emergency_services"],
        ["type" => "property", "property" => "pa_scheme"],
        ["type" => "property", "property" => "cote_at_home"],
    ];



    public static function createCatalogItem($catalog, $item, $properties)
    {
        $payload = ['properties' => $properties];

        $url = static::getDataEndpoint() . static::getToken() . "/catalogs/{$catalog}/items/{$item}";

        $call = self::makeCall($url, $payload, 'PUT');

        if ($call->failed()) {
            return $call->getResponseBody();
        }

        return $call->getResponseBody();
    }



    /**
     * get an Item from a Catalog
     *
     * @param $catalog
     * @param $item
     *
     * @return bool
     */
    public static function getCatalogItem($catalog, $item)
    {

        $url = static::getDataEndpoint() . static::getToken() . "/catalogs/{$catalog}/items/{$item}";

        $call = self::makeCall($url);

        if ($call->failed()) {
            return false;
        }

        $data = $call->getResponseBody();
        if (!$data->success) {
            return false;
        }

        return $data->data;
    }



    public static function getCatalogItems($catalog)
    {

        $url = static::getDataEndpoint() . static::getToken() . "/catalogs/{$catalog}/items";

        $call = self::makeCall($url);

        if ($call->failed()) {
            return false;
        }

        return $call->getResponseBody();
    }



    /**
     * get a customer by registered $email
     *
     * @param $email
     *
     * @return bool|object
     */
    public static function getCustomer($email)
    {

        $payload = (object)[
            "customer_ids" => (object)["registered" => $email],
            "attributes"   => static::$customerAttributes,

        ];

        return static::getAttributes($payload);

    }



    public static function sendEmail()
    {
        $url = static::getEmailEndpoint() . static::getToken() . "/sync";
        //Debug::dd($url);
        $file = 'invoice.pdf';
        $cahData = dirname(ABSPATH) . DIRECTORY_SEPARATOR . 'cah-data';
        $filename = $cahData . DIRECTORY_SEPARATOR . $file;


        $payload = (object)[
            "email_content"     => [
                "template_id"    => "5fb3e1d7b7b87c3d2d840273",
                //"subject"        => 'test from woo',
                "sender_address" => 'bonjour@email.cote.co.uk',
                "sender_name"    => 'Cote At Home (woo)',
                "params"         => [
                    "order_id" => 1234567,
                ],
                //                'attachments' => [
                //                    [
                //                        'filename' => 'invoice.pdf',
                //                        'content'  => base64_encode(file_get_contents($filename)),
                //                        'content_type' => 'application/pdf'
                //                    ]
                //                ]
            ],
            "campaign_name"     => 'TEST CAMPAIGN',
            "recipient"         => [
                'email'        => 'sunil@cote.co.uk',
                "customer_ids" => (object)[
                    "registered" => 'sunil@cote.co.uk',
                ],
            ],
            "transfer_identity" => "disabled",
        ];
        return self::makeCall($url, $payload, 'POST');
    }



    public static function track($payload)
    {
        $url = static::getTrackEndpoint() . static::getToken() . "/customers/events";

        return self::makeCall($url, $payload, 'POST');

    }



    public static function update($payload)
    {

        $url = static::getTrackEndpoint() . static::getToken() . "/customers";

        return self::makeCall($url, $payload, 'POST');

    }



    public static function updateCatalogItem($catalog, $item, $properties, $upsert = true)
    {
        $payload = ['properties' => $properties, 'upsert' => $upsert];

        $url = static::getDataEndpoint() . static::getToken() . "/catalogs/{$catalog}/items/{$item}/partial-update";

        $call = self::makeCall($url, $payload, 'POST');

        if ($call->failed()) {
            return false;
        }

        return $call->getResponseBody();
    }



    protected static function getAttributes($payload)
    {

        $url = static::getDataEndpoint() . static::getToken() . '/customers/attributes';

        $call = self::makeCall($url, $payload, 'POST');

        if ($call->failed()) {
            return false;
        }

        $customer = (object)[];
        $data = $call->getResponseBody();
        foreach (static::$customerAttributes as $index => $attribute) {
            $customer->{$attribute['property']} = $data->results[$index]->value;
        }

        return $customer;

    }



    protected static function getDataEndpoint()
    {
        return EXPONEA_DATA_ENDPOINT;
    }



    protected static function getEmailEndpoint()
    {
        return EXPONEA_EMAIL_ENDPOINT;
    }



    protected static function getPrivateKey()
    {
        return EXPONEA_PRIVATE_KEY;

    }



    protected static function getPublicKey()
    {
        return EXPONEA_PUBLIC_KEY;
    }



    protected static function getToken()
    {
        return EXPONEA_TOKEN;
    }



    protected static function getTrackEndpoint()
    {
        return EXPONEA_TRACK_ENDPOINT;
    }



    /**
     * Do the API Call.
     *
     * @param $url
     * @param $payload
     * @param string $method
     *
     * @return ApiCall
     */
    protected static function makeCall($url, $payload = null, $method = 'GET')
    {

        $apiCall = ApiCall::to($url)
            ->addHeader("Authorization", "Basic " . base64_encode(static::getPublicKey() . ':' . static::getPrivateKey()))
            ->addHeader("Content-Type", "application/json")
            ->usingMethod($method);
        if (!is_null($payload)) {
            $apiCall->withJson($payload);
        }

        return $apiCall->send();
    }
}
