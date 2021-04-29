<?php

namespace Theme;

use Theme\Objects\Order;
use Theme\Objects\ShopSession;
use Theme\Parsers\ExponeaParser;
use Theme\Parsers\RefererParser;
use Theme\Parsers\UserAgentParser;
use Theme\Parsers\UtmParser;
use Theme\Utils\Hasher;
use Theme\Utils\Time;
use Exception;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Session;
use Lnk7\Genie\Utilities\HookInto;
use Throwable;
use Twig\Environment;
use Twig\TwigFunction;
use WC_Data_Exception;
use WC_Order;

class WooCommerce implements GenieComponent
{


    const CanMakeAProductFree = 'shop_user_plus';


    const CanCreateAGiftCard = 'shop_user_plus';


    const orderIsBeingEdited = 'editing';


    const orderIsACart = 'cart';


    const orderIsPendingPayment = 'pending';


    const orderHasBeenPaid = 'processing';


    const orderHasBeenProduced = 'produced';


    const orderHasBeenFulfilled = 'completed';


    const orderHasBeenCancelled = 'cancelled';


    const orderHasFailedPayment = 'failed';


    const orderTaggedAsFraudulent = 'fraud';


    public static function setup()
    {

        /**
         * Create our new order statuses
         */
        HookInto::action('init')
            ->run(function () {

                register_post_status('wc-' . static::orderIsACart, [
                    'label'                     => 'Cart',
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Carts (%s)', 'Carts (%s)'),
                ]);

                register_post_status('wc-' . static::orderHasBeenProduced, [
                    'label'                     => 'Produced',
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Produced (%s)', 'Produced  (%s)'),
                ]);

                register_post_status('wc-' . static::orderIsBeingEdited, [
                    'label'                     => 'Editing',
                    'public'                    => true,
                    'exclude_from_search'       => false,
                    'show_in_admin_all_list'    => true,
                    'show_in_admin_status_list' => true,
                    'label_count'               => _n_noop('Being Edited (%s)', 'Being Edited (%s)'),
                ]);

            });

        /**
         * Tell Woo Commerce about our statuses
         */
        HookInto::filter('woocommerce_register_shop_order_post_statuses')
            ->run(function ($order_statuses) {

                $statuses = static::getOrderStatuses();

                foreach ($statuses as $key => $status) {

                    if (isset($order_statuses[$key])) {
                        $order_statuses[$key]['label'] = $status;
                        $order_statuses[$key]['label_count'] = _n_noop("$status (%s)", "$status (%s)");
                    }

                }
                return $order_statuses;
            });

        /**
         * Only orders with these status's can be marked as complete
         */
        HookInto::filter('woocommerce_valid_order_statuses_for_payment_complete')->run(function () {
            return [
                static::orderIsACart, static::orderIsPendingPayment, static::orderHasFailedPayment, static::orderHasBeenCancelled,
            ];
        });

        /**
         * Only orders with these status's can be paid for
         */
        HookInto::filter('woocommerce_valid_order_statuses_for_payment')
            ->run(function () {
                return [static::orderIsACart, static::orderIsPendingPayment, static::orderHasFailedPayment];
            });

        /**
         * Set which types of orders can be edited
         */
        HookInto::filter('wc_order_is_editable', 20)
            ->run(function ($editable, WC_Order $order) {

                if (in_array($order->get_status(), [static::orderIsACart, static::orderHasBeenPaid, static::orderIsPendingPayment, static::orderIsBeingEdited, static::orderHasFailedPayment])) {
                    return true;
                }

                return false;
            });

        /**
         * Tell woo commerce which statuses to use.
         */
        HookInto::filter('wc_order_statuses')
            ->run(function ($statuses) {
                return static::getOrderStatuses();
            });

        /**
         * Set our Cart cookie. a Hash of the order ID. This only happens on template_redirect, so it works on the front end.
         */
        HookInto::filter('template_redirect')
            ->run(function () {

                //Setup our Cart / Cookie
                static::setCookie();

            });


        /**
         * handle our redirects. The priority is important
         */
        HookInto::action('init', 30)
            ->run(function () {

                global $wp;

                /**
                 * Order invoice
                 */
                if (isset($_GET['order-invoice']) && $_GET['order-invoice']) {

                    $order_id = Hasher::decode(sanitize_text_field($_REQUEST['order-invoice']));
                    if (!$order_id) {
                        return;
                    }

                    $order = new Order($order_id);
                    if ($order) {
                        $order->create_pdf_invoice();
                    }
                    return;
                }


                /**
                 * order needs to be paid
                 */
                if (isset($wp->query_vars['order-pay']) && $wp->query_vars['order-pay']) {

                    $order_id = $wp->query_vars['order-pay'];
                    $order = new Order($order_id);
                    if (!in_array($order->get_status(), [static::orderIsACart, static::orderIsPendingPayment, static::orderHasFailedPayment])) {
                        return;
                    }

                    static::setCookie(Hasher::encode($order_id));
                    wp_redirect(wc_get_checkout_url());

                }

                /**
                 * Continue checkout.
                 */
                if (isset($_REQUEST['continue_order']) && $_REQUEST['continue_order']) {
                    $orderHash = $_REQUEST['order'];

                    $orderHash = sanitize_text_field($orderHash);

                    $order_id = Hasher::decode($orderHash);
                    if (!$order_id) {
                        return;
                    }

                    $order = new Order($order_id);
                    if (!in_array($order->get_status(), [static::orderIsACart, static::orderIsPendingPayment])) {
                        return;
                    }

                    static::setCookie($orderHash);

                    wp_redirect(wc_get_checkout_url());
                    exit;
                }

                /**
                 * if an order has as 0 value, but has been completed.
                 */
                if (isset($_REQUEST['order_complete']) && $_REQUEST['order_complete']) {
                    $orderHash = $_REQUEST['order'];

                    $orderHash = sanitize_text_field($orderHash);

                    $order_id = Hasher::decode($orderHash);
                    if (!$order_id) {
                        return;
                    }

                    $order = new Order($order_id);
                    if ($order->get_total() > 0) {
                        return;
                    }

                    $order->set_status('processing');
                    $order->save();

                    $url = $order->get_checkout_order_received_url();
                    wp_redirect($url);
                    exit;

                }

            });

        /**
         * Add the wc_price filter to twig
         *
         * usage:
         * {{ wc_price(amount) }}
         */
        HookInto::filter('genie_view_twig')
            ->run(function (Environment $twig) {
                $function = new TwigFunction('wc_price', 'wc_price');
                $twig->addFunction($function);
                return $twig;
            });

        /**
         * Remove woo nonsense
         * Phase2: There's more to do here
         */
        HookInto::action('wp_enqueue_scripts', 2000)
            ->run(function () {
                wp_dequeue_script('wc-checkout');
                wp_deregister_script('jquery-payment');
                wp_deregister_script('wc-credit-card-form');
            });

        /**
         * More woo Nonsense in the admin section
         */
        HookInto::action('admin_enqueue_scripts', 2000)
            ->run(function () {
                wp_deregister_style('wc-admin-marketing-coupons');
                wp_deregister_script('wc-admin-marketing-coupons');
            });

        /**
         * Control options in the multi edit dropdown
         */
        HookInto::filter('bulk_actions-edit-shop_order', 100)
            ->run(function ($items) {

                $array = [
                    'trash' => 'Move to Bin',
                ];
                if (current_user_can('shop_user_plus')) {
                    $array['mark_processing'] = 'Mark as Paid';

                }
                if (current_user_can('manage_options')) {
                    $array['mark_completed'] = 'Mark as Fulfilled';
                }
                return $array;

            });

        /**
         * Show all products on the frontend  without paging
         */
        HookInto::filter('loop_shop_per_page', 20)
            ->run(function () {
                return 999;
            });

        /**
         * Add barcode to Products
         */
        HookInto::action('woocommerce_product_options_inventory_product_data')
            ->run(function () {
                global $post;
                woocommerce_wp_text_input([

                    'wrapper_class' => 'form-field',
                    'data_type'     => 'decimal',
                    'id'            => '_barcode',
                    'label'         => 'Barcode',
                    'placeholder'   => '123456',
                    'desc_tip'      => true,
                    'type'          => 'text',
                    'description'   => 'Enter the barcode',
                    'value'         => $post->ID ? get_post_meta($post->ID, '_barcode', true) : '',
                ]);

            });

        /**
         * Save barcodes for products
         */
        HookInto::action('woocommerce_process_product_meta')
            ->run(function ($post_id) {
                $barcode = $_POST['_barcode'];
                if (!empty($barcode)) {
                    update_post_meta($post_id, '_barcode', esc_html($barcode));
                }
            });

        /**
         * Add barcodes for Product Variations
         */
        HookInto::action('woocommerce_product_after_variable_attributes')
            ->run(function ($loop, $variation_data, $variation) {

                // Text Field
                woocommerce_wp_text_input([

                    'wrapper_class' => 'form-field form-row',
                    'data_type'     => 'decimal',
                    'id'            => '_barcode[' . $variation->ID . ']',
                    'label'         => 'Barcode',
                    'placeholder'   => '123456',
                    'desc_tip'      => true,
                    'type'          => 'text',
                    'description'   => 'Enter the barcode',
                    'value'         => get_post_meta($variation->ID, '_barcode', true),
                ]);

            });

        /**
         * Save barcodes for product Variations
         */
        HookInto::action('woocommerce_save_product_variation')
            ->run(function ($post_id) {

                // Text Field
                $barcode = $_POST['_barcode'][$post_id];
                if (!empty($barcode)) {
                    update_post_meta($post_id, '_barcode', esc_attr($barcode));
                }
            });

        /**
         * Hide the short description from the admin backend
         */
        HookInto::action('add_meta_boxes', 999)
            ->run(function () {
                remove_meta_box('postexcerpt', 'product', 'normal');
            });

    }


