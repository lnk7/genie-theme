<?php

namespace Theme\Objects;

use Carbon\Carbon;
use Theme\APIs\Exponea;
use Theme\Utils\Hasher;
use Theme\Utils\Validate;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Fields\EmailField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\Where;
use WC_Coupon;

/**
 * Class Referral
 *
 * @property $from_first_name
 * @property $from_email
 * @property $to_first_name
 * @property $to_email
 * @property $message
 * @property $to_coupon_id
 * @property $to_order_id
 * @property $from_coupon_id
 */
class Referral extends CustomPost
{


    /**
     * Prefix used for coupon codes for "from" person
     */
    const referralFromPrefix = 'RF-';


    /**
     * Prefix used for coupon codes for "to" person
     */
    const referralToPrefix = 'RT-';


    /**
     * The default referral amount
     * TODO:  setup in settings
     */
    const referralAmount = 10;


    /**
     * The post type
     *
     * @var string
     */
    static $postType = 'referral';


    /**
     * Setup our hooks, filters and AJAX calls
     */
    public static function setup()
    {

        parent::setup();

        /**
         * Create our Post Type
         */
        CreateCustomPostType::Called(static::$postType)
            ->icon('dashicons-groups')
            ->set('capabilities', [
                'edit_post'          => 'shop_admin',
                'edit_posts'         => 'shop_admin',
                'edit_others_posts'  => 'shop_admin',
                'publish_posts'      => 'shop_admin',
                'read_post'          => 'shop_admin',
                'read_private_posts' => 'shop_admin',
                'delete_post'        => 'shop_admin',
            ])
            ->set('supports', false )
            ->backendOnly()
            ->register();

        /**
         * The Schema
         */
        CreateSchema::Called('Referral')
            ->style('seamless')
            ->instructionPlacement('field')
            ->withFields([
                EmailField::called('from_first_name')
                    ->label('Referrer First Name')
                    ->wrapperWidth(25),
                EmailField::called('from_email')
                    ->label('Referrer Email')
                    ->wrapperWidth(25),
                EmailField::called('to_first_name')
                    ->label('Referral First Name')
                    ->wrapperWidth(25),
                EmailField::called('to_email')
                    ->label('Referral Email')
                    ->wrapperWidth(25),
                TextAreaField::called('message')
                    ->rows(3),
                PostObjectField::called('to_coupon_id')
                    ->label('Coupon Issued to Referral')
                    ->postObject(['shop_coupon'])
                    ->returnFormat('id')
                    ->wrapperWidth(33),
                PostObjectField::called('to_order_id')
                    ->label('Referral used Coupon on Order ID ')
                    ->postObject(['shop_order'])
                    ->returnFormat('id')
                    ->wrapperWidth(33),
                PostObjectField::called('from_coupon_id')
                    ->label('Coupon Issued to Referrer')
                    ->postObject(['shop_coupon'])
                    ->returnFormat('id')
                    ->wrapperWidth(33),

            ])
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->attachTo(static::class)
            ->register();

        /**
         * Setup our handler for the form
         */
        AjaxHandler::register('referrals/create', function ($firstName, $lastName, $email, $phone, $message, $to, $consent, $location, $terms) {

            $fromEmail = sanitize_email($email);
            $fromFirstName = sanitize_text_field($firstName);
            $fromLastName = sanitize_text_field($lastName);
            $phone = sanitize_text_field($phone);

            if (!$fromEmail) {
                return [
                    'success' => false,
                    'message' => 'Please specify your email address.',
                ];
            }

            $response = Validate::email($fromEmail);

            if (!$response['valid']) {
                return [
                    'success' => false,
                    'message' => "$fromEmail does not appear to be a valid email address, please check and try again",
                ];
            }

            $returnMessage = '';
            foreach ($to as $toSomeone) {

                $toEmail = sanitize_email($toSomeone['email']);
                $response = Validate::email($toEmail);

                if (!$response['valid']) {
                    $returnMessage = "Sorry, email address $toEmail is invalid, please check and try again.";
                } else {

                    if ($toEmail === $fromEmail) {
                        $returnMessage = "Sorry, you cannot refer yourself.";
                    } else {

                        // Check if this person has been referred
                        $customer = Exponea::getCustomer($toEmail);
                        if ($customer && $customer->referred_by) {
                            $returnMessage = "$toEmail has already been referred by another customer";
                        }
                        if ($customer && strtolower($customer->cote_at_home) === 'customer') {
                            $returnMessage = "$toEmail has already registered with Côte at Home";
                        }
                    }
                }
            }

            if ($returnMessage !== '') {
                return [
                    'success' => false,
                    'message' => $returnMessage,
                ];
            }

            Exponea::update([
                'customer_ids' => [
                    'registered' => $fromEmail,
                ],
                'properties'   => [
                    'first_name' => $fromFirstName,
                    'last_name'  => $fromLastName,
                    'phone'      => $phone,
                ],
            ]);

            if (isset($consent) && $consent && isset($terms) && $terms) {

                // Add the event
                Exponea::track([
                    'customer_ids' => [
                        'registered' => $fromEmail,
                    ],
                    'event_type'   => 'consent',
                    'timestamp'    => time(),
                    'properties'   => [
                        'action'      => "accept",
                        'category'    => "cah",
                        'valid_until' => "unlimited",
                        'message'     => $terms,
                        'location'    => $location,
                        'domain'      => "coteathome.co.uk",
                        'language'    => "en",
                        'placement'   => "CAH Referral Form",
                    ],
                ]);
            }
            // Add the event
            Exponea::track([
                'customer_ids' => [
                    'registered' => $fromEmail,
                ],
                'event_type'   => 'referral',
                'timestamp'    => time(),
                'properties'   => [
                    'type'      => 'from',
                    'message'   => $message,
                    'to'        => json_encode($to),
                    'referrals' => count($to),
                ],
            ]);

            foreach ($to as $toSomeone) {
                // Sanitize !
                $toEmail = sanitize_email($toSomeone['email']);
                $toFirstName = sanitize_text_field($toSomeone['firstName']);

                if (!$toEmail) {
                    continue;
                }

                // Create The voucher
                $referral = static::create([
                    'from_first_name' => $fromFirstName,
                    'from_email'      => $fromEmail,
                    'message'         => $message,
                    'to_email'        => $toEmail,
                    'to_first_name'   => $toFirstName,
                ]);

                $code = self::referralToPrefix . Hasher::encode($referral->ID);
                $end = Carbon::now()->addDays(30);

                $coupon = new WC_Coupon();
                $coupon->set_code($code);
                $coupon->set_description("A referral code to $toFirstName ($toEmail) from  $fromFirstName $fromLastName ($fromEmail)");
                $coupon->set_date_expires($end->getTimestamp());
                $coupon->set_discount_type('fixed_cart');
                $coupon->set_amount(self::referralAmount);
                $coupon->set_usage_limit(1);
                $coupon->set_usage_limit_per_user(1);
                $coupon->save();

                $referral->to_coupon_id = $coupon->get_id();
                $referral->save();

                // Create the referral in Exponea
                Exponea::update([
                    'customer_ids' => [
                        'registered' => $toEmail,
                    ],
                    'properties'   => [
                        'email'       => $toEmail,
                        'referred_by' => $fromEmail,
                    ],
                ]);

                // Create the event
                Exponea::track([
                    'customer_ids' => [
                        'registered' => $toEmail,
                    ],
                    'event_type'   => 'referral',
                    'timestamp'    => time(),
                    'properties'   => [
                        'first_name'      => $toFirstName,
                        'email'           => $toEmail,
                        'type'            => 'to',
                        'from_first_name' => $fromFirstName,
                        'from_last_name'  => $fromLastName,
                        'from_email'      => $fromEmail,
                        'code'            => $code,
                        'message'         => $message,
                        'amount'          => self::referralAmount,
                        'expiry_time'     => $end->getTimestamp(),
                    ],
                ]);
            }

            return [
                'success' => true,
            ];

        });

    }


