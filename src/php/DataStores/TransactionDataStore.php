<?php

namespace Theme\DataStores;

use Theme\Abstracts\DataStore;

/**
 * Class TransactionDataStore
 *
 * @package CoteAtHome\WooCommerce
 *
 */
class TransactionDataStore extends DataStore
{

    protected $internal_meta_keys = [

        'transaction_id',
        'success',
        'balance',
        'amount',
        'amount_refunded',
        'status_code',
        'status_details',
        'transaction_type',
        'retrieval_reference',
        'bank_authorisation_code',
        'related_transaction_ids',
        'description',
        'date',
    ];

}
