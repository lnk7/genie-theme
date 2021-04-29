<?php

namespace Theme;

use Lnk7\Genie\Debug;
use Lnk7\Genie\Fields\EmailField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\RepeaterField;
use Lnk7\Genie\Fields\TabField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Fields\WysiwygField;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Options;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\Where;

class Settings implements GenieComponent
{


    public static function setup()
    {

        HookInto::action('init')
            ->run(function () {

                acf_add_options_page([
                    'page_title' => 'Cote At Home Website Settings',
                    'menu_title' => 'Website Settings',
                    'menu_slug'  => 'cah-settings',
                    'capability' => 'manage_options',
                    'redirect'   => true,
                    'icon_url'   => 'dashicons-schedule',
                ]);

            });

        CreateSchema::Called('Site Settings')
            ->withFields([

                TabField::Called('tab_checkout')
                    ->label('Checkout Settings'),

                NumberField::called('days_ahead')
                    ->label('Days Ahead')
                    ->instructions('How many days ahead should we show on the calendar?')
                    ->min(1)
                    ->max(720)
                    ->wrapperWidth(25)
                    ->required(true)
                    ->append('Days')
                    ->default(45),

                TrueFalseField::called('show_cote_delivery')
                    ->label('Show Free Delivery')
                    ->instructions('Show CÔTE free shipping to Shop Plus users?')
                    ->wrapperWidth(25)
                    ->default(true),

                NumberField::called('coupon_limit')
                    ->label('Discount Code Limit')
                    ->instructions('The number of discount codes that can be used at once')
                    ->min(1)
                    ->max(10)
                    ->wrapperWidth(25)
                    ->required(true)
                    ->default(1),
                TextField::called('coupon_limit_error')
                    ->label('Discount Code Limit Error')
                    ->required(true)
                    ->default('Unfortunately only one discount code can be used at a time.')
                    ->wrapperWidth(25),

                NumberField::called('postcode_api_limit')
                    ->label('Daily Postcode API Limit')
                    ->instructions('Maximum allowed backup postcode lookups via get address')
                    ->wrapperWidth(25)
                    ->required(true)
                    ->default(2000),

                TabField::Called('tab_birthday_voucher')
                    ->label('Birthday Voucher'),

                PostObjectField::called('birthday_product_id')
                    ->label('Birthday Voucher Product')
                    ->instructions('This product will be automatically added to the cart if not already present')
                    ->postObject(['product', 'product_variation'])
                    ->returnFormat('id')
                    ->wrapperWidth(50),
                NumberField::called('birthday_product_quantity')
                    ->instructions('Limited to 1 for now.')
                    ->required(true)
                    ->min(1)
                    ->max(1)
                    ->wrapperWidth(50)
                    ->default(1),

                TabField::Called('tab_invoices')
                    ->label('Invoices'),

                WysiwygField::called('invoice_footer')
                    ->default(' <p>Thank you for shopping with Us!</p>
                        <h3>Côte at Home</h3>
                        <p>Woolverstone House, 61-62 Berners Street, Fitzrovia, , W1T 3NJ, United Kingdom<br/>
                            <a href="mailto:contact@coteathome.co.uk">contact@coteathome.co.uk</a><br/>
                            <a href="https://coteathome.co.uk">coteathome.co.uk</a></p>')
                    ->toolbar('basic'),
                WysiwygField::called('invoice_payment')
                    ->instructions('Additional notes for events when Payment is required')
                    ->default(' <p>Please make payment to <b>Cote Deliveries Ltd</b>.  Sort Code: <b>401160</b>.   Account Number: <b>31332287</b></p>')
                    ->toolbar('basic'),

                TabField::Called('tab_forms')
                    ->label('Forms'),

                EmailField::called('contact_form_email')
                    ->label('Contact Form')
                    ->instructions('The contact form will be sent here')
                    ->default('athome@cote.co.uk')
                    ->wrapperWidth(50),

                TabField::Called('tab_events')
                    ->label('Events'),
                RepeaterField::called('event_tiers')
                    ->withFields([
                        TextField::called('code')
                            ->required(true),
                        TextField::called('tier')
                            ->required(true),
                        NumberField::called('covers')
                            ->min(1)
                            ->max(100)
                            ->default(1)
                            ->required(true),
                        NumberField::called('price')
                            ->prepend('£')
                            ->required(true),
                    ])
                    ->required(true),


                TabField::Called('tab_cart')
                    ->label('Carts'),
                NumberField::called('cart_expiry')
                    ->label('Cart retention')
                    ->instructions('The number of days to retain abandoned carts')
                    ->min(1)
                    ->wrapperWidth(25)
                    ->required(true)
                    ->default(30),


            ])
            ->style('seamless')
            ->shown(Where::field('options_page')->equals('cah-settings'))
            ->instructionPlacement('field')
            ->register();


        HookInto::action('acf/save_post', 20)
            ->run(function () {
                $screen = get_current_screen();
                if (strpos( $screen->id,'cah-settings') !== false) {

                    $tiers = get_field('event_tiers', 'option');
                    $return = [];
                    foreach ($tiers as $tier) {
                        $return[$tier['code']] = "{$tier['tier']} (for {$tier['covers']} £{$tier['price']})";
                    }
                    Options::set('tiers', $return);

                }
            });


    }



    /**
     * get an option from ACF
     *
     * @param $option
     * @param bool $default
     *
     * @return bool|mixed
     */
    public static function get($option, $default = false)
    {
        $value = get_field($option, 'option');

        return $value ? $value : $default;

    }



    public static function getEventTiers()
    {
    }

}
