<?php

namespace Theme\APIs;

use Theme\Exceptions\CoteAtHomeException;
use Theme\Objects\GiftCard;
use GraphQL\Client;
use GraphQL\Exception\QueryError;
use GraphQL\Mutation;
use GraphQL\Query;
use GraphQL\Results;
use Illuminate\Support\Facades\Log;
use Lnk7\Genie\Options;

class Toggle
{

    /**
     * @var Client
     */
    static $toggle;

    static $error;



    /**
     *
     * Get a card
     *
     * @param string $card
     * @return false|mixed
     * @throws CoteAtHomeException
     */
    public static function getCard(string $card)
    {

        // get our Shopify Object
        $toggle = static::config();

        $gql = (new Query('Cards'))
            ->setArguments(['card_reference' => $card, 'limit' => 10, 'page' => 0])
            ->setSelectionSet([
                    (new Query('data'))
                        ->setSelectionSet(
                            [
                                'card_reference',
                                'balance',
                                'initial_balance',
                                'validity_start_time',
                                'expiry_time',
                                (new Query('Order'))
                                    ->setSelectionSet(
                                        [
                                            'id',
                                        ]),
                            ]),
                ]
            );


        try {
            $results = $toggle->runQuery($gql);
        } catch (QueryError $e) {
            throw CoteAtHomeException::withMessage($e->getMessage())
                ->withData([$e->getErrorDetails()]);
        }

        static::maybeSaveAuthorization($results);

        $data = $results->getData();

        if ($data->Cards->data && !empty($data->Cards->data)) {
            return $data->Cards->data[0];
        }
        return false;

    }



    /**
     * get an Order
     *
     * @param int $orderID
     * @return false|mixed
     * @throws CoteAtHomeException
     */
    public static function getOrder(int $orderID)
    {

        // get our Shopify Object
        $toggle = static::config();

        $gql = (new Query('Orders'))
            ->setArguments(['id' => $orderID, 'account_id' => 40, 'limit' => 10, 'page' => 0])
            ->setSelectionSet([
                    (new Query('data'))
                        ->setSelectionSet(
                            [
                                (new Query('ReceiptEmail'))->setSelectionSet(
                                    [
                                        'email',
                                    ]),
                                'receipt_name',
                                (new Query('LineItems'))->setSelectionSet(
                                    [
                                        'personalised_message',
                                        (new Query('Product'))->setSelectionSet(
                                            [
                                                'name',
                                                'short_description',
                                                'long_description',
                                                (new Query('Image'))->setSelectionSet(
                                                    [
                                                        'url',
                                                    ]),

                                            ]),
                                        (new Query('LineItemFulfilment'))->setSelectionSet(
                                            [
                                                (new Query('Email'))->setSelectionSet(
                                                    [
                                                        'email',
                                                    ]),
                                            ]),
                                        (new Query('Card'))->setSelectionSet(
                                            [

                                                'card_reference',
                                                'balance',
                                                'initial_balance',
                                                'validity_start_time',
                                                'expiry_time',
                                                'pin',
                                            ]),

                                    ]),

                            ]),
                ]
            );


        try {
            $results = $toggle->runQuery($gql);
        } catch (QueryError $e) {
            throw CoteAtHomeException::withMessage($e->getMessage())
                ->withData([$e->getErrorDetails()]);
        }

        static::maybeSaveAuthorization($results);

        $data = $results->getData();

        if ($data->Orders->data && !empty($data->Orders->data)) {
            return $data->Orders->data[0];
        }
        return false;
    }






    public static function login()
    {

        $username = TOGGLE_EMAIL; //config('toggle.email');
        $password = TOGGLE_PASSWORD; //config('toggle.password');
        $endpoint = TOGGLE_ENDPOINT; //config('toggle.endpoint');

        //dd($email,$password,$endpoint);

        $client = new Client(
            $endpoint,
        );

        $gql = (new Query('loginUser'))
            ->setArguments(['username' => $username, 'password' => $password])
            ->setSelectionSet(['id',]
            );


        try {
            $results = $client->runQuery($gql);
        } catch (QueryError $e) {

            throw CoteAtHomeException::withMessage($e->getMessage())
                ->withData([$e->getErrorDetails()]);
        }

        static::maybeSaveAuthorization($results);

    }



    /**
     * We need better checking.
     *
     * @param $card
     * @param $adjustment
     * @param $note
     * @return array|bool|object
     * @throws CoteAtHomeException
     */
    public static function maybeAdjustCard($card, $adjustment, $note)
    {

        $toggle = static::config();

        $mutation = (new Mutation('createBalanceAdjustment'))
            ->setArguments([
                'currency'       => 'GBP',
                'value'          => $adjustment * 100,
                'merchant_id'    => (int)TOGGLE_MERCHANT_ID,
                'card_reference' => $card,
                'note'           => $note,
            ])
            ->setSelectionSet([
                'id',
                'value',
            ]);

        try {
            $results = $toggle->runQuery($mutation);
        } catch (QueryError $e) {
            Log::error(static::class . '::maybeAdjustCard  - API error: ' . $e->getMessage(), $e->getErrorDetails());
            return false;
        }

        static::maybeSaveAuthorization($results);

        return $results->getData();

    }



    /**
     * if we received an Auth Header... stash it for use on the next call.
     *
     * @param Results $results
     */
    public static function maybeSaveAuthorization(Results $results)
    {

        $response = $results->getResponseObject();
        $auth = $response->getHeader('Authorization');

        if ($auth) {
            Options::set('toggle_auth', $auth[0]);
        }

    }



    /**
     * setup our toggle object if not set
     *
     * @return Client
     * @throws CoteAtHomeException
     */
    protected static function config()
    {


        if (!static::$toggle) {
            static::login();
            $toggleAuth = options::get('toggle_auth');
            $endpoint = TOGGLE_ENDPOINT;

            static::$toggle = new Client(
                $endpoint,
                ['Authorization' => 'Bearer ' . $toggleAuth]
            );

        }
        return static::$toggle;
    }

}
