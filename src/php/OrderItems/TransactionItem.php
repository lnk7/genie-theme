<?php

namespace Theme\OrderItems;

use Theme\Abstracts\OrderItem;

/**
 * Class TransactionItem
 * @package CoteAtHome\Objects
 *
 * @property $type
 * @property string $transaction_id
 * @property bool $success
 * @property float $balance
 * @property float $amount
 * @property float $amount_refunded
 * @property string $status_code
 * @property string $status_detail
 * @property string $transaction_type
 * @property string $retrieval_reference
 * @property string $bank_authorisation_code
 * @property array $related_transaction_ids
 * @property string $description
 * @property string $date
 *
 */
class TransactionItem extends OrderItem
{

    static $type = 'cah_transaction';

    protected $extra_data = [
        'type'                    => '',  // manual|automatic
        'transaction_id'          => 0,
        'success'                 => '',
        'balance'                 => 0,
        'amount'                  => 0,
        'amount_refunded'         => '',
        'status_code'             => '',
        'status_details'          => '',
        'transaction_type'        => '',
        'retrieval_reference'     => '',
        'bank_authorisation_code' => '',
        'related_transaction_ids' => [],
        'description'             => '',
        'date'                    => '',
    ];



}
