<?php

namespace Theme\Commands;

use Theme\Exceptions;
use Theme\Log;
use Theme\Objects\Order;
use Theme\Objects\Product;
use Theme\Objects\ProductComponent;
use Theme\Parsers\RefererParser;
use Theme\Theme;
use Theme\WooCommerce;
use Throwable;
use WC_Product;
use WP_CLI;
use function WP_CLI\Utils\make_progress_bar;

class Commands
{







    /**
     * Remove all _order_data cache
     */
    public function clearOrderData()
    {
        global $wpdb;

        $wpdb->query("DELETE FROM wp_postmeta WHERE meta_key='_order_data' ");
        WP_CLI::log("Order data deleted");

    }



    /**
     * Sync all products with Exponea
     */
    public function syncAllProductsWithExponea(){
        Product::syncAllProductsWithExponea();
    }


    /**
     * Export an Order
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @throws Exceptions\CoteAtHomeException
     * @throws WP_CLI\ExitException
     */
    public function exportOrder($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'id' => 0,
            ]
        );

        if (!$arguments->id) {
            WP_CLI::error('Please specify a order to export');
        }

        $filename = 'order_' . $arguments->id . '.txt';
        $data = [
            'id'        => $arguments->id,
            'orderData' => Order::find($arguments->id)->get_order_data(),
        ];
        file_put_contents($filename, serialize($data));

    }


    public function scheduleReview($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'id' => 0,
            ]
        );

        if (!$arguments->id) {
            WP_CLI::error('Please specify a order to export');
        }


        do_action('future_send_review_request', $arguments->id);

    }



    public function fixPremiumDeliveries($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'limit' => 1,
            ]
        );

        return;

        global $wpdb;

        $results = $wpdb->get_results("SELECT order_id FROM wp_shop_sessions WHERE delivery_code = 'PREMIUM' AND delivery_date >= '2020-11-25' AND status IN ('Paid','Produced','Fulfilled')  limit {$arguments->limit}  ");

        Log::info('Premium Refund: Start ' . count($results));
        WP_CLI::log('Premium Refund: Start ' . count($results));
        foreach ($results as $result) {

            try {

                $order_id = $result->order_id;
                $order = Order::find($order_id);
                $status = $order->get_status();
                $wpdb->query("update wp_posts set post_status = 'wc-processing' where ID = $order_id");
                if (Theme::inProduction()) {
                    $order->refund(10, 'System refunded £10 - No premium delivery');
                }
                Log::info("$order_id : £10 refunded  ($status)");
                WP_CLI::log("$order_id : £10 refunded ($status)");

                $shippingData = $order->get_shipping_item();

                $order->set_shipping_data(
                    $shippingData->date,
                    $shippingData->delivery_company_id,
                    $shippingData->delivery_area_id,
                    0,
                    $shippingData->postcode,
                    'CÔTE',
                    'Free Delivery from Côte',
                    $shippingData->delivery_note,
                    $shippingData->gift_message
                );
                $order->update_shop_session();
                $wpdb->query("update wp_posts set post_status = 'wc-{$status}' where ID = $order_id");
            } catch (Throwable $e) {
                Log::info("$order_id : Could not process Refund." . $e->getMessage());
                WP_CLI::log("$order_id : Could not process Refund." . $e->getMessage());
            }

        }

        Log::info('Premium Refund: Completed');
        WP_CLI::log('Premium Refund: Completed');
    }



    /**
     * Make sure a order has it's ShopSession.
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @throws Exceptions\CoteAtHomeException
     */
    public function generateOrderData($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'limit'    => 100000,
                'usleep'   => 0,
                'progress' => true,
                'reset'    => false,
            ]
        );

        set_time_limit(0);
        ini_set('memory_limit', '4096M');

        global $wpdb;

        if ($arguments->reset) {
            $wpdb->query("DELETE FROM wp_postmeta WHERE meta_key = '_order_data_generated' ");
        }

        $results = $wpdb->get_results("
            select
                 ID 
            from 
                 wp_posts
            where 
                  post_type='shop_order'
              and 
                  post_status != 'wc-cart' 
              and 
                  ID not in (select post_id from wp_postmeta where meta_key = '_order_data_generated') 
            order by ID 
            limit {$arguments->limit}
            ");

        WP_CLI::log("Found {$wpdb->num_rows} orders to process");
        $progress = make_progress_bar('Processing Orders', $wpdb->num_rows);
        foreach ($results as $result) {

            if ($arguments->progress) {
                $progress->tick();
            } else {
                WP_CLI::log($result->ID);
            }
            $order = Order::find($result->ID)->generate_order_data();

            update_post_meta($result->ID, '_order_data_generated', 1);

            unset($order);
            usleep($arguments->usleep);
        }

    }



    /**
     * Import an order
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @throws Exceptions\CoteAtHomeException
     * @throws WP_CLI\ExitException
     * @throws \WC_Data_Exception
     */
    public function importOrder($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'file' => 0,
            ]
        );
        if (!$arguments->file) {
            WP_CLI::error('Please specify a file to export');
        }

        $data = unserialize(file_get_contents($arguments->file));

        if (!$data['id']) {
            WP_CLI::error('cannot find ID to import');
        }
        $orderData = $data['orderData'];

        Order::find($data['id'])->revert('Imported', $orderData);
        WP_CLI::log('Order Imported');

    }



    /**
     * Go through coupon and mark we used.
     */
    public function markUsedDeliverySlots()
    {
        global $wpdb;

        $results = $wpdb->get_results("
            SELECT 
                P.post_title AS code,
                PM1.meta_value AS order_id,
                PM2.meta_value AS used_order_id
            FROM
                wp_postmeta PM1,
                wp_postmeta PM2,
                wp_posts P
            WHERE 
                P.ID = PM1.post_id
                AND PM1.meta_key = 'order_id'  
                AND PM1.meta_value != ''
                AND P.ID = PM2.post_id
                AND PM2.meta_key = 'used_order_id'
                AND PM2.meta_value != ''
                AND post_type = 'shop_coupon'
                

            ");
        foreach ($results as $result) {
            try {
                WP_CLI::log("{$result->code} - Bought on {$result->order_id} and used on {$result->used_order_id} - Setting {$result->order_id} to fulfilled");
                Order::find($result->order_id)
                    ->mark_as_fulfilled("Booking coupon {$result->code} has been used on order {$result->used_order_id}. Marking this order as fulfilled");
            } catch (Throwable $e) {
                WP_CLI::log("..Could not find order id: {$result->order_id}... skipping...");
                continue;
            }

        }

    }



    public function processShopSessions($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'limit' => 1000000,
            ]
        );

        WooCommerce::processShopSessions($arguments->limit);

    }



    /**
     * Go through all orders and make sure the meta data is set correctly.
     *
     * @throws Exceptions\CoteAtHomeException
     */
    public function recalculate()
    {
        WooCommerce::recalculateOrderDeliveryDates();
        WP_CLI::log('Orders recalculated');
    }



    public function recordTransaction($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'id' => 0,
            ]
        );

        if (!$arguments->id) {
            WP_CLI::error('Please specify an order id ');
        }

        Order::find($arguments->id)->convert_sagepay_meta_to_transaction();

    }



    /**
     * Go through all the orders with delivery slots and make sure the events are scheduled.
     *
     * @param array $args
     * @param array $assoc_args
     */
    public function scheduleSlotEmails($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'limit' => 1000000,
            ]
        );

        global $wpdb;

        $results = $wpdb->get_results("
                select 
                    P.ID as coupon_id,
                    P.post_title,
                    PM.meta_value as order_id   
                from 
                     wp_posts P,
                     wp_postmeta PM
                where 
                    P.post_title  like 'BS-%' 
                    and 
                    P.post_type = 'shop_coupon'
                    and 
                    P.post_status = 'publish'
                    and
                    PM.post_id = P.ID
                    and
                    PM.meta_key = 'order_id'
                order by ID asc
                limit {$arguments->limit} 
        ");

        foreach ($results as $result) {
            try {
                $order = Order::find($result->order_id);
            } catch (Throwable $e) {
                continue;
            }

            $used = get_field('used_order_id', $result->coupon_id);
            if ($used) {
                continue;
            }

            WP_CLI::log('Scheduling Emails for order_id:' . $result->order_id);
            $order->maybe_schedule_booking_slot_reminder_emails();

        }

        WP_CLI::log('Process complete');
    }



    public function updateReferrers()
    {
        RefererParser::load();
    }



    /**
     * Make sure a order has it's ShopSession.
     *
     * @param array $args
     * @param array $assoc_args
     *
     * @throws Exceptions\CoteAtHomeException
     */
    public function updateShopSessions($args = [], $assoc_args = [])
    {

        // Parse our arguments
        $arguments = (object)wp_parse_args(
            $assoc_args,
            [
                'limit'    => 10000,
                'usleep'   => 0,
                'progress' => true,
                'from'     => 0,
            ]
        );

        set_time_limit(0);
        global $wpdb;

        $results = $wpdb->get_results("select ID from wp_posts where post_type='shop_order' and ID >= {$arguments->from} order by ID desc limit {$arguments->limit}");
        WP_CLI::log("Found {$wpdb->num_rows} orders to process");
        $progress = make_progress_bar('Processing Orders', $wpdb->num_rows);
        foreach ($results as $result) {

            if ($arguments->progress) {
                $progress->tick();
            }
            $order = Order::find($result->ID)->update_shop_session();
            unset($order);
            usleep($arguments->usleep);
        }

    }



    public function vat()
    {

        /**
         * @var WC_Product[] $products
         */
        $products = wc_get_products([
            'numberposts' => -1,
            'post_status' => 'publish',
        ]);

        WP_CLI::log('Setting VAT Rates');

        foreach ($products as $product) {
            $cats = $product->get_category_ids();
            if (in_array(2171, $cats)) {
                update_post_meta($product->get_id(), 'vat_rate', 'twenty');
            } else {
                update_post_meta($product->get_id(), 'vat_rate', 'zero');
            }
        }

        WP_CLI::log('Setting Calculated Rates');

        $productsToSet = [];

        $productComponents = ProductComponent::get();
        foreach ($productComponents as $productComponent) {

            $product = wc_get_product($productComponent->product_id[0]);

            if (!$product) {
                WP_CLI::log('Could not find product ' . $productComponent->product_id[0]);
                continue;
            }

            if ($product->get_type() === 'variation') {
                $productsToSet[$product->get_parent_id()] = $product->get_parent_id();
            } else {
                $productsToSet[$product->get_id()] = $product->get_id();
            }

        }

        foreach ($productsToSet as $product_id) {

            $product = new Product($product_id);

            $found = product::can_be_set_as_calculated($product_id);
            if ($found === true) {
                update_post_meta($product_id, 'vat_rate', 'calculated');
            } else {
                WP_CLI::log($product_id . ': ' . $product->get_name() . ' : ' . $found);
            }
        }

        Product::calculateVatRates();

        WP_CLI::log('Product VAT Updated');

    }

}
