<?php

namespace Theme\Parsers;

class UserAgentParser

{


    /**
     * Determine the source
     *
     * @param $userAgent
     *
     * @return array
     */
    public static function parse(string $userAgent)
    {

        $data = get_browser($userAgent);
        $grab = ['device_name', 'device_type', 'browser', 'platform', 'crawler'];

        $return = [];

        foreach ($grab as $attribute) {

            if (isset($data->$attribute)) {
                $return[$attribute] = $data->$attribute;
            }
        }

        return $return;

    }

}
