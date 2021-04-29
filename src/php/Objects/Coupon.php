<?php

namespace Theme\Objects;

use Carbon\Carbon;
use Theme\APIs\Shopify;
use Theme\Exceptions\CoteAtHomeException;
use Theme\Settings;
use Theme\Utils\Number;
use Theme\WooCommerce;
use Lnk7\Genie\Fields\DateField;
use Lnk7\Genie\Fields\GroupField;
use Lnk7\Genie\Fields\MessageField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\RepeaterField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TaxonomyField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Options;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\RegisterApi;
use Lnk7\Genie\Utilities\When;
use Lnk7\Genie\Utilities\Where;
use PHPShopify\Exception\ApiException;
use PHPShopify\Exception\CurlException;
use WC_Coupon;
use WC_Order_Item_Coupon;

class Coupon extends WC_Coupon implements GenieComponent
{


    static $postType = 'shop_coupon';


    /**
     * A map of shopify discount types to Woo types
     *
     * @var string[]
     */
    static $shopifyTypeMap = [
        'fixed_amount' => 'fixed_cart',
        'percentage'   => 'percent',
    ];


    public static function setup()
    {

        // Remove coupons case-insensitive filter
        remove_filter('woocommerce_coupon_code', 'strtolower');

        HookInto::filter('woocommerce_coupon_code', 100)->run('strtoupper');

        CreateSchema::Called('Coupon')
            ->instructionPlacement('field')
            ->withFields([

                TabField::called('tab_settings')
                    ->label('Settings'),

                SelectField::called('discount_type')
                    ->choices(
                        [
                            'percent'       => 'Percentage discount',
                            'fixed_cart'    => 'Fixed basket discount',
                            'fixed_product' => 'Fixed product discount',

                        ])
                    ->returnFormat('value')
                    ->wrapperWidth(20)
                    ->default('percent'),

                NumberField::called('coupon_amount')
                    ->required(true)
                    ->wrapperWidth(20),

                DateField::called('date_expires')
                    ->displayFormat('d/m/Y')
                    ->wrapperWidth(20)
                    ->returnFormat('Y-m-d')
                    ->addFilter('acf/update_value/key={$key}', function ($value) {
                        if (!$value) {
                            return $value;
                        }
                        return Carbon::createFromFormat('Ymd', $value)->getTimestamp();
                    }),
                TrueFalseField::called('exclude_from_count')
                    ->key('field_coupon_exclude_from_count')
                    ->label('Exclude from Coupon Limit')
                    ->message("Yes, exclude this coupon when enforcing the 1 coupon per order limit. ")
                    ->default(false)
                    ->wrapperWidth(40),

                TrueFalseField::called('free_shipping')
                    ->key('field_coupon_free_shipping')
                    ->message('Yes, allow free delivery with this coupon code')
                    ->label('Free delivery')
                    ->wrapperWidth(33)
                    ->addFilter('acf/load_value/key={$key}', function ($value) {
                        return $value === 'yes' ? 1 : 0;
                    })
                    ->addFilter('acf/update_value/key={$key}', function ($value) {
                        return $value ? 'yes' : 'no';
                    }),

                MessageField::called('filler1')
                    ->label('')
                    ->wrapperWidth(66)
                    ->shown(When::field('free_shipping')->equals(0)),

                DateField::called('delivery_from')
                    ->label('Only allow delivery dates from')
                    ->wrapperWidth(33)
                    ->returnFormat('Y-m-d')
                    ->shown(When::field('free_shipping')->equals(1)),

                DateField::called('delivery_to')
                    ->label('Only allow delivery up to')
                    ->wrapperWidth(33)
                    ->returnFormat('Y-m-d')
                    ->shown(When::field('free_shipping')->equals(1)),

                TrueFalseField::called('automatic')
                    ->wrapperWidth(33)
                    ->message('Yes, Automatically apply this discount code'),
                TrueFalseField::called('delivery')
                    ->wrapperWidth(33)
                    ->message('Yes, this discount code is linked to a delivery'),
                TrueFalseField::called('products')
                    ->wrapperWidth(33)
                    ->message('Yes, automatically add products when this coupon code is used'),

                TabField::called('tab_restrictions')
                    ->label('Usage Restrictions'),

                NumberField::called('minimum_amount')
                    ->prepend('£')
                    ->wrapperWidth(33),

                NumberField::called('maximum_amount')
                    ->prepend('£')
                    ->wrapperWidth(33),

                SelectField::called('customers')
                    ->key('field_coupon_customers')
                    ->choices(
                        [
                            'all'      => 'All Customers',
                            'new'      => 'New Customers Only',
                            'existing' => 'Existing Customer Only',
                        ])
                    ->returnFormat('value')
                    ->wrapperWidth(33)
                    ->default('all'),

                TaxonomyField::called('product_categories')
                    ->label('Categories: The basket must include a product from one of these categories')
                    ->taxonomy('product_cat')
                    ->fieldType('multi_select')
                    ->returnFormat('id'),

                PostObjectField::called('product_ids')
                    ->postObject('product')
                    ->label('Products: The basket must include at least one of these products...')
                    ->key('field_coupon_product_ids')
                    ->returnFormat('id')
                    ->multiple(true)
                    ->wrapperWidth(75)
                    ->addFilter('acf/load_value/key={$key}', function ($value) {
                        return explode(',', $value);
                    })
                    ->addFilter('acf/update_value/key={$key}', function ($value) {
                        return implode(',', $value);
                    }),

                NumberField::called('max_product_ids')
                    ->label('Up to a maximum of:')
                    ->key('field_coupon_max_product_ids')
                    ->instructions('leave blank for no upper limit')
                    ->wrapperWidth(25)
                    ->shown(When::field('product_ids')->notEmpty()),

                TaxonomyField::called('exclude_product_categories')
                    ->label('Excluded Categories: The basket must NOT include a product from one of these categories')
                    ->taxonomy('product_cat')
                    ->fieldType('multi_select')
                    ->returnFormat('id'),

                PostObjectField::called('exclude_product_ids')
                    ->label('Excluded Products : The basket must NOT include one of these products')
                    ->postObject('product')
                    ->returnFormat('id')
                    ->multiple(true)
                    ->wrapperWidth(100)
                    ->addFilter('acf/load_value/key={$key}', function ($value) {
                        return explode(',', $value);
                    })
                    ->addFilter('acf/update_value/key={$key}', function ($value) {
                        return implode(',', $value);
                    }),

                TabField::called('tab_usage_limits')
                    ->label('Usage Limits'),

                NumberField::called('usage_limit')
                    ->label('Total Usage limit')
                    ->wrapperWidth(50),

                NumberField::called('usage_limit_per_user')
                    ->label('Usage limit per user')
                    ->wrapperWidth(50),

                TabField::called('tab_delivery')
                    ->shown(When::field('delivery')->equals(1))
                    ->label('Linked to Delivery'),
                DateField::called('delivery_date')
                    ->wrapperWidth(50)
                    ->returnFormat('Y-m-d'),
                PostObjectField::called('delivery_area_id')
                    ->label('Delivery Area')
                    ->wrapperWidth(50)
                    ->postObject('delivery-area')
                    ->returnFormat('id'),
                PostObjectField::called('delivery_company_id')
                    ->label('Delivery Company')
                    ->wrapperWidth(50)
                    ->postObject('delivery-company')
                    ->returnFormat('id'),
                TextField::called('postcode')
                    ->wrapperWidth(50),
                NumberField::called('order_id')
                    ->label('Bought with Order')
                    ->wrapperWidth(50),
                NumberField::called('used_order_id')
                    ->label('Used on Order')->wrapperWidth(50),

                TabField::called('Automatically add this coupon')
                    ->shown(When::field('automatic')->equals(1)),

                SelectField::called('automatic_when')
                    ->choices([
                        'category' => 'The cart has products from these Categories',
                        'product'  => 'The cart these products',

                    ])
                    ->default('categories')
                    ->wrapperWidth(50)
                    ->returnFormat('value')
                    ->required(true),

                GroupField::called('automatic_categories')
                    ->label('When the Cart Contains Products from Categories')
                    ->shown(When::field('automatic_when')->equals('category'))
                    ->layout('block')
                    ->withFields([

                        TaxonomyField::called('category_ids')
                            ->label('Categories')
                            ->taxonomy('product_cat')
                            ->fieldType('multi_select')
                            ->wrapperWidth(33)
                            ->required(true)
                            ->returnFormat('id'),

                        SelectField::called('category_type')
                            ->choices([
                                'amount'   => 'Minimum purchase amount (£)',
                                'quantity' => 'Minimum Quantity of Items',

                            ])
                            ->shown(When::field('category_ids')->notEmpty())
                            ->default('quantity')
                            ->wrapperWidth(33)
                            ->returnFormat('value')
                            ->required(true),

                        NumberField::called('category_amount')
                            ->shown(When::field('category_type')->equals('amount'))
                            ->prepend('£')
                            ->required(true)
                            ->wrapperWidth(33),
                        NumberField::called('category_quantity')
                            ->shown(When::field('category_type')->equals('quantity'))
                            ->min(1)
                            ->required(true)
                            ->wrapperWidth(33),

                    ]),

                GroupField::called('automatic_products')
                    ->label('When the Cart Contains Products')
                    ->shown(When::field('automatic_when')->equals('product'))
                    ->layout('block')
                    ->withFields([

                        PostObjectField::called('automatic_product_ids')
                            ->label('Products')
                            ->postObject('product')
                            ->multiple(true)
                            ->required(true)
                            ->wrapperWidth(33)
                            ->returnFormat('id'),

                        SelectField::called('product_type')
                            ->choices([
                                'amount'   => 'Minimum purchase amount (£)',
                                'quantity' => 'Minimum Quantity of Items',

                            ])
                            ->shown(When::field('automatic_product_ids')->notEmpty())
                            ->default('quantity')
                            ->wrapperWidth(33)
                            ->returnFormat('value')
                            ->required(true),

                        NumberField::called('product_amount')
                            ->shown(When::field('product_type')->equals('amount'))
                            ->prepend('£')
                            ->required(true)
                            ->wrapperWidth(33),
                        NumberField::called('product_quantity')
                            ->shown(When::field('product_type')->equals('quantity'))
                            ->min(1)
                            ->required(true)
                            ->wrapperWidth(33),

                    ]),

                TabField::called('tab_products')
                    ->shown(When::field('products')->equals(1))
                    ->label('Automatically add products'),

                RepeaterField::called('add_products')
                    ->key('field_add_products')
                    ->withFields([
                        PostObjectField::called('product_id')
                            ->key('field_add_product_id')
                            ->label('Product')
                            ->postObject('product')
                            ->required(true)
                            ->wrapperWidth(50)
                            ->returnFormat('id'),

                        NumberField::called('quantity')
                            ->key('field_add_product_quantity')
                            ->required(true)
                            ->min(1),
                    ]),

            ])
            ->shown(Where::field('post_type')->equals('shop_coupon'))
            ->register();

        HookInto::action('add_meta_boxes', 999)
            ->run(function () {
                remove_meta_box('woocommerce-coupon-data', 'shop_coupon', 'normal');
            });

        HookInto::action('acf/save_post', 30)
            ->run(function ($post_id) {
                global $post;

                if (!$post or $post->post_type != static::$postType) {
                    return;
                }

                $coupon = new WC_Coupon($post_id);

                $automatic = get_field('automatic', $post_id);
                if ($automatic) {

                    $automatic = Options::get('automatic_coupons', []);

                    $automatic[$coupon->get_code()] = json_decode(json_encode([
                        'coupon_id'  => $coupon->get_id(),
                        'code'       => $coupon->get_code(),
                        'when'       => get_field('automatic_when', $post_id),
                        'categories' => get_field('automatic_categories', $post_id),
                        'products'   => get_field('automatic_products', $post_id),
                    ]));

                    Options::set('automatic_coupons', $automatic);

                }

                wp_update_post([
                    'ID'         => $post_id,
                    'post_title' => strtoupper($post->post_title),
                ]);

            });

        /**
         * if the coupon is automatic, grab the amount and apply it to the coupon.
         */
        HookInto::filter('woocommerce_order_recalculate_coupons_coupon_object')
            ->run(function ($coupon_object, $coupon_code, $coupon_item, $discount) {

                /**
                 * @var WC_Coupon $coupon_object
                 * @var WC_Order_Item_Coupon $coupon_item
                 */

                $coupon_id = $coupon_object->get_id();

                $automatic = get_field('automatic', $coupon_id);
                if (!$automatic) {
                    return $coupon_object;
                }

                $coupon_object->set_discount_type('fixed_cart');
                $coupon_object->set_amount(Number::decimal($coupon_item->get_meta('automatic_discount')));
                return $coupon_object;
            });

        /**
         * API Endpoints to create a Gift Card from Hub
         */
        RegisterApi::post('coupons/create')
            ->run(function ($code, $amount, $expiry, $once, $products) {

                $coupon_id = wc_get_coupon_id_by_code($code);

                if ($coupon_id) {
                    throw new CoteAtHomeException("Coupon code $code already exists");
                }

                $coupon = new Coupon();
                $coupon->set_code($code);
                if (substr($amount, -1) === '%') {
                    $coupon->set_discount_type('percent');
                    $value = Number::decimal(str_replace('%', '', $amount)) * -1;
                } else {
                    $coupon->set_discount_type('fixed_cart');
                    $value = Number::decimal($amount) * -1;
                }
                $coupon->set_amount($value * -1);
                if (!empty($products)) {

                    $productIds = [];
                    foreach ($products as $productID) {

                        $product = WooCommerce::findProductIDByShopifyID('gid://shopify/Product/' . $productID);
                        if ($product) {
                            $productIds[] = $product;
                        }
                    }
                    $coupon->set_product_ids($productIds);
                }

                $meta = [];
                $rows = [];

                if (strpos(strtoupper($code), 'BV-') === 0) {
                    $productID = Settings::get('birthday_product_id');
                    $quantity = Settings::get('birthday_product_quantity', 1);

                    if ($productID) {
                        $product = wc_get_product($productID);
                        $coupon->set_amount(round((float)$product->get_price() * (float)$quantity, 2));
                        $coupon->set_product_ids([$productID]);
                        $row = [
                            'field_add_product_id'       => $productID,
                            'field_add_product_quantity' => $quantity,
                        ];
                        $meta['products'] = 1;
                        $rows['field_add_products'] = $row;
                    }
                }

                if ($once) {
                    $coupon->set_usage_limit_per_user(1);
                    $coupon->set_usage_limit(1);
                }
                if ($expiry) {
                    $coupon->set_date_expires(Carbon::createFromFormat('Y-m-d', $expiry)->toIso8601String());
                }

                $meta['field_coupon_customers'] = 'all';

                $coupon->save();

                foreach ($meta as $key => $value) {
                    update_field($key, $value, $coupon->get_id());
                }
                foreach ($rows as $key => $value) {
                    add_row($key, $value, $coupon->get_id());
                }

            });

    }


