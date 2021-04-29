<?php

namespace Theme\Objects;

use Theme\DataStores\OriginalOrderDataStore;
use Theme\OrderItems\OriginalOrderItem;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;

/**
 * Class OriginalOrder
 * @package CoteAtHome\Objects
 *
 */
class OriginalOrder implements GenieComponent
{


    public static function setup()
    {


        /**
         * Create our own data store for Woo Commerce
         */
        HookInto::filter('woocommerce_data_stores')
            ->run(function ($stores) {
                $type = OriginalOrderItem::$type;
                if (!isset($stores['order-item-' . $type])) {
                    $stores['order-item-' . $type] = OriginalOrderDataStore::class;
                }

                return $stores;

            });

        /**
         * The Item
         */
        HookInto::filter('woocommerce_get_items_key')
            ->run(function ($key, $item) {
                if ($item instanceof OriginalOrderItem) {
                    return 'cah_original_order_lines';
                }

                return $key;
            });


        HookInto::filter('woocommerce_order_type_to_group')
            ->run(function ($groups) {
                $groups['cah_original_order'] = 'cah_original_order_lines';
                return $groups;
            });

        HookInto::filter('woocommerce_get_order_item_classname')
            ->run(function ($classname, $item_type) {
                if ($item_type !== 'cah_original_order') {
                    return $classname;
                }
                return OriginalOrderItem::class;

            });

    }

}
