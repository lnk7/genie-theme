<?php

namespace Theme\Tests;

use Theme\Objects\GiftCard;
use Theme\Objects\Order;
use Theme\Objects\Product;
use Theme\Theme;
use Theme\Utils\Number;
use Lnk7\Genie\Debug;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\RegisterApi;
use WP_CLI;

/**
 * Class Plugin
 * @package CoteAtHome
 */
class OrderEditorTests implements GenieComponent
{

    public static function setup()
    {

        if (Theme::inProduction()) {
            return;
        }

        RegisterApi::get('testing/numbers')
            ->run(function () {
                $numbers = [
                    1                   => 1.00,
                    "2.1"               => 2.10,
                    "1232.1454"         => 1232.15,
                    "1232.000125211545" => 1232.00,
                    "365.99"            => 365.99,
                    "365.994"           => 365.99,
                    "365.989"           => 365.99,
                    "365.982"           => 365.98,
                    "365.004"           => 365.00,
                    "365.006"           => 365.01,
                    "365.01"            => 365.01,
                ];

                foreach ($numbers as $val => $expected) {
                    if (Number::decimal($val) !== $expected) {
                        Debug::d([
                            $val,
                            Number::decimal($val),
                        ]);
                    }
                }


            });


        RegisterApi::get('testing/orders')
            ->run([static::class, 'orderTests']);

        RegisterApi::get('testing/get_products')
            ->run([Product::class, 'getProducts']);

        RegisterApi::get('testing/clear')
            ->run(function () {

                $card = GiftCard::getByCardNumber('111');
                $card->balance = 30;
                $card->activity = [];
                $card->save();

                Order::delete_all_orders();
            });

        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('test orders', [static::class, 'orderTests']);
        }


    }



    public static function orderTests()
    {

    }

}
