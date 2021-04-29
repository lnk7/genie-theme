<?php

namespace Theme\Releases;

use Theme\Abstracts\Release;
use Theme\Objects\Order;
use Lnk7\Genie\Debug;


class FixComponents extends Release
{

    public static $runOnce = true;



    /**
     * perform some maintenance during deployment
     */
    public static function run()
    {
        global $wpdb;

        $orders = $wpdb->get_results("select ID from wp_posts where post_type = 'shop_order' and post_modified_gmt > '2020-12-22 08:00:00'  and post_modified_gmt < '2020-12-22 20:00:00' ");

        foreach ($orders as $order) {

            print "{$order->ID} \n";

            Order::find($order->ID)->update_components();
        }

        return "Orders Checked";

    }

}
