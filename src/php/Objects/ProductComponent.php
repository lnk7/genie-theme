<?php

namespace Theme\Objects;

use Theme\Log;
use Theme\Utils\Str;
use Theme\Utils\Time;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\RelationshipField;
use Lnk7\Genie\Fields\RepeaterField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\Where;
use Throwable;
use WC_Product;

/**
 * Class Redirection
 *
 * @package Cote\PostTypes
 * @property array $product_id
 * @property array $components
 * @property bool $ignore_for_picklist
 * @property string $process_orders
 */
class ProductComponent extends CustomPost
{


    static $postType = 'product-component';


    public static function setup()
    {

        Parent::setup();

        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-update')
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

        CreateSchema::Called('ProductComponents')
            ->instructionPlacement('field')
            ->style('seamless')
            ->withFields([

                RelationshipField::called('product_id')
                    ->label('Product')
                    ->postObject(['product', 'product_variation'])
                    ->filters(['search', 'taxonomy'])
                    ->elements(['featured_image'])
                    ->wrapperWidth(50)
                    ->required(true)
                    ->max(1)
                    ->returnFormat('id')
                    ->addFilter('acf/fields/relationship/result', function ($text, $post, $field, $post_id) {
                        $productTitle =  $post->post_title;
                        if($post->post_type == "product_variation"){
                            $variation = wc_get_product($post->ID);
                            $productTitle =  $post->post_title.' '.$variation->get_attribute_summary();
                        }
                        return $productTitle;
                    })
                    ->addFilter('acf/validate_value/key={$key}', function ($valid, $value, $field, $input) {

                        if (!$valid || !isset($_POST['post_ID'])) {
                            return $valid;
                        }
                        $post_id = (int)$_POST['post_ID'];

                        $posts = static::get([
                            'meta_query'  => [
                                [
                                    'key'     => 'product_id',
                                    'value'   => '"' . $value[0] . '"',
                                    'compare' => 'LIKE',
                                ],
                            ],
                            'post__not_in' => [$post_id],
                        ]);

                        if ($posts->count() > 0) {
                            return 'You already have a product component with this product: <a href="'.home_url("/wp-admin/post.php?post=".$posts->first()->ID).'&action=edit">Click here</a>';
                        }
                        return $valid;
                    }),
                RepeaterField::called('components')
                    ->wrapperWidth(50)
                    ->withFields([
                        PostObjectField::called('product_id')
                            ->label('Product')
                            ->postObject(['product'])
                            ->returnFormat('id')
                            ->required(true),
                        NumberField::called('quantity')
                            ->default(1)
                            ->min(1)
                            ->max(1000)
                            ->required(true),

                    ]),

                TrueFalseField::called('ignore_for_picklist')
                    ->label("Ignore on Picklist?")
                    ->message("Yes, use the main product rather than the components on the pick list.")
                    ->wrapperWidth(25)
                    ->default(0),

                SelectField::called('process_orders')
                    ->label("Process Changed Orders")
                    ->choices([
                        'now' => 'Yes, Now',
                        'delay' => 'Yes, in 5 minutes',
                        'no' => 'No need to process orders'
                    ])
                    ->returnFormat('value')
                    ->wrapperWidth(25)
                    ->default('delay'),

            ])
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->attachTo(static::class)
            ->register();

        HookInto::action('acf/save_post', 30)
            ->run(function ($post_id) {
                global $post;

                if (!$post or $post->post_type != static::$postType) {
                    return;
                }

                $productComponent = new static($post_id);
                $product_id = $productComponent->getProductID();
                //Remove anything that's currently Scheduled
                $args = ['product_id' => $product_id];
                wp_clear_scheduled_hook('future_find_and_update_upcoming_orders', $args);

                switch($productComponent->process_orders) {
                    case 'no' :
                        break;
                    case 'delay' :
                        wp_schedule_single_event(Time::utcNow()->addMinutes(5)->getTimestamp(), 'future_find_and_update_upcoming_orders', $args);
                        break;
                    case 'now' :
                        static::findAndChangeOrders($product_id);
                        break;
                }
                $productComponent->process_orders = 'delay';
                $productComponent->save();

            });

        HookInto::action('future_find_and_update_upcoming_orders')
            ->run([static::class, 'findAndChangeOrders']);

    }