    /**
     * If a coupon code was used that was from a referral, create a coupon code for the referrer
     *
     * @param $code
     * @param $coupon_id
     * @param $orderID
     */
    public static function maybeRewardReferral($code, $coupon_id, $orderID)
    {

        // if the code doesn't begin with 'RT-' we don't care
        if (strpos($code, self::referralToPrefix) !== 0) {
            return;
        }

        // Ok - who referred this?
        $referral = static::getByCouponID($coupon_id);

        //Not found or used already
        if (!$referral || $referral->to_order_id) {
            return;
        }

        // so we dont do this twice.
        $referral->to_order_id = $orderID;

        // Now we need to create a voucher for the "from" person
        $fromCode = self::referralFromPrefix . Hasher::encode($referral->ID +$orderID);
        $end = Carbon::now()->addDays(30);

        $coupon = new WC_Coupon();
        $coupon->set_code($fromCode);
        $coupon->set_description("A referral coupon for {$referral->from_email} for referring {$referral->to_email} who purchased (order $orderID)");
        $coupon->set_date_expires($end->getTimestamp());
        $coupon->set_discount_type('fixed_cart');
        $coupon->set_amount(self::referralAmount);
        $coupon->set_usage_limit(1);
        $coupon->set_usage_limit_per_user(1);
        $coupon->save();

        $referral->from_coupon_id = $coupon->get_id();
        $referral->save();

        $payload = [
            'customer_ids' => [
                'registered' => sanitize_email($referral->from_email),
            ],
            'event_type'   => 'referral',
            'timestamp'    => time(),
            'properties'   => [
                'type'               => 'used',
                'used_by_email'      => $referral->to_email,
                'used_by_first_name' => $referral->to_first_name,
                'used_by_order_id'   => $orderID,
                'code'               => $fromCode,
                'amount'             => self::referralAmount,
                'expiry_time'        => $end->getTimestamp(),
            ],
        ];
        Exponea::track($payload);

    }


    /**
     * @param $coupon_id
     *
     * @return false|static
     */
    public static function getByCouponID($coupon_id)
    {

        $objects = static::get([
            'meta_key'   => 'to_coupon_id',
            'meta_value' => $coupon_id,
        ]);

        if ($objects->isEmpty()) {
            return false;
        }

        return $objects->first();
    }


    function beforeSave()
    {
        parent::beforeSave();

        $date = new Carbon($this->post_date);

        $this->post_title = "{$this->from_first_name } ({$this->from_email}) referred {$this->to_first_name } ({$this->to_email}) on ".$date->format('jS F Y ');

    }

}
