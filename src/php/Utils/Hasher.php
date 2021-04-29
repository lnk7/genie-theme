<?php

namespace Theme\Utils;

use Hashids\Hashids;

class Hasher
{


    /**
     * The alphabet to use when hashing strings.
     */
    const alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * The salt used for encoding and decoding
     */
    const salt = 'Cote At Home';



    /**
     * Decode a has back into it's ID
     *
     * @param $hash
     * @param int $length
     * @return false|mixed
     */
    public static function decode($hash, $length = 8)
    {
        $hashids = new Hashids(static::salt, $length, static::alphabet);
        $array = $hashids->decode($hash);
        if (empty($array)) {
            return false;
        }
        return $array[0];

    }



    /**
     * Turn an ID into it's hash
     *
     * @param $id
     * @param int $length
     * @return string
     */
    public static function encode($id, $length = 8)
    {
        $hashids = new Hashids(static::salt, $length, static::alphabet);
        return $hashids->encode($id);

    }


}
