<?php

namespace Theme\Objects;

use Theme\DataStores\TransactionDataStore;
use Theme\OrderItems\TransactionItem;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;

/**
 * Class Transaction
 * @package CoteAtHome\PostTypes
 */
class Transaction implements GenieComponent
{


    /**
     * Wordpress init hook
     */
    static public function setup()
    {

        /**
         * Create our own data store for WooCommerce
         */
        HookInto::filter('woocommerce_data_stores')
            ->run(function ($stores) {
                $type = TransactionItem::$type;
                if (!isset($stores['order-item-' . $type])) {
                    $stores['order-item-' . $type] = TransactionDataStore::class;
                }

                return $stores;

            });

        /**
         * The Item
         */
        HookInto::filter('woocommerce_get_items_key')
            ->run(function ($key, $item) {
                if ($item instanceof TransactionItem) {
                    return 'cah_transactions';
                }

                return $key;
            });


        HookInto::filter('woocommerce_order_type_to_group')
            ->run(function ($groups) {
                $groups[TransactionItem::$type] = 'cah_transactions';
                return $groups;
            });

        HookInto::filter('woocommerce_get_order_item_classname')
            ->run(function ($classname, $item_type) {
                if ($item_type !== TransactionItem::$type) {
                    return $classname;
                }
                return TransactionItem::class;
            });


    }




}
