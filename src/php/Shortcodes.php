<?php

namespace Theme;

use Theme\Objects\Review;
use Theme\Utils\Hasher;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Request;
use Lnk7\Genie\View;

class Shortcodes implements GenieComponent
{


    public static function setup()
    {

        add_shortcode('contact_form', function () {
            $endpoint = AjaxHandler::generateUrl('contact_form');
            return '<div id="contact_form"><contact-form  endpoint="' . $endpoint . '"/></div>';
        });
        add_shortcode('nhs_form', function () {
            return '<div id="nhs_form"><nhs-form/></div>';
        });

        add_shortcode('referral_form', function () {
            $endpoint = AjaxHandler::generateUrl('referrals/create');

            return '<div id="referral_form"><referral-form endpoint="' . $endpoint . '"/></div>';
        });

        add_shortcode('review_form', function () {

            $order_id = 0;
            if (Request::has('order_id')) {
                $order_id = Hasher::decode(Request::get('order_id'));
            }
            $score = Request::has('score') ? (int)Request::get('score') : 0;


            $reviewSubmitted = false;

            if ($order_id) {
                $reviewSubmitted = Review::getByOrderID($order_id);
            }

            return View::with('shortcodes/review_form.twig')
                ->addVars([
                    'order_id'          => $order_id,
                    'score'             => $score,
                    'already_submitted' => $reviewSubmitted,
                    'endpoint'          => AjaxHandler::generateUrl('reviews/create'),
                ])
                ->render();

        });


        add_shortcode('cart_icon', function () {
            $order = WooCommerce::getCartOrder();
            echo '<div id="headercart">
                <cart url=' . Checkout::$urls['getCartCount'] . '>
            </cart>
            </div>';

        });

        add_shortcode('checkout', function () {
            wc_get_template('checkout/form-checkout.php');
        });


        add_shortcode('product_meta', function () {
            wc_get_template('single-product/meta.php');
        });

        add_shortcode('product_attributes', function () {
            wc_get_template('single-product/attributes.php');
        });

        add_shortcode('product_recommendations', function () {
            wc_get_template('single-product/recommendations.php');
        });

        add_shortcode('product_ingredients', function () {
            wc_get_template('single-product/ingredients.php');
        });

    }

}
