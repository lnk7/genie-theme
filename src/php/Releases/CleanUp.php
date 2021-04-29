<?php

namespace Theme\Releases;

use Theme\Abstracts\Release;


/**
 * Class clean
 *
 * Clean up the post meta table. This runs on every deployment.
 *
 * @package CoteAtHome\Releases
 */
class CleanUp extends Release
{

    public static $runOnce = true;



    /**
     * perform some maintenance during deployment
     */
    public static function run()
    {
        global $wpdb;

        $wpdb->query("delete from $wpdb->postmeta where post_id not in (select ID from $wpdb->posts)");

        return "Database cleaned";

    }

}
