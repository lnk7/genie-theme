<?php

namespace Theme\Objects;

use Theme\DataStores\ShippingDataStore;
use Theme\OrderItems\ShippingItem;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;

/**
 * Class Shipping
 * @package CoteAtHome\Objects
 *
 */
class Shipping implements GenieComponent
{

    static $sessionKey = 'cah-shipping';


    /**
     * Setup our hooks, filters and AJAX calls
     */
    public static function setup()
    {


        /**
         * Create our own data store for Woo Commerce
         */
        HookInto::filter('woocommerce_data_stores')
            ->run(function ($stores) {
                if (!isset($stores['order-item-cah_shipping'])) {
                    $stores['order-item-cah_shipping'] = ShippingDataStore::class;
                }

                return $stores;

            });

        /**
         * The Item
         */
        HookInto::filter('woocommerce_get_items_key')
            ->run(function ($key, $item) {
                if ($item instanceof ShippingItem) {
                    return 'cah_shipping_lines';
                }

                return $key;
            });

        /**
         * Woo group Name
         */
        HookInto::filter('woocommerce_order_type_to_group')
            ->run(function ($groups) {
                $groups['cah_shipping'] = 'cah_shipping_lines';
                return $groups;
            });

        /**
         * Tell Woo what kind of class we have here.
         */
        HookInto::filter('woocommerce_get_order_item_classname')
            ->run(function ($classname, $item_type) {
                if ($item_type !== 'cah_shipping') {
                    return $classname;
                }
                return ShippingItem::class;

            });

    }


}
