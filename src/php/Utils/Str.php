<?php

namespace Theme\Utils;

class Str
{


    public static function maybePrepend($prepend, $text)
    {

        if (strpos($text, $prepend) === 0) {
            return $text;
        }

        return $prepend . $text;
    }

}