    /**
     * These are our valid order statuses.
     *
     * @return string[]
     */
    public static function getOrderStatuses()
    {
        return [
            'wc-' . static::orderIsBeingEdited      => 'Being Edited',
            'wc-' . static::orderIsACart            => 'Being Ordered',
            'wc-' . static::orderIsPendingPayment   => 'Ordered',
            'wc-' . static::orderHasBeenPaid        => 'Paid',
            'wc-' . static::orderHasBeenProduced    => 'Produced',
            'wc-' . static::orderHasBeenFulfilled   => 'Fulfilled',
            'wc-' . static::orderHasBeenCancelled   => 'Cancelled',
            'wc-' . static::orderHasFailedPayment   => 'Failed',
            'wc-' . static::orderTaggedAsFraudulent => 'Fraud',
        ];
    }


    /**
     * Set the cart cookie.
     *
     * @param string $hash  - we ca use this to "force" a cart on a user
     *
     * @throws WC_Data_Exception
     */
    public static function setCookie($hash = '')
    {

        if (!$hash) {
            // Do we have a Cart Hash ?
            $hash = $_COOKIE['cah_cart'] ?? false;
        }

        // let's assume that we need to create a new order for the cart.
        $createOrder = true;

        if ($hash) {

            $orderID = Hasher::decode($hash);

            if ($orderID) {
                try {
                    $order = new Order($orderID);

                    // Is the order still valid to appear in the cart ?
                    if ($order && in_array($order->get_status(), ['cart', 'pending', 'failed'])) {
                        // Yep, let's not create a new cart
                        $createOrder = false;
                    }
                } catch (Exception $e) {
                    // The order has been deleted, there is nothing to do.
                }
            }
        }

        // Create a new Cart Order
        if ($createOrder) {
            $order = new Order();
            $order->set_date_created(Time::utcTimestamp());
            $order->set_status('cart');
            $order->save();
        }

        $hash = Hasher::encode($order->get_id());
        setcookie('cah_cart', $hash, time() + 365 * 24 * 60 * 60, COOKIEPATH, COOKIE_DOMAIN);

        // Only do this if we're in development
        if (Theme::inDevelopment()) {
            setcookie('cah_cart_id', $order->get_id(), time() + 365 * 24 * 60 * 60, COOKIEPATH, COOKIE_DOMAIN);
        }

        //Save this to our session too.
        Session::set('cah_cart', $order->get_id());

        // Initialise the shop session.
        $shopSession = $order->get_shop_session();

        $referrer = $_SERVER['HTTP_REFERER'] ?? null;

        // we only care about referrers from other sites
        if ($referrer && strpos($referrer, 'coteathome.co.uk') === false) {
            $shopSession->referrer = $referrer;
            $shopSession->fill(RefererParser::parse($referrer));
        }

        // Grab the landing Page
        $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $shopSession->landing_page = $url;

        // Parse any utm tags
        //TODO: move this to shop session processing
        $shopSession->fill(UtmParser::parse($url));
        $shopSession->ip_address = $_SERVER['REMOTE_ADDR'];
        $shopSession->user_agent = $_SERVER['HTTP_USER_AGENT'];
        $shopSession->cart_created_date = $order->get_date_created()->format('Y-m-d H:i:s');
        $shopSession->save();

    }


