<?php

namespace Theme\DataStores;

use Theme\Abstracts\DataStore;

/**
 * Class GiftCardDataStore
 *
 * @package CoteAtHome\WooCommerce
 *
 */
class GiftCardDataStore extends DataStore
{

    protected $internal_meta_keys = [
        'card_number',
        'amount',
        'processed',
        'amountProcessed',
        'removed'
    ];


}
