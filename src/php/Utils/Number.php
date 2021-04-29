<?php

namespace Theme\Utils;

class Number
{


    /**
     * return a currency string
     *
     * @param $number
     *
     * @return string
     */
    public static function currency($number, $decimals = 2)
    {
        return '£' . number_format(self::decimal($number), $decimals);
    }


    /**
     * @param mixed $number
     * @param int $decimals
     *
     * @return float
     */
    public static function decimal($number, $decimals = 2)
    {

        $number = is_string($number) ? trim($number) : $number;
        $val = floatval($number);
        return round($val, $decimals);
    }


    /**
     * @param mixed $number
     *
     * @return int
     */
    public static function integer($number)
    {
        return (int)self::decimal($number, 0);
    }


    /**
     * @param mixed $number
     * @param int $decimals
     *
     * @return float
     */
    public static function percentage($number, $decimals = 2)
    {

        $number = is_string($number) ? trim($number) : $number;
        $val = floatval($number);
        return round($val, $decimals) . '%';
    }


    public static function zero() : float
    {
        return self::decimal(0);
    }

}
