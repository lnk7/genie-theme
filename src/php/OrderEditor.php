<?php

namespace Theme;

use Theme\Exceptions\CoteAtHomeException;
use Theme\Objects\Order;
use Theme\Objects\Product;
use Theme\Utils\Time;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Debug;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Request;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\RegisterAjax;
use Lnk7\Genie\View;
use WC_Data_Exception;
use WP_Query;

class OrderEditor implements GenieComponent
{


    public static function setup()
    {

        /**
         * @var Callable[] $ajaxCalls
         */
        $ajaxCalls = [

            //Get order Data
            [static::class, 'getOrderData'],

            //Main Operations
            [static::class, 'cancelOrder'],
            [static::class, 'refundOrder'],
            [static::class, 'chargeOrder'],
            [static::class, 'deleteOrder'],
            [static::class, 'duplicateOrder'],
            [static::class, 'markAsPaid'],

            //Editing
            [static::class, 'startEditing'],
            [static::class, 'finishEditing'],
            [static::class, 'cancelEditing'],

            //Customer
            [static::class, 'updateCustomer'],
            [static::class, 'updateBillingAddress'],
            [static::class, 'updateShippingAddress'],
            [static::class, 'createGiftCard'],

            //Shipping
            [static::class, 'getDates'],
            [static::class, 'setShippingData'],
            [static::class, 'addFreeShipping'],

            //products & items
            [static::class, 'getProducts'],
            [static::class, 'addProduct'],
            [static::class, 'updateProductQuantity'],
            [static::class, 'updateProductPrice'],
            [static::class, 'removeProduct'],

            //Coupons & Gift Cards
            [static::class, 'addCouponCode'],
            [static::class, 'removeGiftCard'],
            [static::class, 'removeCoupon'],

            // Other Operations
            [static::class, 'addNote'],
            [static::class, 'addRefund'],
            [static::class, 'updateTags'],

            //Emails
            [static::class, 'sendEmailConfirmation'],
            [static::class, 'sendRecoverLink'],
            [static::class, 'sendPaymentLink'],
            [static::class, 'sendDeliverySlot'],
            [static::class,'sendRegularPDFInvoice'],

            [static::class, 'updateCustomerTags'],

            // Tagging of customers
            // Add old customer orders to generate_order_data

        ];

        /**
         * All our ajax calls
         */
        $urls = [];
        foreach ($ajaxCalls as $callable) {
            $methodName = $callable[1];
            $url = 'orderEditor/' . $methodName;
            $urls[$methodName] = AjaxHandler::generateUrl($url);
            RegisterAjax::url($url)
                ->run($callable);
        }

        /**
         * Get our scripts going when required
         */
        HookInto::action('admin_enqueue_scripts')
            ->run(function () {
                $screen = get_current_screen();

                if ($screen->base === 'admin_page_cah_order_editor') {
                    wp_enqueue_script('cah-admin-js', get_stylesheet_directory_uri() . '/dist/backend.js', [], null, true);
                    wp_enqueue_style('cah-admin-css', get_stylesheet_directory_uri() . '/dist/backend.css');
                }
            });

        /**
         *  Add our order Editor page.  Using null as the parent_slug does not add an option to the Wordpress Menu
         */
        HookInto::action('admin_menu')
            ->run(function () use ($urls) {

                add_submenu_page(null, 'Order Editor', 'menu', 'shop_user', 'cah_order_editor', function () use ($urls) {

                    $id = Request::get('id');

                    if (!$id) {
                        $order = new Order();
                        $order->set_date_created(Time::utcTimestamp());
                        $order->set_status(WooCommerce::orderIsPendingPayment);
                        $order->save();

                        $id = $order->get_id();

                    }

                    View::with('admin/woocommerce/orderEditor.twig')
                        ->addVar('order_id', $id)
                        ->addVar('urls', $urls)
                        ->display();

                });
            });

        /**
         * Bypass the woo commerce screens in favour of our own.
         */
        HookInto::action('current_screen')
            ->run(function () {
                $screen = get_current_screen();

                //Phase2: - Our own list of Orders
                if ($screen->id === 'edit-shop_order') {

                }
                // order edit page
                if ($screen->id === 'shop_order') {

                    // redirect to our own page
                    $id = Request::get('post');
                    $url = '/wp-admin/admin.php?page=cah_order_editor&id=' . $id;
                    wp_redirect($url, 302);
                    exit;

                }
            });

        /**
         * Search meta data !
         */
        HookInto::filter('woocommerce_shop_order_search_fields')
            ->run(function ($fields) {

                $custom_fields = [
                    "_billing_address_index",
                    "_shipping_address_index",
                    "_billing_first_name",
                    "_billing_last_name",
                    "_billing_email",
                    "_billing_phone",
                    "_shipping_first_name",
                    "_shipping_last_name",
                ];
                return array_merge($fields, $custom_fields);

            });

        HookInto::filter('woocommerce_orders_admin_list_table_filters')->run(

            function ($filters) {

                Debug::dd($filters);
                // to remove the category filter
                if (isset($filters['product_category'])) {
                    unset($filters['product_category']);
                }

                // to remove the product type filter
                if (isset($filters['product_type'])) {
                    unset($filters['product_type']);
                }

                // to remove the stock filter
                if (isset($filters['stock_status'])) {
                    unset($filters['stock_status']);
                }

                return $filters;
            });

        HookInto::action('pre_get_posts')
            ->run(function (WP_Query $query) {

                global $typenow;

                if (!is_admin() || $typenow !== 'shop_order') {
                    return $query;
                }

                $post_status = $_GET['post_status'] ?? '';
                if (!$post_status) {
                    $query->set('post_status', ['wc-' . WooCommerce::orderIsPendingPayment, 'wc-' . WooCommerce::orderHasBeenPaid]);
                }

                return $query;

            });

        // Remove the Hash from order Search
        HookInto::filter('request')
            ->run(function ($request) {

                global $typenow;

                if (!is_admin() || $typenow !== 'shop_order') {
                    return $request;
                }

                if (isset($request['s']) && preg_match('/^#[0-9]+$/', $request['s'])) {
                    $_GET['s'] = ltrim($request['s'], '#');
                }
                return $request;

            });

    }