    /**
     * $product_id could be a variation or main product.
     *
     * @param $product_id
     */
    public static function findAndChangeOrders($product_id)
    {
        global $wpdb;


        set_time_limit(0);

        Log::info("future_find_and_update_upcoming_orders: Start: $product_id ");

        $sql = "
            select 
                distinct order_id  
            from 
                 wp_woocommerce_order_items
            where
                  order_item_id in (select order_item_id from wp_woocommerce_order_itemmeta where (meta_key = '_product_id'  and meta_value = $product_id ) or (meta_key = '_variation_id'  and meta_value = $product_id ) )
                  and 
                  order_id in (select ID from wp_posts where post_status in ('wc-processing','wc-cart','wc-pending'))
        ";

        //Find all orders with the product and flag them for inclusion in the order report
        $results = $wpdb->get_results($sql);
        Log::info("future_find_and_update_upcoming_orders: SQL: $sql ");
        Log::info("future_find_and_update_upcoming_orders: found {$wpdb->num_rows} orders");

        foreach ($results as $result) {
            // Update Orders
            try {
                Log::info("future_find_and_update_upcoming_orders: Processing Order: {$result->order_id} ");

                $order = Order::find($result->order_id);

                $productItems = $order->get_product_items();
                foreach ($productItems as $productItem) {
                    if ((int)$productItem->get_product_id() === $product_id || (int)$productItem->get_variation_id() === $product_id) {

                        $meta = Product::getMeta($product_id);
                        foreach ($meta as $key => $value) {
                            $productItem->update_meta_data($key, $value);
                        }
                        $productItem->save();
                        $order->add_item($productItem);
                        $order->save();
                    }
                }
            } catch (Throwable $e) {
                Log::info("future_find_and_update_upcoming_orders: " . $e->getMessage());
            }
        }
        Log::info("future_find_and_update_upcoming_orders: end: $product_id ");
    }


    public static function getAsArray()
    {

        $components = static::get();
        $data = [];
        foreach ($components as $component) {
            $data[$component->product_id[0]] = $component->components;
        }

        return $data;

    }


    /**
     * @param $product_id
     *
     * @return false|static
     */
    public static function getByProductID($product_id)
    {

        $productComponents = static::get([
            'numberposts' => 1,
            'meta_query'  => [
                [
                    'key'     => 'product_id',
                    'value'   => '"' . $product_id . '"',
                    'compare' => 'LIKE',
                ],
            ],
        ]);

        if (!empty($productComponents)) {
            return $productComponents->first();
        }

        return false;

    }


    public static function updateTitles()
    {

        $productComponents = static::get();
        /**
         * @var ProductComponent $productComponent
         */
        foreach ($productComponents as $productComponent) {
            $productComponent->save();
        }
    }


    /**
     * Before Save
     */
    public function beforeSave()
    {
        $this->updateTitle();
    }


    /**
     * get the product for the ProductComponent
     *
     * @return false|WC_Product|null
     */
    public function getProduct()
    {
        return wc_get_product($this->getProductID());
    }


    /**
     * get the main product ID.
     *
     * @return mixed
     */
    public function getProductID()
    {
        return $this->product_id[0];
    }


    /**
     * Update the title for this product Component.
     */
    public function updateTitle()
    {
        $product_id = $this->getProductID();
        $product = $this->getProduct();
        if (!$product) {
            $this->post_title = Str::maybePrepend('[Product Not Found] ', $this->post_title);
            return;
        }
        $ids = $product->get_parent_id() ? $product->get_parent_id() . '/' . $product_id : $product_id;

        $variationText='';

        if($product->post_type == "product_variation"){
            $variationText = $product->get_attribute_summary();
        }

        $title = $product->get_name() .' '.$variationText.' (' . $ids . ')';
        $this->post_title = $title;
    }
}
