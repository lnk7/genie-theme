<?php

namespace Theme\OrderItems;

use Theme\Abstracts\OrderItem;

/**
 * Class ShippingOrderItem
 * @package CoteAtHome\Objects
 *
 * @property $amount
 * @property $date
 * @property $delivery_company_id
 * @property $delivery_area_id
 * @property $postcode
 * @property $name
 * @property $code
 * @property $delivery_note
 * @property $gift_message
 *
 *
 */
class ShippingItem extends OrderItem
{

    public static $type = 'cah_shipping';

    protected $extra_data = [
        'amount'              => 0,
        'date'                => '',
        'delivery_company_id' => 0,
        'delivery_area_id'    => 0,
        'name'                => '',
        'code'                => '',
        'postcode'            => '',
        'coupon'              => '',
        'delivery_note'       => '',
        'gift_message'        => '',
    ];


}