    /**
     * Check to see if a Coupon code is on Shopify , so we can honour it here.
     *
     * @param $code
     *
     * @return false|int
     */
    public static function maybeImportFromShopify($code)
    {

        $coupon_id = wc_get_coupon_id_by_code($code);

        if ($coupon_id) {
            return false;
        }

        try {
            [$priceRuleID, $discountCodeID] = Shopify::findDiscountCode($code);

            if (!$priceRuleID) {
                return false;
            }

            $priceRule = Shopify::getPriceRule($priceRuleID);
            if (!$priceRule) {
                return false;
            }

        } catch (ApiException|CurlException $e) {
            return false;
        }
        $priceRule = $priceRule->results;

        // dd($priceRule);
        $coupon = new static();

        $coupon->set_code($code);
        $coupon->set_discount_type(static::$shopifyTypeMap[$priceRule->value_type]);
        $coupon->set_amount($priceRule->value * -1);

        if ($priceRule->target_type === 'line_item') {
            $productIDs = [];
            foreach ($priceRule->entitled_product_ids as $shopifyProductID) {
                $shopifyID = 'gid://shopify/Product/' . $shopifyProductID;
                $id = WooCommerce::findProductIDByShopifyID($shopifyID);
                if ($id) {
                    $productIDs[] = $id;
                }
            }
            $coupon->set_product_ids($productIDs);

        }

        // Usages
        if ($priceRule->once_per_customer) {
            $coupon->set_usage_limit_per_user($priceRule->once_per_customer);
        }
        if ($priceRule->usage_limit > 0) {
            $coupon->set_usage_limit($priceRule->usage_limit);
        }
        if ($priceRule->ends_at) {
            $coupon->set_date_expires($priceRule->ends_at);
        }

        $coupon->save();
        return $coupon->get_id();
    }
}
