<?php

namespace Theme;

use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;
use WP_Query;


class Search implements GenieComponent
{

    public static function setup()
    {

        HookInto::action('pre_get_posts')
            ->run(function (WP_Query $query) {

                if ($query->is_search && !is_admin()) {
                    $query->set('post_type', ['product']);

                }

                return $query;

            });

    }

}
