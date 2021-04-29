<?php

namespace Theme\Abstracts;

use Abstract_WC_Order_Item_Type_Data_Store;
use Exception;
use WC_Object_Data_Store_Interface;
use WC_Order_Item_Type_Data_Store_Interface;

/**
 * Class CoteShippingDataStore
 *
 * @package CoteAtHome\WooCommerce
 *
 */
abstract class DataStore extends Abstract_WC_Order_Item_Type_Data_Store implements WC_Object_Data_Store_Interface, WC_Order_Item_Type_Data_Store_Interface
{

    /**
     * Meta data keys
     *
     * @var array
     */
    protected $internal_meta_keys = [];



    /**
     * @param static $item
     * @throws Exception
     */
    public function read(&$item)
    {
        parent::read($item);

        foreach ($this->internal_meta_keys as $key) {
            $item->$key = get_metadata('order_item', $item->get_id(), $key, true);
        }
        $item->set_object_read(true);
    }



    /**
     * @param static $item
     */
    public function save_item_data(&$item)
    {
        foreach ($this->internal_meta_keys as $key) {
            update_metadata('order_item', $item->get_id(), $key, $item->$key);
        }
    }

}
