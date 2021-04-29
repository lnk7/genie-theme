<?php

namespace Theme\Objects;

use Lnk7\Genie\Fields\ImageField;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\CreateTaxonomy;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\Where;

class ShopifyType
    implements GenieComponent
{


    static $taxonomy = 'shopify_type';


    public static function setup()
    {

        HookInto::action('init')
            ->run(function () {

                $plural = 'DW Categories';
                $singular = 'DW Category';

                CreateTaxonomy::Called(static::$taxonomy)
                    ->setLabels([
                        'name'                       => $plural,
                        'singular_name'              => $singular,
                        'menu_name'                  => $plural,
                        'all_items'                  => 'All ' . $plural,
                        'edit_item'                  => 'Edit ' . $singular,
                        'view_item'                  => 'View ' . $singular,
                        'update_item'                => 'Update ' . $singular,
                        'add_new_item'               => 'Add New ' . $singular,
                        'new_item_name'              => 'New ' . $singular . ' Name',
                        'parent_item'                => 'Parent ' . $singular,
                        'parent_item_colon'          => 'Parent ' . $singular . ':',
                        'search_items'               => 'Search ' . $plural,
                        'popular_items'              => 'Popular ' . $plural,
                        'separate_items_with_commas' => 'Separate ' . $plural . ' with commas',
                        'add_or_remove_items'        => 'Add or remove ' . $plural,
                        'choose_from_most_used'      => 'Choose from the most used ' . $plural,
                        'not_found'                  => 'No ' . $plural . ' found',
                        'back_to_items'              => '← Back to ' . $plural,
                    ])
                    ->attachTo('product')
                    ->set('hierarchical', false)
                    ->set('public', true)
                    ->set('publicly_queryable', true)
                    ->set('show_admin_column', true)
                    ->set('rewrite', [
                        'slug'       => 'type',
                        'with_front' => false,
                    ])
                    ->register();

                register_taxonomy_for_object_type(static::$taxonomy, 'product');

                CreateSchema::Called('Image')
                    ->instructionPlacement('field')
                    ->withFields([
                        ImageField::Called('image_id')
                            ->key('collection_image_id')
                            ->previewSize('thumbnail'),
                    ])
                    ->shown(Where::field('taxonomy')->equals(static::$taxonomy))
                    ->register();
            });

    }

}
