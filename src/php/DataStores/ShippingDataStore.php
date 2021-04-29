<?php

namespace Theme\DataStores;

use Theme\Abstracts\DataStore;

/**
 * Class ShippingDataStore
 *
 * @package CoteAtHome\WooCommerce
 *
 */
class ShippingDataStore extends DataStore
{

    protected $internal_meta_keys = [
        'amount',
        'date',
        'delivery_company_id',
        'delivery_area_id',
        'name',
        'code',
        'postcode',
        'delivery_note',
        'gift_message',
        'coupon',
    ];

}