    public static function sendRegularPDFInvoice(int $order_id ){
        return Order::find($order_id)
            ->create_pdf_invoice();
    }


    /**
     * Adds a coupon to the order
     *
     * @param int $order_id
     * @param string $code
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function addCouponCode(int $order_id, string $code)
    {
        return Order::find($order_id)
            ->add_coupon(strtoupper($code))
            ->generate_order_data();
    }


    /**
     * Make shipping free
     *
     * @param int $order_id
     * @param string $note
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function addFreeShipping(int $order_id, string $note)
    {
        return Order::find($order_id)
            ->add_free_shipping($note)
            ->generate_order_data();
    }


    /**
     * Add a gift card to the order
     *
     * @param int $order_id
     * @param string $cardNumber
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function addGiftCard(int $order_id, string $cardNumber)
    {
        return Order::find($order_id)
            ->add_gift_card($cardNumber)
            ->generate_order_data();
    }


    /**
     * Remove a gift card from the order
     *
     * @param int $order_id
     * @param string $note
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function addNote(int $order_id, string $note)
    {
        return Order::find($order_id)
            ->add_note($note)
            ->save()
            ->generate_order_data();
    }


    /**
     * Add an item to the cart
     *
     * @param int $order_id
     * @param int $product_id
     * @param int $quantity
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function addProduct(int $order_id, int $product_id, int $quantity)
    {
        return Order::find($order_id)
            ->add_or_update_product($product_id, $quantity, true)
            ->generate_order_data();
    }


    /**
     * Refund an amount fort this Order
     *
     * @param int $order_id
     * @param float $amount
     * @param string $note
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function addRefund(int $order_id, float $amount, string $note)
    {
        return Order::find($order_id)
            ->refund($amount, $note)
            ->generate_order_data();
    }


    /**
     * @param int $order_id
     *
     * @return array
     * @throws CoteAtHomeException|WC_Data_Exception
     */
    public static function cancelEditing(int $order_id)
    {
        return Order::find($order_id)
            ->leave_edit_mode(false)
            ->generate_order_data();
    }


