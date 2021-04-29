<?php

namespace Theme\Objects;

use Theme\Utils\Time;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Utilities\CreateCustomPostType;

class Snapshot extends CustomPost
{

    static $postType = 'snapshot';



    static public function setup()
    {

        CreateCustomPostType::Called(static::$postType, 'Delivery Area', 'Delivery Areas')
            ->backendOnly()
            ->hidden()
            ->register();

    }



    public static function order($order_id)
    {

        if (!defined('COTE_USE_SNAPSHOTS') || COTE_USE_SNAPSHOTS === false) {
            return;
        }

        if ($order_id == 0) {
            return;
        }

        $order = new Order($order_id);
        $data = json_encode($order->get_order_data(), JSON_PRETTY_PRINT);
        $post_title = 'order:' . $order_id;
        $key = 'snapshot:order:' . $order_id . '@' . Time::utcNow()->format('Y-m-d H:i:s') . ':' . microtime(true);

        $snapshot = static::getByTitle($post_title);
        if (!$snapshot) {
            $snapshot = new static();
            $snapshot->post_title = $post_title;
            $snapshot->save();
        }

        Update_post_meta($snapshot->ID, $key, $data);

    }

}
