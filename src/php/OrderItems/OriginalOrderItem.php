<?php

namespace Theme\OrderItems;

use Theme\Abstracts\OrderItem;

/**
 * Class OriginalOrderItem
 * @package CoteAtHome\Objects
 *
 * @property $orderData
 * @property $addedAt
 * @property $addedBy
 * @property $revertsAt
 *
 */
class OriginalOrderItem extends OrderItem
{

    public static $type = 'cah_original_order';

    protected $extra_data = [
        'orderData' => [],
        'addedAt'   => 0,
        'addedBy'   => 0,
        'revertsAt'  => 0,
    ];


}
