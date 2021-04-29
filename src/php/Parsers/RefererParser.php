<?php

namespace Theme\Parsers;

use Theme\Log;
use Theme\Theme;
use Lnk7\Genie\Options;

class RefererParser

{


    /**
     * Load the referrer database - This happens once a month
     */
    public static function load()
    {

        set_time_limit(0);
        $file = 'https://s3-eu-west-1.amazonaws.com/snowplow-hosted-assets/third-party/referer-parser/referers-latest.json';

        $headers = get_headers($file);
        $statusCode = substr($headers[0], 9, 3);
        if ($statusCode != "200") {
            Log::error(static::class . "::load: File $file not found. Aborting");
            return;
        }

        $json = json_decode(file_get_contents($file));

        $filename = Theme::getCahDataFolder(). 'referers-latest.json';
        file_put_contents($filename, $file );

        $data = [];
        $p1s = ['twitter', 't.co', 'facebook', '.uk'];

        foreach ($json as $source => $sourceData) {
            foreach ($sourceData as $name => $settings) {
                $params = isset($settings->parameters) ? $settings->parameters : [];
                foreach ($settings->domains as $domain) {
                    $priority = 2;
                    foreach ($p1s as $p1) {
                        if (strpos($domain, $p1) !== false) {
                            $priority = 1;
                            break;
                        }
                    }

                    $slashed = trim(strtolower(addcslashes($domain, './')));

                    $data[] = [
                        'regex'          => "/^[^:]+:\/\/{$slashed}/",
                        'source'         => $source,
                        'source_name'    => $name,
                        'source_domain'  => $domain,
                        'regex_priority' => $priority + 2,
                    ];

                    if (!empty($params)) {
                        foreach ($params as $param) {

                            $param = trim(strtolower($param));
                            $data[] = [

                                'regex'          => "/^[^:]+:\/\/{$slashed}\/.*[?|&]{$param}\=/",
                                'source'         => $source,
                                'source_name'    => $name,
                                'source_domain'  => $domain,
                                'param'          => $param,
                                'regex_priority' => $priority,
                            ];
                        }
                    }
                }
            }
        }

        usort($data, function ($a, $b) {
            return $a['regex_priority'] - $b['regex_priority'];
        });

        Options::set('referrers', $data);
    }


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
        $referrers = Options::get('referrers');
        if (empty($referrers)) {
            Log::debug(static::class . '::parse: $referrers is empty');
            return $return;
        }

        $found = false;
        foreach ($referrers as $referrer) {
            if (preg_match($referrer['regex'], $url)) {
                $found = $referrer;
                break;
            }
        }

        if (!$found) {
            return $return;
        }

        $return['source'] = $found['source'];
        $return['source_name'] = $found['source_name'];
        $return['source_domain'] = $found['source_domain'];

        $found['search term'] = '';
        if ($found['param']) {
            $parts = parse_url($url);
            parse_str($parts['query'], $vars);
            $return['search_term'] = $vars[$found['param']];
        }

        return $return;

    }

}
