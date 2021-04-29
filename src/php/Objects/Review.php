<?php

namespace Theme\Objects;

use Theme\APIs\Hub;
use Theme\Utils\Time;
use Theme\Utils\Validate;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\Where;

/**
 * Class Link
 *
 * @package CoteAtHome\Objects
 * @property string $review
 * @property int $order_id
 * @property int $overall
 * @property int $food
 * @property int $value
 * @property int $delivery
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $phone
 */
class Review extends CustomPost
{


    static $postType = 'review';



    public static function Setup()
    {

        parent::setup();

        /**
         * Create our Post Type
         */
        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-star-filled')
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
         * The Schema
         */
        CreateSchema::Called('Review')
            ->style('seamless')
            ->instructionPlacement('field')
            ->withFields([

                TextAreaField::called('review')
                    ->rows(8)
                    ->required(true),

                PostObjectField::called('order_id')
                    ->postObject('shop_order')
                    ->wrapperWidth(20)
                    ->returnFormat('id'),

                NumberField::called('overall')
                    ->min(0)
                    ->max(6)
                    ->wrapperWidth(20)
                    ->required(true),

                NumberField::called('food')
                    ->min(0)
                    ->max(6)
                    ->wrapperWidth(20),

                NumberField::called('value')
                    ->min(0)
                    ->max(6)
                    ->wrapperWidth(20),

                NumberField::called('delivery')
                    ->min(0)
                    ->max(6)
                    ->wrapperWidth(20),

                SelectField::called('title')
                    ->choices([
                        'Mr'        => 'Mr',
                        'Mrs'       => 'Mrs',
                        'Ms'        => 'Ms',
                        'Miss'      => 'Miss',
                        'Dr'        => 'Dr',
                        'Professor' => 'Professor',
                        'Other'     => 'Other',
                    ])
                    ->returnFormat('value')
                    ->wrapperWidth(20),

                TextField::called('first_name')
                    ->wrapperWidth(40)
                    ->required(true),
                TextField::called('last_name')
                    ->wrapperWidth(40)
                    ->required(true),

                TextField::called('email')
                    ->wrapperWidth(50)
                    ->required(true),

                TextField::called('phone')
                    ->wrapperWidth(50)
                    ->required(true),


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


        AjaxHandler::register('reviews/create', function (
            $order_id,
            $reviewText,
            $overall,
            $food,
            $value,
            $delivery,
            $first_name,
            $title,
            $last_name,
            $email,
            $phone
        ) {


            $errors = [
                'email' => false,
                'phone' => false,
            ];

            $error = false;


            if ($order_id) {
                $order_id = (int)$order_id;
                $order = Order::find($order_id);
                $first_name = $order->get_billing_first_name();
                $last_name = $order->get_billing_last_name();
                $email = $order->get_billing_email();
                $phone = $order->get_billing_phone();
            } else {
                $response = Validate::email($email);
                if (!$response['valid']) {
                    $errors['email'] = $response;
                    $error = true;
                }

                if ($phone) {
                    $response = Validate::tel($phone);
                    if (!$response['valid']) {
                        $errors['phone'] = $response;
                        $error = true;
                    }
                }
            }

            if ($error) {
                return [
                    'success' => false,
                    'errors'  => $errors,
                ];
            }

            $review = static::create([
                'order_id'   => (int)$order_id,
                'review'     => sanitize_text_field($reviewText),
                'overall'    => (int)$overall,
                'title'      => $title,
                'first_name' => sanitize_text_field($first_name),
                'last_name'  => sanitize_text_field($last_name),
                'email'      => sanitize_text_field($email),
                'phone'      => sanitize_text_field($phone),
            ]);

            if ($food) {
                $review->food = (int)$food;
            }
            if ($value) {
                $review->value = (int)$value;
            }
            if ($delivery) {
                $review->delivery = (int)$delivery;
            }

            $review->save();

            Hub::createAtHomeReview([
                "OrderID"        => $order_id,
                "Date"           => Time::now()->format("Y-m-d H:i:s"),
                "OverallRating"  => $overall,
                "Description"    => $reviewText,
                "FoodRating"     => $food,
                "VfmRating"      => $value,
                "DeliveryRating" => $delivery,
                "Public"         => true,
                'Title'          => $title,
                'FirstName'      => $first_name,
                'LastName'       => $last_name,
                'Email'          => $email,
                'Phone'          => $phone,
            ]);

            return [
                'success' => true,
            ];

        });


    }



    public function beforeSave()
    {
        parent::beforeSave();

        $post_title = 'Review by ' . $this->first_name . ' ' . $this->last_name;
        if ($this->order_id) {
            $post_title .= " ({$this->order_id})";
        }

        $this->post_title = $post_title;

    }



    public static function getByOrderID($order_id)
    {

        $events = static::get([
            'meta_key'   => 'order_id',
            'meta_value' => $order_id,
        ]);

        if ($events->isEmpty()) {
            return false;
        }

        return $events->first();
    }


}
