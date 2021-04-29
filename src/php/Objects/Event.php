<?php

namespace Theme\Objects;

use Theme\Log;
use Theme\Utils\Hasher;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\DateField;
use Lnk7\Genie\Fields\EmailField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TaxonomyField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\When;
use Lnk7\Genie\Utilities\Where;
use Lnk7\Genie\View;
use Throwable;
use WC_Product_Simple;
use WP_Post;

/**
 * Class Event
 * @package CoteAtHome\Objects
 *
 * @property string $category_id
 * @property string $code
 * @property float $price
 * @property string $expiry_date
 * @property string $link
 * @property int $limit
 * @property int $delivery_company_id
 * @property array $order_ids
 * @property int $maximum_products
 * @property int $product_to_add_id
 * @property bool $create_product_to_add
 * @property bool $confirmed
 * @property int $event_order_id
 * @property bool $create_event_order
 * @property int $event_product_id
 * @property bool $create_event_product
 * @property bool $keep_in_sync
 *
 * @property string $company
 * @property string $expected_date
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property string $address_1
 * @property string $address_2
 * @property string $city
 * @property string $state
 * @property string $postcode
 * @property string $invoice_link
 */
class Event extends CustomPost
{

    static $postType = 'event';



    /**
     * Wordpress init hook
     */
    static public function setup()
    {

        parent::setup();


        // Change expiry to delivery and force on checkout
        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-buddicons-community')
            ->backendOnly()
            ->set('capabilities', [
                'edit_post'          => 'shop_user_plus',
                'edit_posts'         => 'shop_user_plus',
                'edit_others_posts'  => 'shop_user_plus',
                'publish_posts'      => 'shop_user_plus',
                'read_post'          => 'shop_user_plus',
                'read_private_posts' => 'shop_user_plus',
                'delete_post'        => 'shop_user_plus',
            ])
            ->removeSupportFor(['editor'])
            ->register();

        CreateSchema::Called('Event')
            ->instructionPlacement('field')
            ->withFields([

                TabField::called('tab_details')
                    ->label('Details'),

                NumberField::called('limit')
                    ->label('Number of Orders')
                    ->wrapperWidth(50)
                    ->required(true),

                DateField::called('expected_date')
                    ->label('Expected Date')
                    ->returnFormat('Y-m-d')
                    ->wrapperWidth(50),

                TextAreaField::called('details')
                    ->rows(8)
                    ->wrapperWidth(50),

                TextAreaField::called('notes')
                    ->rows(8)
                    ->wrapperWidth(50),

                TextAreaField::called('marketing')
                    ->rows(4)
                    ->wrapperWidth(50),

                TextAreaField::called('other')
                    ->rows(4)
                    ->wrapperWidth(50),


                TabField::called('tab_billing')
                    ->label('Billing Address'),


                TextField::called('company')
                    ->wrapperWidth(100),
                TextField::called('first_name')
                    ->wrapperWidth(50),
                TextField::called('last_name')
                    ->wrapperWidth(50),
                EmailField::called('email')
                    ->wrapperWidth(50),
                TextField::called('phone')
                    ->wrapperWidth(50),
                TextField::called('address_1')
                    ->wrapperWidth(50),
                TextField::called('address_2')
                    ->wrapperWidth(50),
                TextField::called('city')
                    ->wrapperWidth(50),
                TextField::called('state')
                    ->wrapperWidth(50),
                TextField::called('postcode')
                    ->wrapperWidth(50),


                // Event and Product

                TabField::called('tab_event_and_product')
                    ->label('Order / Product'),

                NumberField::called('event_order_id')
                    ->label('The event order ID')
                    ->wrapperWidth(25),
                TrueFalseField::called('create_event_order')
                    ->label('Create an order if one is not specified')
                    ->shown(When::field('confirmed')->equals(0))
                    ->wrapperWidth(25)
                    ->default(1),

                PostObjectField::called('event_product_id')
                    ->label('The event product')
                    ->postObject('product')
                    ->returnFormat('id')
                    ->wrapperWidth(25),
                TrueFalseField::called('create_event_product')
                    ->label('Create a product if one is not specified')
                    ->shown(When::field('confirmed')->equals(0))
                    ->wrapperWidth(25)
                    ->default(1),

                TrueFalseField::called('keep_in_sync')
                    ->label('Keep the product and order price in sync')
                    ->wrapperWidth(50)
                    ->instructions('When set to true, any change to the price/ billing address here will be reflected on the product / order. This will stop once this event has been confirmed')
                    ->shown(When::field('confirmed')->equals(0))
                    ->default(1),


                // Invoice Tab

                TabField::called('tab_invoice')
                    ->label('Invoice'),

                NumberField::called('price')
                    ->prepend('£')
                    ->wrapperWidth(33),

                TextField::called('purchase_order')
                    ->wrapperWidth(33),

                SelectField::called('invoice_status')
                    ->choices([
                        'not_paid' => 'Not paid',
                        'paid'     => 'Paid',
                        'part'     => 'Part paid',
                    ])
                    ->default('not_paid')
                    ->returnFormat('value')
                    ->wrapperWidth(33),

                TextAreaField::called('invoice_notes')
                    ->rows(4)
                    ->wrapperWidth(100),

                TrueFalseField::called('confirmed')
                    ->wrapperWidth(50)
                    ->message("Set to 'Yes' to generate the coupon code and reserve delivery slots."),


                TextField::called('invoice_link')
                    ->readOnly(true)
                    ->wrapperWidth(50),


                // Confirmed Tab

                TabField::called('tab_confirmed')
                    ->shown(When::field('confirmed')->equals(1))
                    ->label('Confirmed'),


                DateField::called('expiry_date')
                    ->label('Actual Delivery Date')
                    ->returnFormat('Y-m-d')
                    ->instructions('This should NOT be changed once set.')
                    ->required(true)
                    ->wrapperWidth(50),


                PostObjectField::called('delivery_company_id')
                    ->label('Delivery Company')
                    ->instructions('This should NOT be changed once set.')
                    ->postObject(DeliveryCompany::$postType)
                    ->returnFormat('id')
                    ->required(true)
                    ->wrapperWidth(50),

                TaxonomyField::called('category_id')
                    ->taxonomy('product_cat')
                    ->label('Product Category')
                    ->instructions('Only products from this category can be bought')
                    ->required(true)
                    ->wrapperWidth(50),

                NumberField::called('maximum_products')
                    ->label('Maximum # Products')
                    ->instructions('Maximum number of products from this category that can be bought per order')
                    ->required(true)
                    ->wrapperWidth(50),


                // Line 2
                PostObjectField::called('product_to_add_id')
                    ->label('Automatically add this product to their cart')
                    ->postObject('product')
                    ->returnFormat('id')
                    ->wrapperWidth(25),
                TrueFalseField::called('create_product_to_add')
                    ->label('Create a product if one is not specified')
                    ->wrapperWidth(25)
                    ->default(0),


                TextField::called('code')
                    ->readOnly(true)
                    ->wrapperWidth(15),

                TextField::called('link')
                    ->readOnly(true)
                    ->wrapperWidth(35),


                TabField::called('tab_orders')
                    ->shown(When::field('confirmed')->equals(1))
                    ->label('Orders'),

                PostObjectField::called('order_ids')
                    ->label('Order Numbers')
                    ->instructions('This will be populated automatically')
                    ->multiple(true)
                    ->postObject('shop_order')
                    ->returnFormat('id'),


            ])
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->style('seamless')
            ->attachTo(static::class)
            ->register();


        HookInto::filter('acf/fields/post_object/result/name=order_ids')
            ->run(function ($text, $post, $field, $post_id) {
                return '#' . $post->ID;
            });

        HookInto::action('acf/save_post', 30)
            ->run(function ($post_id) {
                global $post;

                if (!$post or $post->post_type != static::$postType) {
                    return;
                }

                $event = new static($post_id);
                $event->save();
            });


        /**
         * Add our Meta Box
         */
        HookInto::action('add_meta_boxes')
            ->run(function (string $post_type, WP_Post $post) {

                if ($post_type !== static::$postType) {
                    return;
                }

                if (!current_user_can('shop_user_plus')) {
                    return;
                }

                add_meta_box(
                    'event_id',
                    'Download',
                    function ($post) {

                        if ($post->post_status !== 'publish') {
                            return;
                        }

                        $event = new static($post->ID);

                        View::with('admin/events/meta_box.twig')
                            ->addVar('event', $event)
                            ->display();
                    },
                    static::$postType,
                    'side',
                    'low'
                );
            });

        HookInto::action('init', 30)
            ->run(function () {

                /**
                 * Order invoice
                 */
                if (isset($_GET['event_id']) && isset($_GET['download']) && current_user_can('shop_user_plus')) {

                    $event = new static((int)$_GET['event_id']);
                    $event->createCSV();

                }

            });

    }



