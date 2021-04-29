<?php

namespace Theme\Abstracts;

use WC_Order_Item;


/**
 * Class OrderItem
 * @package CoteAtHome\Abstracts
 *
 */
abstract class OrderItem extends WC_Order_Item
{

    /**
     * The type of this item
     *
     * @var string
     */
    public static $type = '';

    /**
     * The data for this item
     *
     * @var array
     */
    protected $extra_data = [];



    /**
     * Magic!
     *
     * @param $var
     * @return mixed|null
     */
    public function __get($var)
    {
        return $this->get_prop($var);
    }



    /**
     * magic
     *
     * @param $var
     * @param $value
     */
    public function __set($var, $value)
    {

        //if (in_array($var, array_keys($this->extra_data))) {
            $this->set_prop($var, $value);
       // }
    }



    /**
     * get the type of this OrderItem
     *
     * @return string
     */
    public function get_type()
    {
        return static::$type;
    }



    /**
     * Handy shit to make things quicker.
     *
     * @param array $array
     */
    public function fill(array $array) {

        foreach($array as $property => $value) {
            $this->$property = $value;
        }

    }

}
