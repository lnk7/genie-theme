<?php

namespace Theme\Objects;

use Theme\Traits\IsTaggable;
use Theme\Utils\Time;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\EmailField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\CreateTaxonomy;
use Lnk7\Genie\Utilities\RegisterApi;
use Lnk7\Genie\Utilities\Where;

/**
 * Class Customer
 * @package CoteAtHome\Objects
 *
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 * @property bool $accepts_marketing
 * @property string billing_company
 * @property string billing_address_1
 * @property string billing_address_2
 * @property string billing_city
 * @property string billing_state
 * @property string billing_postcode
 * @property string billing_country
 * @property string shipping_company
 * @property string shipping_address_1
 * @property string shipping_address_2
 * @property string shipping_city
 * @property string shipping_state
 * @property string shipping_postcode
 * @property string shipping_country
 * @property string shipping_phone
 *
 *
 */
class Customer extends CustomPost
{

    use IsTaggable;

    static $postType = 'customer';



    /**
     * Wordpress init hook
     */
    static public function setup()
    {

        CreateTaxonomy::called(static::get_taxonomy())
            ->register();

        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-admin-users')
            ->backendOnly()
            ->removeSupportFor(['editor', 'thumbnail'])
            ->addTaxonomy(static::get_taxonomy())
            ->set('capabilities', [
                'edit_post'          => 'shop_user',
                'edit_posts'         => 'shop_user',
                'edit_others_posts'  => 'shop_user',
                'publish_posts'      => 'shop_user',
                'read_post'          => 'shop_user',
                'read_private_posts' => 'shop_user',
                'delete_post'        => 'shop_user',
            ])
            ->register();

        CreateSchema::Called('Customer')
            ->instructionPlacement('field')
            ->withFields([
                TabField::called('Contact Details'),
                TextField::called('first_name')
                    ->wrapperWidth(50),
                TextField::called('last_name')
                    ->wrapperWidth(50),
                EmailField::Called('email')
                    ->wrapperWidth(50),
                TextField::called('phone')
                    ->wrapperWidth(50),
                TrueFalseField::called('accepts_marketing'),
                TabField::called('Billing Address'),
                TextField::called('billing_company'),
                TextField::called('billing_address_1')->wrapperWidth(50),
                TextField::called('billing_address_2')->wrapperWidth(50),
                TextField::called('billing_city')->wrapperWidth(50),
                TextField::called('billing_state')->wrapperWidth(50),
                TextField::called('billing_postcode')->wrapperWidth(50),
                TextField::called('billing_country')->wrapperWidth(50),
                TabField::called('Shipping Address'),
                TextField::called('shipping_company'),
                TextField::called('shipping_address_1')->wrapperWidth(50),
                TextField::called('shipping_address_2')->wrapperWidth(50),
                TextField::called('shipping_city')->wrapperWidth(50),
                TextField::called('shipping_state')->wrapperWidth(50),
                TextField::called('shipping_postcode')->wrapperWidth(50),
                TextField::called('shipping_country')->wrapperWidth(50),
                TextField::called('shipping_phone')->wrapperWidth(50),
            ])
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->attachTo(static::class)
            ->register();


        RegisterApi::post('customers/review')
            ->run(function ($email, $order_id) {

                $customer = self::findOrNew($email);
                if (!$customer->ID) {
                    return [
                        'message' => 'Customer Not Found',
                    ];
                }

                $data = [
                    'date'     => Time::utcTimestamp(),
                    'order_id' => $order_id,
                ];

                update_post_meta($customer->ID, 'last_cah_review', $data);

                return [
                    'message' => 'Review Saved',
                ];

            });

    }



    public function beforeSave()
    {
        $this->post_title = $this->first_name . ' ' . $this->last_name . ' (' . $this->email . ')';
    }



    public static function findOrNew($email)
    {
        $customer = static::getByEmail($email);
        if ($customer) {
            return $customer;
        }

        $customer = new static();
        $customer->email = $email;

        return $customer;

    }



    /**
     * Find a customer by their email address
     *
     * @param $email
     * @return false|mixed
     */
    public static function getByEmail($email)
    {

        if (!$email) {
            return false;
        }

        $customers = static::get([
            'meta_key'   => 'email',
            'meta_value' => $email,
        ]);

        if ($customers->isEmpty()) {
            return false;
        }

        return $customers->first();

    }



    /**
     * Get all Orders for this customer.
     *
     * @return Order[]
     */
    public function getOrders()
    {

        $ids = self::getOrderIDs();
        $orders = [];

        foreach ($ids as $id) {
            $orders[] = new Order($id);
        }

        return $orders;


    }



    public function getOrderIDs()
    {
        return get_posts([
            'post_type'   => 'shop_order',
            'meta_key'    => '_cc_id',
            'meta_value'  => $this->ID,
            'post_status' => 'any',
            'fields'      => 'ids',
        ]);
    }



    public function get_id()
    {
        return $this->ID;
    }


}