    /**
     * $refund only needed if the order orderData subtotal.payments > 0
     * TODO: refund order if $refund
     *
     * @param int $order_id
     * @param string $note
     * @param bool $refund
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function cancelOrder(int $order_id, string $note, bool $refund)
    {
        return Order::find($order_id)
            ->cancel($note, $refund)
            ->generate_order_data();
    }


    /**
     * Add a gift card
     *
     * @param int $customer_id
     * @param float $amount
     */
    public static function createGiftCard(int $customer_id, float $amount)
    {

        if (!current_user_can(WooCommerce::CanCreateAGiftCard)) {
            throw CoteAtHomeException::withMessage('You do not have permission to do this');
        }
        wc_add_notice('Gift card created and sent');
        return [
            'success' => true,
            'message' => 'Gift card created',
        ];
    }



    /**
     * @param int $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function deleteOrder(int $order_id)
    {
        return Order::find($order_id)
            ->delete();
    }


    /**
     * @param int $order_id
     * @param bool $free
     *
     * @return array
     * @throws CoteAtHomeException|WC_Data_Exception
     */
    public static function duplicateOrder(int $order_id, $free = false)
    {
        return Order::find($order_id)
            ->duplicate()
            ->generate_order_data();
    }


    /**
     * Finish Editing - leave the order edit mode, and delete the original Order
     *
     * @param int $order_id
     *
     * @return array
     * @throws CoteAtHomeException|WC_Data_Exception
     */
    public static function finishEditing(int $order_id)
    {
        return Order::find($order_id)
            ->leave_edit_mode(true)
            ->generate_order_data();
    }


    /**
     * Figure out the dates for delivery
     *
     * @param int $order_id
     * @param string $postcode
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function getDates(int $order_id, string $postcode)
    {

        return Order::find($order_id)
            ->get_shipping_dates($postcode);

    }


    /**
     * @param $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function getOrderData(int $order_id)
    {
        return Order::find($order_id)
            ->generate_order_data();
    }


    /**
     *  Get all products
     */
    public static function getProducts()
    {
        return Product::getProducts();
    }


    /**
     * @param int $order_id
     *
     * @return mixed
     * @throws CoteAtHomeException
     */
    public static function markAsPaid(int $order_id)
    {
        return Order::find($order_id)
            ->mark_as_paid()
            ->generate_order_data();

    }


    /**
     * Refund an Entire order;
     *
     * @param int $order_id
     * @param string $reason
     * @param float $amount (0 = refund in full)
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function refundOrder(int $order_id, string $reason, float $amount)
    {

        return Order::find($order_id)
            ->refund($amount, $reason)
            ->generate_order_data();
    }


    /**
     * Charge a customer;
     *
     * @param int $order_id
     * @param string $reason
     * @param float $amount (0 = refund in full)
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function chargeOrder(int $order_id, string $reason, float $amount)
    {

        return Order::find($order_id)
            ->charge($amount, $reason)
            ->generate_order_data();
    }


    /**
     * Remove a coupon from the order
     *
     * @param int $order_id
     * @param string $code
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function removeCoupon(int $order_id, string $code)
    {
        return Order::find($order_id)
            ->remove_coupon(strtoupper($code))
            ->generate_order_data();
    }


    /**
     * Remove a gift card from the order
     *
     * @param int $order_id
     * @param string $cardNumber
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function removeGiftCard(int $order_id, string $cardNumber)
    {
        return Order::find($order_id)
            ->remove_gift_card($cardNumber)
            ->generate_order_data();
    }


    /**
     * Remove an Item from the cart
     *
     * @param int $order_id
     * @param int $product_id
     * @param int $variation_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function removeProduct(int $order_id, int $product_id, int $variation_id)
    {

        $idToUse = $variation_id ? $variation_id : $product_id;

        return Order::find($order_id)
            ->add_or_update_product($idToUse, 0)
            ->generate_order_data();
    }


    /**
     * @param int $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function sendDeliverySlot(int $order_id)
    {
        return Order::find($order_id)
            ->send_delivery_slot_email()
            ->generate_order_data();
    }


    /**
     * @param int $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function sendEmailConfirmation(int $order_id)
    {
        return Order::find($order_id)
            ->send_email()
            ->generate_order_data();
    }


    /**
     * @param int $order_id
     *
     * @return mixed
     * @throws CoteAtHomeException
     */
    public static function sendPaymentLink(int $order_id)
    {
        return Order::find($order_id)
            ->send_email()
            ->generate_order_data();

    }


