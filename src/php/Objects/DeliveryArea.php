<?php

namespace Theme\Objects;

use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TimeField;
use Lnk7\Genie\Options;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\Where;


/**
 * Class DeliveryAreas
 * @package CoteAtHome\PostTypes
 *
 * @property int $sunday
 * @property int $monday
 * @property int $tuesday
 * @property int $wednesday
 * @property int $thursday
 * @property int $friday
 * @property int $saturday
 * @property string $delivery_days_text
 * @property int $cut_off_days
 * @property string $cut_off_time
 * @property string $postcodes
 *
 */
class DeliveryArea extends CustomPost
{

    static $postType = 'delivery-area';


    static public function setup()
    {

        parent::setup();

        $days = [
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
        ];


        CreateCustomPostType::Called(static::$postType, 'Delivery Area','Delivery Areas')
            ->icon('dashicons-location-alt')
            ->removeSupportFor(['editor', 'thumbnail'])
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


        $fields = [
            TabField::Called('settings'),
            TextField::Called('delivery_days_text')
                ->required(true)
                ->wrapperWidth(50),
            NumberField::Called('cut_off_days')
                ->min(1)
                ->max(10)
                ->wrapperWidth(25),
            TimeField::Called('cut_off_time')
                ->default('14:00')
                ->returnFormat('H:i:s')
                ->wrapperWidth(25),
            TextAreaField::Called('postcodes')
                ->rows(6)
                ->required(true),
            TextAreaField::Called('blocked_postcodes')
                ->rows(6)
                ->required(false),

            TabField::Called('companies')
                ->label('Delivery Companies'),
        ];

        foreach ($days as $day) {
            $fields[] = PostObjectField::Called($day)
                ->postObject('delivery-company')
                ->multiple(false)
                ->wrapperWidth(25)
                ->returnFormat('id');
        }

        CreateSchema::Called('Delivery Area')
            ->instructionPlacement('field')
            ->withFields($fields)
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->attachTo(static::class)
            ->register();

        HookInto::action('acf/save_post', 30)
            ->run(function ($post_id) {
                global $post;

                if (!$post or $post->post_type != static::$postType) {
                    return;
                }

                static::rebuildPostcodeLookup();

            });


        HookInto::Action('after_deploy')
            ->run(function(){
               static::rebuildPostcodeLookup();
            });

    }


    /**
     * Rebuild the lookup table
     */
    public static function rebuildPostcodeLookup() {

        $allPostcodes = [];
        $areas = static::get();
        foreach ($areas as $area) {
            $areaPostcodes = explode(',', $area->postcodes);
            foreach ($areaPostcodes as $areaPostcode) {
                $allPostcodes[trim(strtoupper($areaPostcode))] = $area->ID;
            }
        }

        Options::set('postcodeAreaLookup', $allPostcodes);

    }



    /**
     *
     * Determine the delivery Area by Postcode
     *
     * @param $postcode
     *
     * @return false|static
     */
    public static function getByPostcode($postcode)
    {

        $allPostcodes = Options::get('postcodeAreaLookup');

        // Clean up our postcode
        $postcode = preg_replace('/[^A-Z0-9]/', '', strtoupper($postcode));

        //Reduce the postcode by one char until we find something
        // for N134BS, try
        //     N134BS, N134B, N134, N13, N1, N
        while( strlen($postcode)>0 ) {

            if(array_key_exists($postcode, $allPostcodes)) {
                return new static($allPostcodes[$postcode]);
            }
            $postcode = substr($postcode,0,-1);
        }

        // Postcode not found.
        return false;


    }

}
