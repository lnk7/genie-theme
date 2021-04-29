<?php


namespace Theme\Releases;


class Clean_DW_Load extends \Theme\Abstracts\Release
{

    public static $runOnce = true;



    /**
     * perform some maintenance during deployment
     */
    public static function run()
    {
        global $wpdb;

        $clean = $wpdb->query("DELETE FROM wp_postmeta WHERE meta_key = '_include_in_next_dw_load' limit 150000");

        return "next_dw_load meta deleted";

    }
}