    /**
     * @param int $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function sendRecoverLink(int $order_id)
    {
        return Order::find($order_id)
            ->send_email('Abandoned')
            ->generate_order_data();
    }


    /**
     * @param int $order_id
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
    public static function setShippingData(int $order_id, string $date, int $delivery_company_id, int $delivery_area_id, float $amount, string $postcode, string $code, string $name, string $delivery_note = '', string $gift_message = '')
    {

        return Order::find($order_id)
            ->set_shipping_data($date, $delivery_company_id, $delivery_area_id, $amount, $postcode, $code, $name, $delivery_note, $gift_message)
            ->generate_order_data();
    }


    /**
     * @param int $order_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function startEditing(int $order_id)
    {
        return Order::find($order_id)
            ->enter_edit_mode()
            ->generate_order_data();
    }


    /**
     * Update the Billing Address
     *
     * @param int $order_id
     * @param string $company
     * @param string $address_1
     * @param string $address_2
     * @param string $city
     * @param string $state
     * @param string $postcode
     * @param string $country
     *
     * @return array
     * @throws CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public static function updateBillingAddress(int $order_id, string $company, string $address_1, string $city, string $postcode, string $address_2 = '', string $state = '', string $country = 'GB')
    {
        return Order::find($order_id)
            ->set_billing_address($company, $address_1, $address_2, $city, $state, $postcode, $country)
            ->generate_order_data();
    }


    /**
     * Update the customer's details
     *
     * @param int $order_id
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @param string $phone
     *
     * @return array
     * @throws WC_Data_Exception
     * @throws CoteAtHomeException
     */
    public static function updateCustomer(int $order_id, string $first_name, string $last_name, string $email, string $phone)
    {
        return Order::find($order_id)
            ->set_customer($first_name, $last_name, $email, $phone)
            ->generate_order_data();
    }


    /**
     * @param int $order_id
     * @param array $tags // ['vip','priority'] etc
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function updateCustomerTags(int $order_id, array $tags)
    {
        return Order::find($order_id)
            ->set_customer_tags($tags)
            ->generate_order_data();
    }


    /**
     * Make a product Free
     *
     * @param int $order_id
     * @param int $product_id
     * @param int $variation_id
     * @param float $price
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function updateProductPrice(int $order_id, int $product_id, int $variation_id, float $price = 0)
    {

        $idToUse = $variation_id ? $variation_id : $product_id;

        return Order::find($order_id)
            ->update_product_price($idToUse, $price)
            ->generate_order_data();
    }


    /**
     * Add an item to the cart
     *
     * @param int $order_id
     * @param int $product_id
     * @param int $quantity
     * @param int $variation_id
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function updateProductQuantity(int $order_id, int $product_id, int $quantity, int $variation_id)
    {
        $idToUse = $variation_id ? $variation_id : $product_id;

        return Order::find($order_id)
            ->add_or_update_product($idToUse, $quantity)
            ->generate_order_data();
    }


    /**
     * Update the Shipping Address
     *
     * @param int $order_id
     * @param string $first_name
     * @param string $last_name
     * @param string $phone
     * @param string $company
     * @param string $address_1
     * @param string $city
     * @param string $postcode
     * @param string $address_2
     * @param string $state
     * @param string $country
     *
     * @return array
     * @throws CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public static function updateShippingAddress(int $order_id, string $first_name, string $last_name, string $phone, string $company, string $address_1, string $city, string $postcode, string $address_2 = '', string $state = '', string $country = 'GB')
    {
        return Order::find($order_id)
            ->set_shipping_address($first_name, $last_name, $company, $address_1, $address_2, $city, $state, $postcode, $country , $phone)
            ->generate_order_data();
    }


    /**
     * @param int $order_id
     * @param array $tags // ['vip','priority'] etc
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public static function updateTags(int $order_id, array $tags)
    {
        return Order::find($order_id)
            ->set_tags($tags)
            ->generate_order_data();
    }

}
