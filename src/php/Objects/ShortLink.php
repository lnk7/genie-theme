<?php

namespace Theme\Objects;

use Theme\Utils\Hasher;
use Theme\Utils\Time;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\DateField;
use Lnk7\Genie\Fields\GroupField;
use Lnk7\Genie\Fields\MessageField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\RelationshipField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TaxonomyField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\CreateTaxonomy;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\When;
use Lnk7\Genie\Utilities\Where;
use WP_Term;

/**
 * Class Link
 *
 * @package CoteAtHome\Objects
 * @property string $link_hash
 * @property string $link_to
 * @property string $description
 * @property int $post_id
 * @property int $category_id
 * @property array $utm
 * @property int $visits
 * @property string $last_visit_date
 * @property string $source_url
 * @property string $incoming_utm
 * @property string $other_params
 */
class ShortLink extends CustomPost

{


    static $postType = 'short-link';


    public static function Setup()
    {

        parent::setup();

        /**
         * Create our Post Type
         */
        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-admin-links')
            ->set('capabilities', [
                'edit_post'          => 'shop_admin',
                'edit_posts'         => 'shop_admin',
                'edit_others_posts'  => 'shop_admin',
                'publish_posts'      => 'shop_admin',
                'read_post'          => 'shop_admin',
                'read_private_posts' => 'shop_admin',
                'delete_post'        => 'shop_admin',
            ])
            ->set('supports', false)
            ->backendOnly()
            ->register();

        /**
         * Register our order tag
         */
        CreateTaxonomy::called('utm_campaign')
            ->attachTo('link')
            ->set('hierarchical', false)
            ->register();
        CreateTaxonomy::called('utm_medium')
            ->attachTo('link')
            ->set('hierarchical', false)
            ->register();
        CreateTaxonomy::called('utm_source')
            ->attachTo('link')
            ->set('hierarchical', false)
            ->register();

        /**
         * The Schema
         */
        CreateSchema::Called('Link')
            ->style('seamless')
            ->instructionPlacement('field')
            ->withFields([
                TabField::called('tab_Details')
                    ->label('Details')
                    ->endpoint(true),

                TextField::called('link_hash')
                    ->label('Link')
                    ->instructions("If you leave this blank, one will be generated for you. However once created you shouldn't change it.")
                    ->prepend(static::urlPrefix())
                    ->wrapperWidth(33)
                    ->addFilter('acf/validate_value/key={$key}', function ($valid, $value, $field, $input) {

                        if (!$valid || !isset($_POST['post_ID'])) {
                            return $valid;
                        }
                        $post_id = (int)$_POST['post_ID'];

                        $posts = static::get([
                            'post__not_in' => [$post_id],
                            'meta_key'     => 'link_hash',
                            'meta_value'   => $value,
                        ]);

                        if ($posts->count() > 0) {
                            return 'This Value is not Unique. Please enter a unique ' . $field['label'];
                        }

                        return true;

                    }),

                SelectField::called('link_to')
                    ->label('Link to?')
                    ->choices([
                        'post_id'     => 'A page, post or product',
                        'product_cat_id' => 'A product category',
                    ])
                    ->returnFormat('value')
                    ->default('post_id')
                    ->wrapperWidth(33),

                TextAreaField::called('description')
                    ->instructions("It's good to include where this link is being used")
                    ->rows(2)
                    ->wrapperWidth(33)
                    ->required(true),

                RelationshipField::called('post_id')
                    ->label('Link To')
                    ->postObject(['page', 'post', 'product'])
                    ->returnFormat('id')
                    ->min(1)
                    ->max(1)
                    ->required(1)
                    ->shown(When::field('link_to')->equals('post_id'))
                    ->wrapperWidth(50),

                TaxonomyField::called('product_cat_id')
                    ->label('Category')
                    ->taxonomy('product_cat')
                    ->returnFormat('id')
                    ->fieldType('radio')
                    ->addTerms(false)
                    ->loadTerms(false)
                    ->saveTerms(false)
                    ->required(1)
                    ->shown(When::field('link_to')->equals('product_cat_id'))
                    ->wrapperWidth(50),

                GroupField::called('utm')
                    ->label('utm Tags')
                    ->wrapperWidth(50)
                    ->withFields([
                        TaxonomyField::called('campaign')
                            ->label('Campaign')
                            ->taxonomy('utm_campaign')
                            ->addTerms(true)
                            ->loadTerms(true)
                            ->saveTerms(true)
                            ->fieldType('select')
                            ->returnFormat('object'),
                        TaxonomyField::called('source')
                            ->label('Source')
                            ->taxonomy('utm_source')
                            ->addTerms(true)
                            ->loadTerms(true)
                            ->saveTerms(true)
                            ->fieldType('select')
                            ->returnFormat('object'),
                        TaxonomyField::called('medium')
                            ->label('Medium')
                            ->taxonomy('utm_medium')
                            ->addTerms(true)
                            ->loadTerms(true)
                            ->saveTerms(true)
                            ->fieldType('select')
                            ->returnFormat('object'),
                    ])
                    ->instructions('You can used mixed case here when adding utm tags. however all utm tags will be converted to lowercase'),

                TabField::called('tab_settings')
                    ->label('Settings')
                    ->endpoint(true),

                SelectField::called('incoming_utm')
                    ->label('Handling utm tags on this incoming link?')
                    ->choices([
                        'use' => 'Use the incoming tags',
                        'own' => 'Use the ones specified here',
                    ])
                    ->returnFormat('value')
                    ->default('own')
                    ->wrapperWidth(50)
                    ->instructions('Some sites like facebook, will send their own utm tags on the end of this link. What should we do in that case?'),

                SelectField::called('other_params')
                    ->label('Handling other parameters')
                    ->choices([
                        'ignore'  => 'Ignore the additional parameters',
                        'forward' => 'Forward the additional parameters to the destination',
                    ])
                    ->returnFormat('value')
                    ->default('forward')
                    ->wrapperWidth(50)
                    ->instructions('Exponea, for example, will add their own tracking code on the end of a link. What should we do with those?'),

                TabField::called('tab_cleverness')
                    ->label('Cleverness')
                    ->endpoint(true),

                MessageField::called('coming_soon')
                    ->message('We could auto add products and/or coupons, block delivery dates to the cart when this link is clicked'),

                TabField::called('tab_stats')
                    ->label('Stats')
                    ->endpoint(true),

                TextField::called('source_url')
                    ->label('Source Url - Copy this')
                    ->readOnly(true),

                NumberField::called('visits')
                    ->readOnly(true)
                    ->wrapperWidth(50),
                DateField::called('last_visit_date')
                    ->readOnly(true)
                    ->displayFormat('jS F Y')
                    ->wrapperWidth(50),

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

                $link = new static($post_id);
                $link->save();

            });

        HookInto::action('template_redirect', 50)
            ->run(function () {
                global $wpdb;

                $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

                $parts = parse_url($url);

                if (strpos($parts['path'], '/go/') === false) {
                    return;
                }

                $key = trim(substr($parts['path'], 4, 1000));

                $post_id = $wpdb->get_var("select post_id from wp_postmeta where meta_key = 'link_hash' and meta_value ='$key' limit 1");

                if (!$post_id) {
                    return;
                }

                $link = new static($post_id);

                if (!$link ) {
                    return;
                }

                $url = $link->link_to === 'post_id' ? get_permalink($link->post_id[0]) : get_term_link($link->product_cat_id, 'product_cat');
                if (!$url) {
                    return;
                }

                $params = [];

                $utmTagsFound = false;
                foreach ($_GET as $key => $value) {
                    if (in_array(strtolower($key), ['utm_campaign', 'utm_medium', 'utm_source'])) {
                        $utmTagsFound = true;
                        if ($link->incoming_utm === 'use') {
                            $params[$key] = $value;
                        }
                    } else {
                        if ($link->other_params !== 'ignore') {
                            $params[$key] = $value;
                        }
                    }
                }

                if ($link->incoming_utm === 'own' || ($link->incoming_utm === 'use' && !$utmTagsFound)) {
                    $vars = ['campaign', 'source', 'medium'];
                    foreach ($vars as $var) {
                        if ($link->utm[$var] && $link->utm[$var] instanceof WP_Term) {
                            $params['utm_' . $var] = $link->utm[$var]->slug;
                        }
                    }
                }

                $url = http_build_url($url,
                    [
                        "query" => http_build_query($params),
                    ],
                    HTTP_URL_STRIP_AUTH | HTTP_URL_JOIN_PATH | HTTP_URL_JOIN_QUERY
                );

                $link->visits = (int)$link->visits + 1;
                $link->last_visit_date = Time::utcNow()->format('Ymd');
                $link->save();

                wp_redirect($url, 307);
                exit;

            });

    }


    public function beforeSave()
    {
        parent::beforeSave();

        if (!$this->link_hash) {
            $this->link_hash = strtolower(Hasher::encode(time()));
        }
        $this->source_url = static::urlPrefix() . $this->link_hash;
        $this->post_title = $this->source_url . " ({$this->description})";

    }


    /**
     * Set defaults
     */
    public function setDefaults()
    {
        parent::setDefaults();
        $this->visits = 0;
    }


    /**
     * The prefix
     *
     * @return string
     */
    protected static function urlPrefix()
    {
        return home_url('/go/');
    }

}
