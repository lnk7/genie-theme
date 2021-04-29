<?php

namespace Theme\OrderItems;

use Theme\Abstracts\OrderItem;

/**
 * Class GiftCardOrderItem
 * @package CoteAtHome\Objects
 *
 *
 * @property string $card_number
 * @property float $amount
 * @property bool $processed
 * @property float $amountProcessed
 * @property bool $removed
 */
class GiftCardItem extends OrderItem
{

    public static $type = 'cah_gift_card';

    protected $extra_data = [
        'card_number'     => '',
        'amount'          => 0,
        'processed'       => false,
        'amountProcessed' => 0,
        'removed'         => false,
    ];


}
