<?php

namespace Theme\Objects;

use Carbon\Carbon;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\View;
use WP_Post;

/**
 * Class Date
 *
 * @package CoteAtHome\Objects
 */
class Date extends CustomPost
{


    const deliveryDateDisabled = 'disabled';


    const deliveryDateAvailable = 'available';


    const deliveryDateFull = 'full';


    static $postType = 'date';


    public static function setup()
    {

        Parent::setup();

        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-calendar-alt')
            ->set('supports', false)
            ->backendOnly()
            ->set('capabilities', [
                'edit_post'          => 'shop_admin',
                'edit_posts'         => 'shop_admin',
                'edit_others_posts'  => 'shop_admin',
                'publish_posts'      => 'shop_admin',
                'read_post'          => 'shop_admin',
                'read_private_posts' => 'shop_admin',
                'delete_post'        => 'shop_admin',
            ])
            ->register();

        /**
         * Add our Meta Box
         */
        HookInto::action('add_meta_boxes')
            ->run(function (string $post_type, WP_Post $post) {

                if ($post_type !== static::$postType) {
                    return;
                }

                add_meta_box(
                    'delivery_date_id',
                    'Delivery Date',
                    function ($post) {

                        if ($post->post_status !== 'publish') {
                            return;
                        }

                        $deliveryDate = new static($post->ID);

                        View::with('admin/delivery_date/meta_box.twig')
                            ->addVar('deliveryDate', $deliveryDate)
                            ->addVar('deliveryCompanies', $deliveryDate->getOrderCountForDeliveryCompanies())
                            ->display();
                    },
                    static::$postType,
                    'normal',
                    'high'
                );
            });

        /**
         * Columns
         */
        HookInto::filter('manage_' . static::$postType . '_posts_columns')
            ->run(function () {

                $columns = [
                    'cb'    => '<input type="checkbox" />',
                    'title' => __('Title'),
                    'day'   => 'Day',

                ];

                $deliveryCompanies = DeliveryCompany::get();

                foreach ($deliveryCompanies as $deliveryCompany) {
                    $columns[$deliveryCompany->ID] = $deliveryCompany->post_title;
                }

                return $columns;

            });

        /**
         * Populate Columns
         */
        HookInto::action('manage_' . static::$postType . '_posts_custom_column')
            ->run(function ($column, $post_id) {

                $deliveryDate = new  static($post_id);
                $date = Carbon::createFromFormat('Y-m-d', $deliveryDate->post_title);

                switch ($column) {
                    case 'day' :
                        $return = $date->format('l');
                        break;
                    default :
                        $count = static::getOrderCount($date, $column);
                        $return = (int)$count > 0 ? $count : '';
                        break;
                }
                echo $return;
            });

    }


    /**
     * create the date;
     *
     * @param mixed $date // Y-m-d  format
     *
     * @return static
     */
    public static function firstOrCreate($date)
    {
        if ($date instanceof Carbon) {
            $date = $date->format('Y-m-d');
        }

        $deliveryDate = static::getByTitle($date);

        if (!$deliveryDate) {
            $deliveryDate = new static();
            $deliveryDate->post_title = $date;
            $deliveryDate->save();
        }

        return $deliveryDate;

    }


    /**
     * get the number of orders for this date and Area
     *
     * @param Carbon $date
     * @param int $delivery_company_id
     *
     * @return int
     */
    public static function getOrderCount(Carbon $date, int $delivery_company_id)
    {

        global $wpdb;
        $key = '_order_delivery_' . $date->format('Y-m-d') . '_' . $delivery_company_id;
        $sql = "select sum(meta_value) as count from $wpdb->postmeta  where meta_key = '$key'";

        return (int) $wpdb->get_var($sql);

    }


    /**
     * get the count for all delivery companies
     *
     * @return array
     */
    protected function getOrderCountForDeliveryCompanies()
    {
        $date = Carbon::createFromFormat('Y-m-d', $this->post_title);
        $deliveryCompanies = DeliveryCompany::get();
        $companies = [];
        foreach ($deliveryCompanies as $deliveryCompany) {
            $companies[] = [
                'company' => $deliveryCompany,
                'orders'  => static::getOrderCount($date, $deliveryCompany->ID),
            ];
        }

        return $companies;

    }

}
