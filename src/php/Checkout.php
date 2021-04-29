<?php

namespace Theme;

use Theme\Exceptions\CoteAtHomeException;
use Theme\Objects\GiftCard;
use Theme\Objects\Order;
use Theme\Utils\Hasher;
use Theme\Utils\Validate;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\RegisterAjax;
use WC_Data_Exception;

class Checkout implements GenieComponent
{


    /**
     * A list of urls needed for the checkout Page
     *
     * @var array
     */
    static $urls = [];



    public static function setup()
    {


        /**
         * Capture the woo commerce hooks and update our real order.
         */
        HookInto::action('woocommerce_add_to_cart')
            ->run(function ($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {

                $idToUse = $variation_id ? $variation_id : $product_id;

                $cartOrder = WooCommerce::getCartOrder();
                if ($cartOrder) {

                    $cartOrder->add_or_update_product($idToUse, $quantity, true);
                } else {
                    //Phase2: Show an error to the user
                }
                WC()->cart->empty_cart();
            });


        /**
         * This happens after the order button has been pressed.
         */
        HookInto::action('woocommerce_before_pay_action')
            ->run(function ($order) {
                Log::debug('woocommerce_before_pay_action');

                Order::find($order->get_id())
                    ->maybe_send_tracking_to_exponea(400);

            });

        /**
         * this must be after the cart is created
         *
         * @see WooCommerce::setup()
         */
        Hookinto::action('init', 20)
            ->run(function () {

                /**
                 * @var Callable[] $ajaxCalls
                 */
                $ajaxCalls = [

                    //Get order Data
                    [static::class, 'addCouponCode'],
                    [static::class, 'getCheckoutData'],
                    [static::class, 'getCartCount'],
                    [static::class, 'getDates'],
                    [static::class, 'removeCoupon'],
                    [static::class, 'removeGiftCard'],
                    [static::class, 'removeProduct'],
                    [static::class, 'saveCustomerData'],
                    [static::class, 'setShippingData'],
                    [static::class, 'updateProductQuantity'],
                    [static::class, 'validateOrder'],
                    [static::class, 'getCurrentGiftcardInfo'],
                ];

                /**
                 * All our ajax calls
                 */
                static::$urls = [];
                foreach ($ajaxCalls as $callable) {
                    $methodName = $callable[1];
                    $RequestPath = 'checkout/' . $methodName;
                    static::$urls[$methodName] = AjaxHandler::generateUrl($RequestPath);
                    RegisterAjax::url($RequestPath)
                        ->run($callable);
                }

            });

    }



    /**
     * Add a coupon code / Gift Card
     *
     * @param string $order_id
     * @param string $code
     * @param string $email
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function addCouponCode(string $order_id, string $code, string $email)
    {

        return Order::find(Hasher::decode($order_id))
            ->add_coupon(strtoupper($code), $email)
            ->get_checkout_data();
    }



    public static function getCartCount()
    {

        $order = WooCommerce::getCartOrder();

        if ($order) {
            return $order->get_item_count();
        }

        return 0;
    }



    /**
     * @param string $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function getCheckoutData(string $order_id)
    {
        return Order::find(Hasher::decode($order_id))
            ->maybe_send_tracking_to_exponea(100)
            ->get_checkout_data();
    }



    /**
     * @param string $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     *
     *
     */
    public static function getCurrentGiftcardInfo(string $cardnumber, string $balance)
    {
        return GiftCard::checkCardBalance($cardnumber, $balance);
    }



    /**
     * Figure out the dates for delivery
     *
     * @param string $order_id
     * @param string $postcode
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function getDates(string $order_id, string $postcode)
    {

        return Order::find(Hasher::decode($order_id))
            ->maybe_send_tracking_to_exponea(300)
            ->get_shipping_dates($postcode);
    }



    /**
     * @param string $order_id
     * @param string $code
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function removeCoupon(string $order_id, string $code)
    {

        wc_add_notice("Coupon $code removed");
        return Order::find(Hasher::decode($order_id))
            ->remove_coupon($code)
            ->get_checkout_data();
    }



    /**
     * @param string $order_id
     * @param string $cardNumber
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function removeGiftCard(string $order_id, string $cardNumber)
    {

        return Order::find(Hasher::decode($order_id))
            ->remove_gift_card($cardNumber)
            ->get_checkout_data();
    }



    /**
     * @param string $order_id
     * @param $product_id
     * @param $variation_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function removeProduct(string $order_id, $product_id, $variation_id)
    {
        $idToUse = $variation_id ? $variation_id : $product_id;
        return Order::find(Hasher::decode($order_id))
            ->add_or_update_product($idToUse, 0)
            ->get_checkout_data();

    }



    /**
     * @param string $order_id
     * @param $data
     *
     * @return mixed
     * @throws CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public static function saveCustomerData(string $order_id, $data)
    {

        $order = Order::find(Hasher::decode($order_id));

        $errors = [];

        $properties = [];

        if (!$data['customer']['firstName']) {
            $errors['firstName'] = 'Please enter your first name';
        } else {
            $properties['first_name'] = $data['customer']['firstName'];
            $order->set_billing_first_name($data['customer']['firstName']);
        }


        if (!$data['customer']['lastName']) {
            $errors['lastName'] = 'Please enter your last name';
        } else {
            $properties['last_name'] = $data['customer']['lastName'];
            $order->set_billing_last_name($data['customer']['lastName']);
        }

        $hasCustomerData = false;
        if ($data['customer']['email']) {

            $response = Validate::email($data['customer']['email']);
            if (!$response['valid']) {
                $errors['email'] = 'Please enter a valid email address';

            } else {
                $properties['email'] = $data['customer']['email'];
                $order->set_billing_email($data['customer']['email']);
                $hasCustomerData = true;
            }
        } else {
            $errors['email'] = 'Please enter an email address';
        }

        $order->update_meta_data('_has_customer_data', $hasCustomerData ? 1 : 0);

        if ($data['customer']['phone']) {
            $response = Validate::tel($data['customer']['phone']);
            if (!$response['valid']) {
                $errors['phone'] = 'Please enter a valid phone number';
            } else {
                $properties['phone'] = $data['customer']['phone'];
                $order->set_billing_phone($data['customer']['phone']);
            }
        } else {
            $errors['phone'] = 'Please enter a phone number';
        }

        if (!$data['billingAddress']['address1']) {
            $errors['billingAddress1'] = 'Please enter your address';
        } else {
            $order->set_billing_address_1($data['billingAddress']['address1']);
        }

        $order->set_billing_address_2($data['billingAddress']['address2']);

        if (!$data['billingAddress']['city']) {
            $errors['billingCity'] = 'Please enter your city name';
        } else {
            $order->set_billing_city($data['billingAddress']['city']);
        }

        $order->set_billing_state($data['billingAddress']['state']);

        if ($data['billingAddress']['postcode']) {
            $response = Validate::postcode($data['billingAddress']['postcode']);
            if (!$response['valid']) {
                $errors['billingPostcode'] = 'The postcode is invalid';
            } else {
                $order->set_billing_postcode($data['billingAddress']['postcode']);
            }
        } else {
            $errors['billingPostcode'] = 'Please enter a postcode';
        }

        $order->set_billing_country('GB');
        $order->set_billing_company($data['billingAddress']['company']);

        $deliverToDifferentAddress = isset($data['deliverToDifferentAddress']) && $data['deliverToDifferentAddress'];

        if ($deliverToDifferentAddress) {


            if (isset($data['shippingAddress']['phone'])) {
                $response = Validate::tel($data['shippingAddress']['phone']);
                if (!$response['valid']) {
                    $errors['shippingPhone'] = 'Please enter a valid phone number';
                } else {
                    $order->set_shipping_phone($data['shippingAddress']['phone']);
                }
            }

            if (!$data['shippingAddress']['firstName']) {
                $errors['shippingFirstName'] = 'Please enter your first name';
            } else {
                $order->set_shipping_first_name($data['shippingAddress']['firstName']);
            }

            if (!$data['shippingAddress']['lastName']) {
                $errors['shippingLastName'] = 'Please enter your last name';
            } else {
                $order->set_shipping_last_name($data['shippingAddress']['lastName']);
            }

            if (!$data['shippingAddress']['address1']) {
                $errors['shippingAddress1'] = 'Please enter your address';
            } else {
                $order->set_shipping_address_1($data['shippingAddress']['address1']);
            }

            $order->set_shipping_address_2($data['shippingAddress']['address2']);

            if (!$data['shippingAddress']['city']) {
                $errors['shippingCity'] = 'Please enter your city name';
            } else {
                $order->set_shipping_city($data['shippingAddress']['city']);
            }

            $order->set_shipping_state($data['shippingAddress']['state']);

            if ($data['shippingAddress']['postcode']) {
                $response = Validate::postcode($data['shippingAddress']['postcode']);
                if (!$response['valid']) {
                    $errors['shippingPostcode'] = 'The postcode is invalid';
                } else {
                    $order->set_shipping_postcode($data['shippingAddress']['postcode']);
                }
            } else {
                $errors['shippingPostcode'] = 'Please enter a postcode';
            }

            $order->set_shipping_country('GB');
            $order->set_shipping_company($data['shippingAddress']['company']);

        }

        $order->update_meta_data('_accepts_marketing', $data['customer']['acceptsMarketing']);
        $order->set_deliver_to_a_different_address($deliverToDifferentAddress);

        $order->save();

        if (isset($properties['email']) && $properties['email']) {
            Tracking::identify($properties['email']);
            Tracking::update($properties);

            $order->maybe_send_tracking_to_exponea(200);

        }

        // Update Shop Session
        $shopSession = $order->get_shop_session();
        $shopSession->added_details = true;
        $shopSession->save();

        return $order->get_checkout_data($errors);
    }



    /**
     * @param string $order_id
     * @param string $date // Y-m-d
     * @param int $delivery_company_id
     * @param int $delivery_area_id
     * @param float $amount
     * @param string $postcode
     * @param string $code
     * @param string $name
     * @param string $delivery_note
     * @param string $gift_message
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function setShippingData(string $order_id, string $date, int $delivery_company_id, int $delivery_area_id, float $amount, string $postcode, string $code, string $name, string $delivery_note, string $gift_message)
    {

        return Order::find(Hasher::decode($order_id))
            ->set_shipping_data($date, $delivery_company_id, $delivery_area_id, $amount, $postcode, $code, $name, $delivery_note, $gift_message)
            ->get_checkout_data();
    }



    /**
     * @param string $order_id
     * @param $product_id
     * @param $quantity
     * @param $variation_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function updateProductQuantity(string $order_id, $product_id, $quantity, $variation_id)
    {

        $idToUse = $variation_id ? $variation_id : $product_id;

        return Order::find(Hasher::decode($order_id))
            ->add_or_update_product($idToUse, $quantity)
            ->get_checkout_data();
    }



    /**
     * Validate the order - make sure the date is still available
     *
     * @param string $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function validateOrder(string $order_id)
    {
        Order::find(Hasher::decode($order_id))
            ->validate_order_before_checkout();

    }

}
