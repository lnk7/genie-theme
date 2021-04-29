<?php

namespace Theme\DataStores;

use Theme\Abstracts\DataStore;

/**
 * Class OriginalOrderDataStore
 *
 * @package CoteAtHome\WooCommerce
 *
 */
class OriginalOrderDataStore extends DataStore
{

    protected $internal_meta_keys = [
        'orderData',
        'addedAt',
        'addedBy',
        'revertsAt',
    ];

}