    public function afterSave()
    {

        // Just make sure we have the date set up
        Date::firstOrCreate($this->expiry_date);

        $used = is_array($this->order_ids) ? count($this->order_ids) : 0;

        $reserved = (int)$this->limit - $used;
        $key = '_order_delivery_' . $this->expiry_date . '_' . $this->delivery_company_id;
        update_post_meta($this->ID, $key, $reserved);

        $key = '_order_delivery_' . $this->expiry_date;
        update_post_meta($this->ID, $key, $reserved);


    }



    public function allocate($order_id)
    {

        update_post_meta($order_id, '_event_coupon_code', $this->code);
        update_post_meta($order_id, '_event_name', $this->post_title);
        update_post_meta($order_id, '_event_id', $this->ID);


        if (!is_array($this->order_ids)) {
            $ids = [];
        } else {
            $ids = $this->order_ids;
        }

        $ids[] = $order_id;

        $this->order_ids = array_unique($ids);
        $this->save();
    }



    public function beforeSave()
    {


        if (!$this->confirmed) {
            try {

                if (!$this->event_product_id && $this->create_event_product) {
                    $product = new WC_Product_Simple();
                    $product->set_name($this->post_title . ' Event');
                    if ($this->price) {
                        $product->set_price((float)$this->price);
                    }
                    $product->set_catalog_visibility('hidden');
                    $product->save();
                    update_field('field_product_cah_type', 'event', $product->get_id());
                    $thumbnail_id = get_post_thumbnail_id($this->ID);
                    if ($thumbnail_id) {
                        set_post_thumbnail($product->get_id(), $thumbnail_id);
                    }
                    $this->event_product_id = $product->get_id();
                    $this->create_event_product = false;
                }

                if ($this->keep_in_sync && $this->event_product_id) {
                    $product = wc_get_product($this->event_product_id);
                    if ($this->price) {
                        $product->set_regular_price((float)$this->price);
                        $product->save();
                    }

                }

                if (!$this->event_order_id && $this->create_event_order && $this->event_product_id) {

                    $order = new Order();
                    $order->add_or_update_product($this->event_product_id, 1);
                    $order->save();
                    $this->event_order_id = $order->get_id();
                    $this->create_event_order = false;

                }

                if ($this->keep_in_sync && $this->event_product_id) {
                    $product = wc_get_product($this->event_product_id);
                    $product->set_regular_price($this->price);
                    $product->save();
                }

                if ($this->keep_in_sync && $this->event_order_id && $this->event_product_id) {


                    $order = Order::find($this->event_order_id)
                        ->update_product_price($this->event_product_id, (float)$this->price)
                        ->set_billing_address($this->company, $this->address_1, $this->address_2, $this->city, $this->state, $this->postcode, '')
                        ->set_shipping_address($this->first_name, $this->last_name, $this->company, $this->address_1, $this->address_2, $this->city, $this->state, $this->postcode, '', '')
                        ->set_customer($this->first_name, $this->last_name, $this->email, $this->phone)
                        ->save();
                    $this->invoice_link = $order->get_order_invoice_url();


                    if ($this->expected_date && $this->postcode) {
                        $availableDates = $order->get_shipping_dates($this->postcode);
                        if (!empty($availableDates)) {
                            foreach ($availableDates['dates'] as $date) {
                                if ($date['available'] && $date['date'] === $this->expected_date) {
                                    $order->set_shipping_data(
                                        $date['date'],
                                        $date['delivery_company_id'],
                                        $date['delivery_area_id'],
                                        0,
                                        $this->postcode,
                                        'FREE',
                                        'Event Delivery',
                                        '',
                                        ''
                                    );
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::error('Error when saving event : ' . $e->getMessage());
            }
            return;

        }

        // Only if confirmed


        if (!$this->product_to_add_id && $this->create_product_to_add) {
            $product = new WC_Product_Simple();
            $product->set_name($this->post_title . ' Event');
            $product->set_price(0);
            $product->set_catalog_visibility('hidden');
            $product->save();
            $thumbnail_id = get_post_thumbnail_id($this->ID);
            if ($thumbnail_id) {
                set_post_thumbnail($product->get_id(), $thumbnail_id);
            }
            $this->product_to_add_id = $product->get_id();
            $this->create_product_to_add = false;
        }


        if (!$this->code) {

            $this->code = 'EV-' . Hasher::encode(time());


            $coupon = new \WC_Coupon();
            $coupon->set_code($this->code);
            $coupon->set_description('In association with Event: ' . $this->post_title);
            $coupon->set_date_expires($this->expiry_date);
            $coupon->save();

            $coupon_id = $coupon->get_id();
            update_field('delivery', 1, $coupon_id);
            update_field('delivery_date', $this->expiry_date, $coupon_id);

            if ($this->event_order_id) {
                try {
                    $order = Order::find($this->event_order_id);
                    $shippingData = $order->get_shipping_item();
                    $shippingData->delivery_company_id = $this->delivery_company_id;
                    $shippingData->date = $this->expiry_date;
                    $shippingData->save();
                    $order->add_item($shippingData);
                    $order->save();
                } catch (Throwable $e) {
                }
            }


        } else {
            $coupon_id = wc_get_coupon_id_by_code($this->code);
            $coupon = new \WC_Coupon($coupon_id);
        }

        if ($this->product_to_add_id) {
            update_field('products', 1, $coupon_id);
            $row = [
                'field_add_product_id'       => $this->product_to_add_id,
                'field_add_product_quantity' => 1,
            ];
            //delete_row('field_add_products', 0,$coupon_id);
            update_field('field_add_products', [$row], $coupon_id);
        }

        $productIDs = [];
        $products = wc_get_products([
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $this->category_id,
                    'operator' => 'IN',
                ],
            ],
        ]);

        /**
         * @var WC_Product_Simple|\WC_Product_Variable $product
         */
        foreach ($products as $product) {
            $productIDs[] = $product->get_id();
        }

        $this->link = get_category_link($this->category_id);
        $coupon->set_usage_limit($this->limit);
        $coupon->set_usage_limit_per_user(1);
        $coupon->set_free_shipping(true);
        $coupon->set_product_ids($productIDs);
        $coupon->save();

        update_field('field_coupon_max_product_ids', $this->maximum_products, $coupon->get_id());

        $this->create_event_product = false;
        $this->create_event_order = false;
        $this->keep_in_sync = false;
    }



    public function createCSV()
    {

        set_time_limit(0);


        $data = [];

        $code = $this->code;


        foreach ($this->order_ids as $order_id) {
            $order = Order::find($order_id);

            $items = $order->get_product_items();

            $shippingData = $order->get_shipping_item();
            $deliveryCompany = new DeliveryCompany($shippingData->delivery_company_id);

            foreach ($items as $item) {

                $product = $item->get_product();

                $data[] = [
                    'Order ID'                  => $order_id,
                    'Name'                      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'Email'                     => $order->get_billing_email(),
                    'Phone'                     => $order->get_billing_phone(),
                    'Billing address 1'         => $order->get_billing_address_1(),
                    'Billing address 2'         => $order->get_billing_address_2(),
                    'Billing address city'      => $order->get_billing_city(),
                    'Billing address state'     => $order->get_billing_state(),
                    'Billing address postcode'  => $order->get_billing_postcode(),
                    'Shipping name'             => $order->get_shipping_first_name() . ' ' . $order->get_billing_last_name(),
                    'Shipping phone'            => $order->get_shipping_phone(),
                    'Shipping address 1'        => $order->get_shipping_address_1(),
                    'Shipping address 2'        => $order->get_shipping_address_2(),
                    'Shipping address city'     => $order->get_shipping_city(),
                    'Shipping address state'    => $order->get_shipping_state(),
                    'Shipping address postcode' => $order->get_shipping_postcode(),
                    'Delivery date'             => $shippingData->date,
                    'Delivery company'          => $deliveryCompany->post_title,
                    'Product'                   => $product->get_name(),
                    'Quantity'                  => $item->get_quantity(),
                ];

            }

        }


        header('Content-Disposition: attachment; filename="' . $code . '.csv";');


        print "\xEF\xBB\xBF";

        print  '"' . implode('","', array_keys($data[0])) . '"' . "\n";
        foreach ($data as $row) {
            print '"' . implode('","', $row) . '"' . "\n";
        }

        exit;

    }



    public function deallocate($order_id)
    {

        delete_post_meta($order_id, '_event_coupon_code');
        delete_post_meta($order_id, '_event_name');
        delete_post_meta($order_id, '_event_id');

        $ids = $this->order_ids;
        if (($key = array_search($order_id, $ids)) !== false) {
            unset($ids[$key]);
            $this->order_ids = $ids;
            $this->save();
        }

    }



    /**
     * Find a event by it's code
     *
     * @param $code
     * @return false|static
     */
    public static function getByCouponCode($code)
    {

        $events = static::get([
            'meta_key'   => 'code',
            'meta_value' => $code,
        ]);

        if ($events->isEmpty()) {
            return false;
        }

        return $events->first();

    }



    /**
     * Find a event by it's product
     *
     * @param $productID
     * @return false|static
     */
    public static function getByEventProductID($productID)
    {

        $events = static::get([
            'meta_key'   => 'event_product_id',
            'meta_value' => $productID,
        ]);

        if ($events->isEmpty()) {
            return false;
        }

        return $events->first();

    }



    public function getDownloadUrl()
    {
        return home_url('/?event_id=' . $this->ID . '&download=1');
    }


}
