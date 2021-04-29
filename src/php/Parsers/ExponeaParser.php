<?php

namespace Theme\Parsers;

use Carbon\Carbon;
use Theme\APIs\Exponea;

class ExponeaParser

{


    /**
     * Determine the source
     *
     * @param $email
     *
     * @return array
     */
    public static function parse(string $email)
    {

        $data = Exponea::getCustomer($email);

        $return = [];

        if ($data && $data->birth_date) {
            $date = Carbon::createFromTimestamp($data->birth_date)->format('Y-m-d');
            $return['birth_date'] = $date;
        }

        return $return;

    }

}
