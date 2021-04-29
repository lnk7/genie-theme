<?php

namespace Theme\Parsers;

class UtmParser

{

    /**
     * Determine the source
     *
     * @param $url
     *
     * @return array
     */
    public static function parse($url)
    {
        $url = trim(strtolower($url));

        $return = [];

        $utmTags = ['utm_campaign', 'utm_source', 'utm_medium', 'utm_content', 'utm_term'];
        $parts = parse_url($url);
        if (!isset($parts['query'])) {
            return $return;
        }
        parse_str($parts['query'], $vars);

        foreach ($utmTags as $utm) {

            if (isset($vars[$utm])) {
                $return[$utm] = $vars[$utm];
            }
        }

        return $return;

    }

}
