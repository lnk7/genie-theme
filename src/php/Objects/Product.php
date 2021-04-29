<?php

namespace Theme\Objects;

use Theme\APIs\Exponea;
use Theme\Log;
use Theme\Utils\Number;
use Theme\Utils\Time;
use Lnk7\Genie\Fields\DateField;
use Lnk7\Genie\Fields\MessageField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\RelationshipField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TaxonomyField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TimeField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Fields\WysiwygField;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Options;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\CreateTaxonomy;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\When;
use Lnk7\Genie\Utilities\Where;
use Lnk7\Genie\View;
use WC_Product;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Term;

class Product extends WC_Product implements GenieComponent
{


    const labelTaxonomy = 'product_label';



    public static function setup()
    {

        HookInto::filter('woocommerce_related_products')
            ->run(function ($related_posts, $product_id, $args) {

                // Get the product Ids in the defined product category
                $exclude_ids = wc_get_products([
                    'status'     => 'publish',
                    'limit'      => -1,
                    'meta_key'   => 'hidden_related',
                    'meta_value' => 1,
                    'return'     => 'ids',
                ]);

                return array_diff($related_posts, $exclude_ids);
            });

        HookInto::filter('woocommerce_product_tabs', 99)
            ->run(function ($tabs) {

                global $post;
                $ingredients = get_field('ingredients', $post->ID);

                if (!$ingredients) {
                    return [];
                }

                return [
                    'ingredients' => [
                        'title'    => 'Ingredients / Dietary Details',
                        'priority' => 10,
                        'callback' => function () use ($ingredients) {
                            echo $ingredients;
                        },
                    ],
                ];

            });


        /**
         * Hide products that cannot be delivered in time
         */
        HookInto::filter('woocommerce_product_query', 100)->run(function ($query) {

            $args = [
                'relation' => 'OR',
                [
                    'key'     => 'delivery_to',
                    'value'   => Time::now()->addDays(3)->format('Ymd'),
                    'compare' => '>=',
                    'type'    => 'NUMERIC',
                ],
                [
                    'key'     => 'delivery_to',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'delivery_to',
                    'compare' => '=',
                    'value'   => '',
                ],
            ];

            $query->set('meta_query', $args);

        });


        $tiers = Options::get('tiers', []);


        CreateSchema::Called('Product')
            ->instructionPlacement('field')
            ->withFields([
                TabField::called('tab_settings')
                    ->label('Settings'),
                SelectField::called('cah_type')
                    ->label('Type of Product')
                    ->choices([
                        'food'  => 'A Food/Drink Item',
                        'slot'  => 'A Delivery Slot',
                        'event' => 'An Event',
                    ])
                    ->default('food')
                    ->wrapperWidth(33)
                    ->returnFormat('value'),
                NumberField::called('cost')
                    ->prepend('£')
                    ->wrapperWidth(33),

                TrueFalseField::called('not_for_sale')
                    ->label('Not for sale')
                    ->instructions('Not for sale products are for reporting, and not seen by the public')
                    ->wrapperWidth(33),

                /**
                 * Food Tab
                 */
                TabField::called('tab_food')
                    ->shown(When::field('cah_type')->equals('food')->and('not_for_sale')->equals(0))
                    ->label('Food/Drink Settings'),
                TrueFalseField::called('gluten_free')
                    ->message('This product is gluten free')
                    ->wrapperWidth(25),
                TrueFalseField::called('vegetarian')
                    ->message('This product is vegetarian')
                    ->wrapperWidth(25),
                TrueFalseField::called('vegan')
                    ->message('This product is vegan')
                    ->wrapperWidth(25),
                TrueFalseField::called('home_freezing')
                    ->message('This product is suitable for home freezing')
                    ->wrapperWidth(25),
                TaxonomyField::called('label')
                    ->taxonomy(Product::labelTaxonomy)
                    ->loadTerms(true)
                    ->saveTerms(true)
                    ->addTerms(true)
                    ->fieldType('select')
                    ->returnFormat('object'),
                WysiwygField::Called('ingredients')
                    ->toolbar('full')
                    ->mediaUpload(false),
                WysiwygField::Called('cooking_instructions')
                    ->toolbar('full')
                    ->mediaUpload(false),

                /**
                 * Tab Slot
                 */
                TabField::called('tab_slot')
                    ->shown(When::field('cah_type')->equals('slot'))
                    ->label('Slot Settings'),

                NumberField::called('reminder_1_days')
                    ->label('1st reminder (days) ')
                    ->instructions('This is the number of days before the delivery date')
                    ->min(1)
                    ->wrapperWidth(50)
                    ->default(9),

                TimeField::called('reminder_1_time')
                    ->label('1st reminder time')
                    ->wrapperWidth(50)
                    ->default('11:00')
                    ->returnFormat('H:i'),

                NumberField::called('reminder_2_days')
                    ->label('2nd reminder (days)')
                    ->instructions('This is the number of days before the delivery date')
                    ->min(1)
                    ->wrapperWidth(50)
                    ->default(4),
                TimeField::called('reminder_2_time')
                    ->label('2nd reminder time')
                    ->wrapperWidth(50)
                    ->default('11:00')
                    ->returnFormat('H:i'),

                /**
                 * Recommended Products
                 */
                TabField::called('tab_recommendations')
                    ->shown(When::field('cah_type')->equals('food')->and('not_for_sale')->equals(0))
                    ->label('Recommendations'),
                RelationshipField::called('recommended_product_ids')
                    ->postObject(['product'])
                    ->filters(['search', 'taxonomy'])
                    ->elements(['featured_image'])
                    ->returnFormat('id'),

                /**
                 * Product Restrictions
                 */
                TabField::called('tab_restrictions')
                    ->label('Restrictions & Modifications')
                    ->shown(When::field('not_for_sale')->equals(0)),
                SelectField::called('coupons')
                    ->label('Discount Code restrictions')
                    ->choices([
                        'any'  => 'No restrictions',
                        'only' => 'Only with discount codes beginning with',
                        'none' => 'This product cannot be bought when using a discount code',
                    ])
                    ->default('any')
                    ->returnFormat('id')
                    ->wrapperWidth(50),

                TextField::called('only_with_coupon')
                    ->label('Only allow purchase with discount codes beginning with')
                    ->shown(When::field('coupons')->equals('only'))
                    ->wrapperWidth(50),
                MessageField::called('spacer1')
                    ->label('')
                    ->shown(When::field('coupons')->notEquals('only'))
                    ->wrapperWidth(50),

                TrueFalseField::called('ignore_minimum')
                    ->label('Ignore Order Minimum?')
                    ->message('Yes, ignore the minimum order amount when this product is in the cart')
                    ->wrapperWidth(50),
                TrueFalseField::called('bought_alone')
                    ->label('Bought Alone?')
                    ->message('Yes, this product should be bought alone')
                    ->wrapperWidth(50),

                DateField::called('delivery_from')
                    ->label('Only allow delivery dates from')
                    ->wrapperWidth(50)
                    ->returnFormat('Y-m-d'),
                DateField::called('delivery_to')
                    ->label('Only allow delivery up to')
                    ->wrapperWidth(50)
                    ->returnFormat('Y-m-d'),

                NumberField::called('limit_per_order')
                    ->label('How many of this product can be added per order?')
                    ->wrapperWidth(50),

                /**
                 * Product Visibility
                 */
                TabField::called('tab_visibility')
                    ->label('Visibility')
                    ->shown(When::field('not_for_sale')->equals(0)),
                TrueFalseField::called('hidden_seo')
                    ->label('Hide from Google / SEO')
                    ->default(0),
                TrueFalseField::called('hidden_related')
                    ->label('Hide from Related products')
                    ->default(0),
                /**
                 * Special Message
                 */

                TabField::called('tab_product_message')
                    ->label('Product Message')
                    ->shown(When::field('not_for_sale')->equals(0)),
                TextAreaField::called('product_message')
                    ->label('Product Message')
                    ->wrapperWidth(60)
                    ->rows(8)
                    ->addFilter('acf/validate_value/key={$key}', function ($valid, $value, $field, $input_name) {
                        return View::isValidTwig($value) ? true : 'Please check the syntax of your text. (make sure the merge fields are enclosed in {{ }}';
                    }),

                MessageField::called('fields_message1')
                    ->label('Product Message Notes')
                    ->wrapperWidth(40)
                    ->message('The product message appears on the checkout thank you page and the confirmation email. This message will be wrapped in a &lt;P&gt; tag, and joined to other message from other products. You can control the sequence in which messages appear by changing the priority <br>
                        <table cellpadding="10">
                        <tr>
                            <td valign="top">
                            <b>Product Merge Fields</b>
                            {{product.name}}<br>
                            {{product.price}} <i>(e.g. £5.00)</i><br>
                            {{product.description}}<br>
                            {{product.link}}<br>
                            </td>
                            <td valign="top">
                            <b>Delivery slot merge fields</b>
                            {{slot.delivery_date}} <br>
                            {{slot.deadline_date}}<br>
                            {{slot.coupon_code}}
                            </td>
                        </tr>
                        </table>
                        '),
                NumberField::called('product_message_priority')
                    ->label('Priority')
                    ->instructions('When multiple products have messages, they will be sorted by this priority (lower numbers appearing before bigger numbers)')
                    ->default(10)
                    ->wrapperWidth(50)
                    ->min(0),
                SelectField::called('product_message_date_format')
                    ->label('Date Format')
                    ->choices([
                        'd/m/Y'  => Time::now()->format('d/m/Y'),
                        'd-m-Y'  => Time::now()->format('d-m-Y'),
                        'jS F Y' => Time::now()->format('jS F Y'),
                        'Y-m-d'  => Time::now()->format('Y-m-d'),
                    ])
                    ->default('jS F Y')
                    ->wrapperWidth(50)
                    ->returnFormat('value'),

                TabField::called('tab_dw')
                    ->label('Data Warehouse'),

                SelectField::called('vat_rate')
                    ->label('VAT Rate')
                    ->choices([
                        'zero'       => '0%',
                        'twenty'     => '20%',
                        'calculated' => 'Calculated from Pick List',
                        'custom'     => 'Custom Value',
                    ])
                    ->addFilter('acf/validate_value/key={$key}', function ($valid, $value, $field, $input_name) {
                        if (!$valid || !isset($_POST['post_ID']) || $value !== 'calculated') {
                            return $valid;
                        }
                        $componentsValid = static::can_be_set_as_calculated($_POST['post_ID']);
                        if ($componentsValid !== true) {
                            return nl2br($componentsValid);
                        }
                        return $valid;
                    })
                    ->default('0')
                    ->wrapperWidth(25)
                    ->returnFormat('value'),

                NumberField::called('vat_custom_rate')
                    ->label('Custom VAT Rate')
                    ->min(0)
                    ->max(99)
                    ->append('%')
                    ->required(true)
                    ->shown(When::field('vat_rate')->equals('custom'))
                    ->wrapperWidth(25),

                SelectField::called('tier')
                    ->choices($tiers)
                    ->wrapperWidth(25)
                    ->returnFormat('id'),

                TaxonomyField::called('dw_category')
                    ->label('Data Warehouse Category')
                    ->taxonomy(ShopifyType::$taxonomy)
                    ->addTerms(true)
                    ->loadTerms(true)
                    ->saveTerms(true)
                    ->fieldType('select')
                    ->wrapperWidth(25)
                    ->returnFormat('object'),

            ])
            ->shown(Where::field('post_type')->equals('product'))
            ->register();

        CreateTaxonomy::called('product_label')
            ->attachTo('product')
            ->set('hierarchical', false)
            ->register();

        HookInto::action('acf/save_post', 30)
            ->run(function ($post_id) {
                global $post;

                if (!$post or $post->post_type != 'product') {
                    return;
                }

                $notForSale = get_field('not_for_sale', $post_id);
                if ($notForSale) {

                    //Force
                    update_field('hidden_related', 1, $post_id);

                    // Make sure it's always child.
                    wp_update_post([
                        'ID'         => $post_id,
                        'post_title' => '[NOT FOR SALE] ' . trim(str_replace('[NOT FOR SALE]', '', $post->post_title)),
                    ]);
                }
                static::calculateVatRates($post_id);
                self::syncWithExponea($post_id);
            });

    }



    /**
     * @param $product_id
     */
    public static function calculateVatRateForProduct($product_id)
    {
        $product = wc_get_product($product_id);

        // No Children? - must be a product with no children or a variation.
        // A variation will have it's parent_id set/
        $idToUse = $product->get_parent_id() ? $product->get_parent_id() : $product_id;

        // What's the vat_rate - it's set on the parent item.
        $vatRateField = get_field('vat_rate', $idToUse);
        $vatRate = 0;

        switch ($vatRateField) {
            case 'twenty' :
                $vatRate = 20;
                break;
            case 'custom' :
                $rate = get_field('vat_rate_custom', $idToUse);
                $vatRate = Number::decimal($rate);
                break;
            case 'calculated' :
                $ProductComponent = ProductComponent::getByProductID($product_id);
                if (!$ProductComponent) {
                    Log::error('can not find product components when trying to calc VAT ($product_id)');
                    break;
                }

                $vat = 0;
                $net = 0;
                foreach ($ProductComponent->components as $product) {
                    $vatRate = static::getVatRate($product['product_id']) / 100;
                    $wcProduct = wc_get_product($product['product_id']);
                    $price = Number::decimal($wcProduct->get_price());
                    $lineSubtotal = $price * Number::integer($product['quantity']);
                    $lineNet = $lineSubtotal / (1 + $vatRate);
                    $lineVat = $lineSubtotal - $lineNet;
                    $vat += $lineVat;
                    $net += $lineNet;

                }
                if ($net > 0) {
                    $vatRate = Number::decimal($vat / $net * 100);
                } else {
                    $vatRate = 0;
                }

                break;
        }

        update_post_meta($product_id, '_vat_rate', $vatRate);

    }



    /**
     * Calculate VAT on one or all products
     *
     * @param null $product_id
     */

    public static function calculateVatRates($product_id = null)
    {

        $args = [
            'numberposts' => -1,
            'post_status' => 'publish',
        ];

        if ($product_id) {
            $args['include'] = [$product_id];
        }

        /**
         * @var WC_Product[] $products
         */
        $products = wc_get_products($args);

        // Do all normal products first.
        foreach ($products as $product) {
            if (!$product->has_child()) {
                static::calculateVatRateForProduct($product->get_id());
            }
        }

        //Now do all the ones with variations
        foreach ($products as $product) {
            if ($product->has_child()) {
                foreach ($product->get_children() as $variation_id) {
                    static::calculateVatRateForProduct($variation_id);
                }
            }
        }

    }



    public static function can_be_set_as_calculated($product_id)
    {

        $product = wc_get_product($product_id);

        $productVariationIDS = $product->get_children();

        if (is_array($productVariationIDS) && !empty($productVariationIDS)) {
            $found = [];
            foreach ($productVariationIDS as $variation_id) {
                $productComponent = ProductComponent::getbyProductID($variation_id);
                if ($productComponent) {
                    $found[] = $variation_id;
                    foreach ($productComponent->components as $component) {
                        if ($component['product_id'] === $product->get_id()) {
                            return "One or more variations contains the parent Product, the VAT rate must be specified.";
                        }
                    }
                }
            }
            if (count($found) != count($productVariationIDS)) {

                $notFound = array_diff($productVariationIDS, $found);
                $notFoundString = [];
                foreach ($notFound as $id) {
                    $nfp = wc_get_product($id);
                    $notFoundString[] = $nfp->get_id() . ': ' . $nfp->get_name();
                }
                $variationsString = [];
                foreach ($productVariationIDS as $id) {
                    $nfp = wc_get_product($id);
                    $variationsString[] = $nfp->get_id() . ': ' . $nfp->get_name();
                }

                return "This product doesn't have pick list components for all variations.\nVariations:\n - " . implode("\n - ", $variationsString) . "\nNot Found for\n - " . implode("\n - ", $notFoundString) . "\n\n";
            }
            return true;
        }

        $productComponent = ProductComponent::getbyProductID($product->get_id());
        if (!$productComponent) {
            return "This product doesn't have pick list components";
        } else {
            foreach ($productComponent->components as $component) {
                if ($component['product_id'] === $product->get_id()) {
                    return "This product component contains itself, the VAT rate must be specified.";
                }
            }
        }

        return true;
    }



    public static function findIdBySlug($slug)
    {

        $ids = get_posts([
            'post_type'   => ['product', 'product_variation'],
            'post_status' => 'published',
            'numberposts' => 1,
            'name'        => trim($slug),
            'fields'      => 'ids',
        ]);

        if (empty($ids)) {
            return false;
        }

        return $ids[0];

    }



    /**
     * @param $id
     * @return array
     */
    public static function getAttributesForExponea($product)
    {


        $id = $product->get_id();

        if ($product->get_type() === 'variation') {
            $idForType = $product->get_parent_id();
        } else {
            $idForType = $product->get_id();
        }

        $cahType = get_field('cah_type', $idForType);

        $categories = array();
        $categoryIDs = array();
        $cat1 = "";
        $cat1ID = "";

        $terms = get_the_terms($id, 'product_cat' );

        if($terms) {
            foreach ($terms as $term) {
                if (!$categories) {
                    $cat1ID = "$term->term_id";
                    $cat1 = "$term->name";
                }
                array_push($categories, "$term->name");
                array_push($categoryIDs, "$term->term_id");
            }
        }

        $attributes = [
            'categories'   => $categories,
            'category_1'   => "$cat1",
            'category_id'  => "$cat1ID",
            'category_ids' => $categoryIDs,
            'price'        => (float)$product->get_price(),
            'product_id'   => "$id",
            'sale_price'   => (float)$product->get_sale_price(),
            'tag_id'       => '',
            'tag_ids'      => [],
            'tags'         => [],
            'title'        => $product->get_title(),
            'type'         => $cahType,
            'domain'       => get_site_url(),
        ];

        $terms = wp_get_post_terms($id, 'product_tag');
        if (!empty($terms)) {
            foreach ($terms as $term) {
                if (!$attributes['tag_id']) {
                    $attributes['tag_id'] = "$term->term_id";
                }
                $attributes['tags'][] = $term->name;
            }
        }

        return $attributes;

    }



    /**
     * Additional Meta dad when adding products
     *
     * @param $product_id
     *
     * @return array
     */
    public static function getMeta($product_id)
    {

        $return = [
            'vat_rate'            => static::getVatRate($product_id),
            'components'          => [],
            'ignore_for_picklist' => false,
        ];

        $productComponent = ProductComponent::getByProductID($product_id);
        if ($productComponent) {
            $return['components'] = $productComponent->components;
            $return['ignore_for_picklist'] = $productComponent->ignore_for_picklist;
        }

        return $return;

    }



    /**
     *  Get all products for the order Editor
     */
    public static function getProducts()
    {

        /**
         * @var WC_Product[] $products
         */
        $products = wc_get_products([
            'numberposts' => -1,
            'post_status' => 'publish',
            'meta_key'    => 'not_for_sale',
            'meta_value'  => '0',
        ]);

        $return = [];

        foreach ($products as $product) {

            $variations = [];
            if ($product instanceof WC_Product_Variable) {
                $productVariationIDS = $product->get_children();
                foreach ($productVariationIDS as $productVariationID) {
                    $productVariation = new WC_Product_Variation($productVariationID);

                    $variations[] = [
                        'id'         => $productVariation->get_id(),
                        'price'      => $productVariation->get_price(),
                        'sale_price' => $productVariation->get_sale_price(),
                        'variation'  => $productVariation->get_attribute_summary(),
                        'image'      => wp_get_attachment_image_src($productVariation->get_image_id(), 'thumbnail')[0],
                        'name'       => $productVariation->get_name().' '.$productVariation->get_attribute_summary(),
                        'status'     => $productVariation->get_status(),
                    ];
                }
            }

            $return[] = [
                'id'         => $product->get_id(),
                'object'     => get_class($product),
                'price'      => $product->get_price(),
                'sale_price' => $product->get_sale_price(),
                'name'       => $product->get_name(),
                'search'     => remove_accents($product->get_name()),
                'status'     => $product->get_status(),
                'variations' => $variations,
                'image'      => wp_get_attachment_image_src($product->get_image_id(), 'thumbnail')[0],
            ];
        }

        return $return;

    }



    public static function getVatRate($product_id)
    {
        return number::decimal(get_post_meta($product_id, '_vat_rate', true));
    }



    /**
     * Get all the labels used for this type.
     *
     * @return array
     */
    public function get_labels()
    {
        $terms = wp_get_post_terms($this->get_id(), static::labelTaxonomy);
        $return = [];
        foreach ($terms as $term) {
            $return[] = $term->name;
        }

        return $return;
    }



    /**
     * Set tags for this order
     *
     * @param array $labels
     * @param bool $addTerm
     *
     * @return $this
     */
    public function set_labels(array $labels, $addTerm = false)
    {

        $termIDs = [];
        foreach ($labels as $label) {
            if (is_int($label)) {
                $termIDs[] = (int)$label;
                continue;
            }

            $term = get_term_by('name', $label, static::labelTaxonomy);
            if ($term) {
                $termIDs[] = $term->term_id;
                continue;
            }

            if ($addTerm) {
                $term = wp_insert_term($label, static::labelTaxonomy);
                $termIDs[] = $term['term_id'];
            }
        }

        wp_set_post_terms($this->get_id(), $termIDs, static::labelTaxonomy);

        return $this;

    }



    public static function syncAllProductsWithExponea()
    {


        $products = wc_get_products([
            'numberposts' => -1,
            'post_status' => 'any',
            //            'meta_key'    => 'not_for_sale',
            //            'meta_value'  => '0',
        ]);

        foreach ($products as $product) {
            self::syncWithExponea($product->get_id());
        }

    }



    public static function syncWithExponea($product_id)
    {

        $rows = [];

        $product = wc_get_product($product_id);

        $variations = self::getVariations($product_id);


        $homeFreezing = get_field('home_freezing', $product->get_id());
        $glutenFree = get_field('gluten_free', $product->get_id());
        $vegetarian = get_field('vegetarian', $product->get_id());
        $vegan = get_field('vegan', $product->get_id());

        /**
         * @var WP_Term[] $terms
         */

        $terms = wp_get_post_terms($product_id, 'product_cat');
        $categories = [];
        $category = '';
        $category_id = '';
        $category_url = '';
        if (!empty($terms)) {
            foreach ($terms as $term) {
                if (!$category) {
                    $category = $term->name;
                    $category_id = $term->term_id;
                    $category_url = get_term_link($term->term_id, 'product_cat');
                }

                $categories[] = $term->name;
            }
        }

        $terms = wp_get_post_terms($product_id, 'product_tag');
        $tags = [];
        if (!empty($terms)) {
            foreach ($terms as $term) {
                $tags[] = $term->name;
            }
        }

       $categoryIDs = [];
        foreach ($product->get_category_ids() as $id) {
            $categoryIDs[] = "$id";
        };


        if (!empty($variations)) {

            foreach ($variations as $variation) {
                $rows[$variation['id']] = [
                    'product_id'       => "$product_id",
                    'type'             => 'variation',
                    'active'           => $product->get_status() === 'publish',
                    'recommendable'    => false,
                    'gluten_free'      => $glutenFree,
                    'vegan'            => $vegan,
                    'vegetarian'       => $vegetarian,
                    'home_freezing'    => $homeFreezing,
                    'image'            => $variation['thumbnail'],
                    'image_fullsize'   => $variation['image'],
                    'title'            => $variation['name'],
                    'description'      => $product->get_description(),
                    'tags'             => $tags,
                    'url'              => $product->get_permalink(),
                    'category_level_1' => $category,
                    'category_id'      => "$category_id",
                    'category_url'     => $category_url,
                    'category_ids'     => $categoryIDs,
                    'categories'       => $categories,
                    'price'            => (float) $variation['price'],
                    'sale_price'       => (float) $variation['sale_price'],
                    'date_added'       => $product->get_date_created() ? $product->get_date_created()->getTimestamp() : Time::utcTimestamp(),
                ];
            }
        }

        $image = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
        $full = wp_get_attachment_image_src($product->get_image_id(), 'full');

        $notForSale = get_field('not_for_sale', $product->get_id());

        $rows[$product->get_id()] = [

            'product_id'       => "$product_id",
            'type'             => 'product',
            'active'           => $product->get_status() === 'publish',
            'recommendable'    => $product->get_status() === 'publish' && !$notForSale,
            'gluten_free'      => $glutenFree,
            'vegan'            => $vegan,
            'vegetarian'       => $vegetarian,
            'home_freezing'    => $homeFreezing,
            'image'            => is_array($image) ? $image[0] : '',
            'image_fullsize'   => is_array($full) ? $full[0] : '',
            'title'            => $product->get_name(),
            'description'      => $product->get_description(),
            'tags'             => $tags,
            'url'              => $product->get_permalink(),
            'category_level_1' => $category,
            'category_id'      => "$category_id",
            'category_url'     => $category_url,
            'category_ids'     => $categoryIDs,
            'categories'       => $categories,
            'price'            => (float) $product->get_price(),
            'sale_price'       => (float) $product->get_sale_price(),
            'date_added'       => $product->get_date_created() ? $product->get_date_created()->getTimestamp() : Time::utcTimestamp(),
        ];

        foreach ($rows as $item_id => $properties) {
            Exponea::updateCatalogItem(EXPONEA_PRODUCT_CATALOG, $item_id, $properties);
        }

    }



    private static function getVariations($id)
    {

        $product = wc_get_product($id);

        $variations = [];
        if ($product instanceof WC_Product_Variable) {
            $productVariationIDS = $product->get_children();
            foreach ($productVariationIDS as $productVariationID) {
                $productVariation = new WC_Product_Variation($productVariationID);

                $variations[] = [
                    'id'         => $productVariation->get_id(),
                    'price'      => $productVariation->get_price(),
                    'sale_price' => $productVariation->get_sale_price(),
                    'variation'  => $productVariation->get_attribute_summary(),
                    'image'      => wp_get_attachment_image_src($productVariation->get_image_id(), 'full')[0],
                    'thumbnail'  => wp_get_attachment_image_src($productVariation->get_image_id(), 'thumbnail')[0],
                    'name'       => $productVariation->get_name(),
                    'status'     => $productVariation->get_status(),
                ];
            }
        }
        return $variations;
    }

}
