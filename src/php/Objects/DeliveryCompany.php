<?php

namespace Theme\Objects;

use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\DateField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\RepeaterField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\Where;

/**
 * Class DeliveryCompany
 *
 * @package CoteAtHome\PostTypes
 *
 * @property int $sunday
 * @property int $monday
 * @property int $tuesday
 * @property int $wednesday
 * @property int $thursday
 * @property int $friday
 * @property int $saturday
 * @property array $overrides
 * @property array $prices
 * @property int $spill_over_id
 *
 */
class DeliveryCompany extends CustomPost
{

    static $postType = 'delivery-company';



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


        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-car')
            ->removeSupportFor(['editor'])
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
            TabField::Called('defaults')
                ->label('Delivery Quotas Defaults'),
        ];

        foreach ($days as $day) {
            $fields[] = NumberField::Called($day)
                ->instructions("Enter 0 for no delivery on this day.")
                ->wrapperWidth(25)
                ->min(0);
        }

        $fields[] = PostObjectField::called('spill_over_id')
            ->label('Spill Over To')
            ->wrapperWidth(25)
            ->postObject(static::$postType)
            ->returnFormat('id');

        $fields[] = TabField::Called('delivery_overrides')->label('Delivery Quotas Overrides');
        $fields[] = RepeaterField::Called('overrides')
            ->withFields([
                DateField::Called('date')
                    ->label('Delivery Date')
                    ->required(true)
                    ->returnFormat('Y-m-d'),
                NumberField::Called('limit')
                    ->label('Frontend Limit')
                    ->required(true),
                NumberField::Called('backend_limit')
                    ->label('Backend Limit (leave blank if the same)'),
            ]);
        $fields[] = TabField::Called('prices_tab')
            ->label('Shipping Prices');

        $fields[] = RepeaterField::Called('prices')
            ->label('Shipping Prices')
            ->withFields([
                TextField::Called('code')
                    ->maxLength(10)
                    ->instructions('A unique code')
                    ->required(true),
                TextField::Called('name')
                    ->required(true),
                NumberField::Called('from')
                    ->prepend('£'),
                NumberField::Called('to')
                    ->prepend('£'),
                NumberField::Called('price')
                    ->prepend('£')
                    ->required(true),
                TrueFalseField::Called('hidden'),
                TextField::Called('blocked_postcodes')
                    ->required(false)
                    ->label('Blocked Postcodes'),
            ]);

        CreateSchema::Called('Delivery Company')
            ->instructionPlacement('field')
            ->withFields($fields)
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->attachTo(static::class)
            ->register();

    }

}