    /**
     * useful tool to get the name of a status.
     *
     * @param $status
     *
     * @return string
     */
    public static function getStatusName($status)
    {
        $status = 'wc-' . preg_replace('/^' . preg_quote('wc-', '/') . '/', '', $status);

        $statuses = static::getOrderStatuses();
        if (array_key_exists($status, $statuses)) {
            return $statuses[$status];
        }

        return $status;

    }


    /**
     * Does what it says on the tin :)
     * Phase2: Convert to a Setting
     */
    public static function deleteOldCartsWithNoCustomerOrOrderData()
    {

        global $wpdb;

        set_time_limit(0);

        Log::info('Cart Cleanup Started');

        $date = Time::utcNow()->subDays(2)->endOfDay()->format('Y-m-d H:i:s');

        $results = $wpdb->get_results("
            select   
                ID 
            from
                wp_posts 
            where
                post_type = 'shop_order'
                and
                post_status = 'wc-cart'
                and
                post_modified_gmt < '$date'
                and
                ID not in (select distinct order_id from wp_woocommerce_order_items)
                and
                ID not in (select post_id from wp_postmeta where meta_key = '_has_customer_data'  and meta_value = 1)
            limit 20000
        ");

        foreach ($results as $result) {
            try {
                $order = Order::find($result->ID)

                    // Make sure we save it !
                    ->update_shop_session()

                    // goodbye :)
                    ->delete(true);

            } catch (Throwable $e) {
                Log::debug('deleteOldCartsWithNoCustomerOrOrderData:'.$e->getMessage());
            }
            unset($order);
        }

        Log::info('Cart Cleanup Complete');

    }

    /**
     * Does what it says on the tin daily! :)
     */
    public static function abandonedCartCleanup()
    {

        global $wpdb;

        set_time_limit(0);

        Log::info('Abandoned Cart Cleanup Started');

        $retentionLimit = Settings::get('cart_expiry', 30);

        $date = Time::utcNow()->subDays($retentionLimit)->endOfDay()->format('Y-m-d H:i:s');

        $results = $wpdb->get_results("
            select   
                ID 
            from
                wp_posts 
            where
                post_type = 'shop_order'
                and
                post_status = 'wc-cart'
                and
                post_modified_gmt < '$date'
            limit 2000
        ");

        foreach ($results as $result) {
            try {
                $order = Order::find($result->ID)

                    // Make sure we save it !
                    ->update_shop_session()

                    // goodbye :)
                    ->delete(true);

            } catch (Throwable $e) {
                Log::debug('abandonedCartCleanup:'.$e->getMessage());
            }
            unset($order);
        }

        Log::info('Abandoned Cart Cleanup Complete');

    }


    /**
     * @param $shopifyID
     *
     * @return string|null
     */
    public static function findProductVariationIDByShopifyID($shopifyID)
    {
        return static::findProductIDByShopifyID($shopifyID, 'product_variation');
    }


    /**
     * @param string $shopifyGid
     * @param string $type
     *
     * @return string|null
     */
    public static function findProductIDByShopifyID($shopifyGid, $type = 'product')
    {
        global $wpdb;
        $query = "
            SELECT 
                post_id   
            FROM 
                $wpdb->postmeta 
            WHERE 
                post_id in (select ID from $wpdb->posts where post_type = '$type' ) 
                and 
                meta_key = '_shopify_id' 
                and 
                meta_value = '$shopifyGid'";
        return $wpdb->get_var($query);
    }


    /**
     * get the cart Object
     *
     * @return Order|false
     */
    public static function getCartOrder()
    {
        $cartID = Session::get('cah_cart', false);
        if ($cartID) {
            return new Order($cartID);
        }
        return false;
    }


    /**
     * process Abandoned Carts
     *
     * @throws Exceptions\CoteAtHomeException
     */
    public static function processAbandonedCarts()
    {

        Log::info('processAbandonedCarts start');

        $order_ids = get_posts([
            'post_status' => 'wc-cart',
            'post_type'   => 'shop_order',
            'fields'      => 'ids',
            'date_query'  => [
                [
                    'column' => 'post_modified_gmt',
                    'before' => '3 hours ago',
                ],
            ],
            'meta_query'  => [
                [
                    'key'     => '_sent_abandoned_cart_event',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'   => '_has_customer_data',
                    'value' => 1,
                ],
            ],
        ]);

        foreach ($order_ids as $order_id) {
            Log::info("Sending Abandoned Cart for : $order_id ");
            Order::find($order_id)
                ->send_abandoned_cart_email()
                ->add_note('An abandoned cart email was sent')
                ->save();
        }

        Log::info('processAbandonedCarts end');

    }


    public static function cleanShopSessions()
    {
        // '.ico'
        // 'touch-icon'
    }


    /**
     * go through shop Sessions and process data
     *
     * @param int $limit
     */
    public static function processShopSessions($limit = 10000)
    {

        set_time_limit(0);

        global $wpdb;


        $table = ShopSession::getTableName();

        // Mark records as processed that don't need to be processed
        $wpdb->query("update wp_shop_sessions set processed = 1  where user_agent is NULL and email is NULL");
        $wpdb->query("update wp_shop_sessions set processed = 1  where birth_date is not NULL or device_type is NOT NULL");

        // Update new customers
        $wpdb->query("update wp_shop_sessions A LEFT JOIN wp_shop_sessions B ON A.email = B.email AND A.email is not null AND B.email is not null AND B.order_id < A.order_id SET A.new_customer = IF(B.order_id is NULL, 1,0)");

        $results = $wpdb->get_results("select order_id from wp_shop_sessions where processed is NULL and (user_agent is not NULL or email is not NULL) limit $limit");

        try {
            foreach ($results as $result) {
                $shopSession = new ShopSession($result->order_id);

                if ($shopSession->user_agent && !$shopSession->device_type) {
                    $shopSession->fill(UserAgentParser::parse($shopSession->user_agent));
                }
                if ($shopSession->email && !$shopSession->birth_date) {
                    $shopSession->fill(ExponeaParser::parse($shopSession->email));
                }

                $shopSession->processed = 1;
                $shopSession->save();
            }
        } catch (Throwable $e) {
            Log::error('processShopSessions: ' . $e->getMessage());
        }

    }


    /**
     * @throws Exceptions\CoteAtHomeException
     */
    public static function recalculateOrderDeliveryDates()
    {

        $orderIDs = get_posts([
            'post_type'   => 'shop_order',
            'post_status' => 'any',
            'fields'      => 'ids',
            'numberposts' => -1,
        ]);

        foreach ($orderIDs as $orderID) {
            Order::find($orderID)
                ->update_order_delivery_meta()
                ->maybe_assign_to_an_event();
        }

    }


    /**
     * Roll back all orders that have expired their editing time.
     *
     * @throws Exceptions\CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public static function revertEditedOrders()
    {

        Log::info('revertEditedOrders start');

        $order_ids = get_posts([
            'post_type'   => 'shop_order',
            'post_status' => 'wc-editing',
            'fields'      => 'ids',
        ]);

        foreach ($order_ids as $order_id) {
            $order = new Order($order_id);
            $originalOrder = $order->get_original_order();
            if ($originalOrder->revertsAt < Time::utcTimestamp()) {
                Log::info("reverting $order_id");
                $order->add_order_note('This order was reverted as the editing window has expired.');
                $order->leave_edit_mode();
            }
        }
        Log::info('revertEditedOrders end');
    }


    /**
     * Mark orders accordingly.
     *
     * @throws Exceptions\CoteAtHomeException
     */
    public static function startOrderProcessing()
    {

        set_time_limit(0);

        global $wpdb;

        $willBeProduced = Time::now()->endOfDay()->addDays(2)->format('Y-m-d');
        $willBeFulfilled = Time::now()->format('Y-m-d');

        $status = 'wc-' . WooCommerce::orderHasBeenPaid;

        $posts = $wpdb->get_results("
            SELECT 
              post_id
            from 
              wp_postmeta 
            where 
              meta_key = '_delivery_date' 
              and 
              DATE(meta_value) < '$willBeProduced'
              and 
              post_id in (SELECT ID from wp_posts where post_type = 'shop_order' and post_status  ='$status'  ) 
            ");

        foreach ($posts as $post) {
            Order::find($post->post_id)
                ->set_status(WooCommerce::orderHasBeenProduced)
                ->add_note('Marked as Produced by the system')
                ->save();
        }

        $status = 'wc-' . WooCommerce::orderHasBeenProduced;

        $posts = $wpdb->get_results("
            SELECT 
              post_id
            from 
              wp_postmeta 
            where 
              meta_key = '_delivery_date' 
              and 

              DATE(meta_value) < '$willBeFulfilled'
              and 
              post_id in (SELECT ID from wp_posts where post_type = 'shop_order' and post_status ='$status' ) 
            ");

        foreach ($posts as $post) {
            Order::find($post->post_id)
                ->set_status(WooCommerce::orderHasBeenFulfilled)
                ->add_note('Marked as Fulfilled by the system')
                ->save();
        }

    }


    public static function validStatusesForSQL()
    {
        return "'" . implode("','", [
                'wc-' . WooCommerce::orderHasBeenPaid,
                'wc-' . WooCommerce::orderHasBeenProduced,
                'wc-' . WooCommerce::orderHasBeenFulfilled,
                'wc-' . WooCommerce::orderHasFailedPayment,
                'wc-' . WooCommerce::orderHasBeenCancelled,
            ]) . "'";
    }

}
