<?php

namespace Theme\Objects;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Theme\APIs\Exponea;
use Theme\APIs\Hub;
use Theme\APIs\SagePay;
use Theme\Exceptions\CoteAtHomeException;
use Theme\Log;
use Theme\OrderItems\GiftCardItem;
use Theme\OrderItems\OriginalOrderItem;
use Theme\OrderItems\ShippingItem;
use Theme\OrderItems\TransactionItem;
use Theme\Settings;
use Theme\Theme;
use Theme\Tracking;
use Theme\Traits\IsTaggable;
use Theme\Utils\Hasher;
use Theme\Utils\Number;
use Theme\Utils\Time;
use Theme\Utils\Validate;
use Theme\WooCommerce;
use Dompdf\Dompdf;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Options;
use Lnk7\Genie\Utilities\CreateTaxonomy;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\View;
use Throwable;
use WC_Coupon;
use WC_Data_Exception;
use WC_Order;
use WC_Order_Item_Coupon;
use WC_Order_Item_Product;
use WC_Product;

class Order extends WC_Order implements GenieComponent
{


    use IsTaggable;

    const paymentMethodCard = 'card';


    const paymentMethodGiftCard = 'gift-card';


    const paymentMethodNone = 'none';


    /**
     * This is stored with the cached version of _order_data
     * The cache is refreshed if the cached version is < this number
     */
    const orderDataVersion = 1.0;


    static $postType = 'shop_order';


    static $cancelEditAfter = 60 * 60;


    protected $orderMessages;


    /**
     * @var Customer $customer
     */
    protected $customer;



    public static function setup()
    {

        /**
         * This is from sagepay - Just to make sure we only take the amount pending.
         */
        HookInto::filter('woocommerce_sagepay_direct_data')
            ->run(function ($data, $order) {
                $order = new Order($order->get_id());
                $data['Amount'] = $order->get_pending_charge();
                return $data;
            });

        /**
         * An order has been paid
         */
        HookInto::action('woocommerce_order_status_' . WooCommerce::orderHasBeenPaid)
            ->run(function ($order_id) {

                static::find($order_id)
                    ->convert_sagepay_meta_to_transaction()
                    ->set_date_paid()
                    ->maybe_set_shipping_data()
                    ->create_customer_and_update_exponea()
                    ->update_order_delivery_meta()
                    ->process_products_and_create_delivery_slot()
                    ->maybe_schedule_booking_slot_reminder_emails()
                    ->process_coupons()
                    ->maybe_assign_to_an_event()
                    ->process_gift_cards()
                    ->recalculate_and_save()
                    ->maybe_capture_original_order()
                    ->maybe_send_coupons_to_hub()
                    ->maybe_send_email()
                    ->maybe_send_tracking_to_exponea(500)
                    ->delete_original_order_data()
                    ->update_shop_session();
            });

        /**
         * Generate the order_data after an order and timestamp.
         */
        HookInto::action('woocommerce_after_order_object_save')
            ->run(function ($order) {

                $order->set_date_modified(Time::utcTimestamp());
                Order::find($order->get_id())->generate_order_data();

            });

        /**
         * An order has been converted from a cart to paid
         */
        HookInto::action('woocommerce_order_status_' . WooCommerce::orderIsACart . '_to_' . WooCommerce::orderHasBeenPaid)
            ->run(function ($order_id) {
                static::find($order_id)
                    ->set_date_created()
                    ->save();
            });

        /**
         * The order was cancelled.
         */
        HookInto::action('woocommerce_order_status_' . WooCommerce::orderHasBeenCancelled)
            ->run(function ($order_id) {

                static::find($order_id)
                    ->maybe_credit_gift_cards()
                    ->send_email()
                    ->update_order_delivery_meta()
                    ->maybe_assign_to_an_event()
                    ->maybe_send_coupons_to_hub()
                    ->delete_original_order_data()
                    ->update_shop_session();
            });

        /**
         * The order has been marked as fulfilled
         */
        HookInto::action('woocommerce_order_status_' . WooCommerce::orderHasBeenFulfilled)
            ->run(function ($order_id) {
                static::find($order_id)
                    ->maybe_schedule_review_request()
                    ->update_shop_session();
            });

        /**
         * The order has been marked as produced
         */
        HookInto::action('woocommerce_order_status_' . WooCommerce::orderHasBeenProduced)
            ->run(function ($order_id) {
                static::find($order_id)
                    ->update_shop_session();
            });

        /**
         * Register our order tag
         */
        CreateTaxonomy::called('order_tag')
            ->attachTo('shop_order')
            ->register();

        /**
         * Event handler for a future event
         */
        HookInto::action('future_send_review_request')
            ->run(function ($order_id) {

                try {

                    Log::info('future_send_review_request', [$order_id]);

                    $order = static::find($order_id);
                    if ($order->get_status() === WooCommerce::orderHasBeenCancelled) {
                        Log::info('future_send_review_request: order cancelled', [$order_id]);
                        return;
                    }

                    $email = strtolower(trim($order->get_billing_email()));
                    if (!$email) {
                        Log::error('future_send_review_request: no email', [$order_id]);
                        return;
                    }

                    $shippingData = $order->get_shipping_item();
                    if (!$shippingData) {
                        Log::error('future_send_review_request: could not find shipping data', [$order_id]);
                        return;
                    }

                    $order->create_exponea_customer();

                    $payload = [
                        'customer_ids' => [
                            'registered' => $email,
                        ],
                        'event_type'   => 'cah_review_request',
                        'timestamp'    => time(),
                        'properties'   => [
                            'order_id'           => $order_id,
                            'email'              => $email,
                            'order_timestamp'    => $order->get_date_created()->getTimestamp(),
                            'delivery_timestamp' => Carbon::createFromFormat('Y-m-d', $shippingData->date)->getTimestamp(),
                            'type'               => 'request',
                            'url'                => home_url('/review/?order_id=' . Hasher::encode($order_id)),
                        ],
                    ];

                    if (Theme::inProduction()) {
                        Exponea::track($payload);
                    }
                    Log::info('future_send_review_request: track ', [$order_id, $payload]);

                } catch (Throwable $e) {
                    Log::error('hook: future_send_review_request: ' . $e->getMessage(), func_get_args());
                }

            });

        /**
         * Event handler for a future event
         */
        HookInto::action('future_send_booking_slot_reminder')
            ->run(function ($order_id, $reminder) {

                try {

                    Log::debug('future_send_booking_slot_reminder', [$order_id, $reminder]);

                    $order = static::find($order_id);
                    if ($order->get_status() === WooCommerce::orderHasBeenCancelled) {
                        Log::debug('future_send_booking_slot_reminder: order cancelled', [$order_id, $reminder]);
                        return;
                    }

                    if (!$order->contains_delivery_slot()) {
                        Log::debug('future_send_booking_slot_reminder: no delivery slot', [$order_id, $reminder]);
                        return;
                    }

                    $code = $order->get_delivery_slot_code();

                    if (GiftCard::looksLikeAGiftCard($code)) {
                        $giftCard = GiftCard::getByCardNumber($code);
                        if (!$giftCard || ($giftCard->isSlotCard() && $giftCard->used_order_id)) {
                            Log::debug('future_send_booking_slot_reminder:  Gift Card Used already / No Gift Card ', [$order_id, $reminder]);
                            return;
                        }
                    } else {
                        $coupon_id = wc_get_coupon_id_by_code($code);
                        if (!$coupon_id) {
                            Log::debug('future_send_booking_slot_reminder: No Coupon', [$order_id, $reminder]);
                            return;
                        }

                        $used = get_field('used_order_id', $coupon_id);
                        if ($used) {
                            Log::debug('future_send_booking_slot_reminder:  Coupon Used already ', [$order_id, $reminder]);
                            return;
                        }

                    }

                    $product = current($order->get_product_items());
                    if (!$product) {
                        Log::debug('future_send_booking_slot_reminder:  Order ' . $order->get_id() . ' could not find product when sending delivery slot', [$order_id, $reminder]);
                        return;
                    }

                    $shippingItem = $order->get_shipping_item();
                    $deliveryDate = Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz());
                    $useByDate = Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->subDays(3);

                    $payload = [
                        'customer_ids' => [
                            'registered' => $order->get_billing_email(),
                        ],
                        'event_type'   => 'cah_booking_slot',
                        'timestamp'    => time(),
                        'properties'   => [
                            'status'        => 'reminder',
                            'name'          => $product->get_name(),
                            'order_id'      => $order->get_id(),
                            'delivery_date' => $deliveryDate->getTimestamp(),
                            'use_by_date'   => $useByDate->getTimestamp(),
                            'postcode'      => $shippingItem->postcode,
                            'code'          => $code,
                            'reminder'      => $reminder,
                        ],
                    ];
                    if (Theme::inProduction()) {
                        Exponea::track($payload);
                    }
                    Log::debug('future_send_booking_slot_reminder:  track ', [$order_id, $reminder, $payload]);

                } catch (Throwable $e) {
                    Log::error('hook: future_send_booking_slot_reminder: ' . $e->getMessage(), func_get_args());
                }

            });

    }



    /**
     * Add a coupon to the order
     *
     * @param string $code
     * @param string|null $email
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function add_coupon(string $code, string $email = null)
    {

        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9\-]/', '', $code);

        $email = sanitize_email(trim(strtolower($email)));

        $this->check_if_order_is_editable();

        // Check if we're adding a gift Card
        if (GiftCard::looksLikeAGiftCard($code)) {
            return $this->add_gift_card($code);
        }

        $coupon_id = wc_get_coupon_id_by_code($code);
        if (!$coupon_id) {
            $coupon_id = Coupon::maybeImportFromShopify($code);
            if (!$coupon_id) {
                throw CoteAtHomeException::withMessage("Unfortunately this discount code is invalid");
            }
        }

        // This throws an error if something is wrong.
        $this->can_coupon_be_added($code, $email);

        // We need this here as can_coupon_be_added is used when adding automatic coupons too.
        $automatic = get_field('automatic', $coupon_id);
        if ($automatic && !is_user_logged_in()) {
            throw CoteAtHomeException::withMessage("Unfortunately this discount code can only be added automatically");
        }

        // Auto add products ?
        $addProducts = get_field('products', $coupon_id);
        if ($addProducts) {
            $products = get_field('add_products', $coupon_id);
            if (!empty($products)) {
                foreach ($products as $product) {
                    // we must add the coupon before this.. otherwise it might fail;
                    $this->add_or_update_product($product['product_id'], $product['quantity'], true, true, false);
                }
            }
        }

        // All Good ! No errors were thrown, add our coupon in. It will be calculated later.
        $couponItem = new WC_Order_Item_Coupon();
        $couponItem->set_props([
            'code'     => $code,
            'discount' => 0,
        ]);
        $couponItem->save();
        $this->add_item($couponItem);
        $this->recalculate_and_save();

        $this->check_cart_contents();

        return $this;

    }



    /**
     * Sets the order with free shipping.
     *
     * @param $note
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function add_free_shipping($note)
    {
        $this->check_if_order_is_editable();

        $shippingItem = $this->get_shipping_item();
        $shippingItem->amount = 0;
        $this->add_item($shippingItem);
        $this->add_note($note);
        $this->recalculate_and_save();
        return $this;

    }



    /**
     * Add a Gift card to the order.
     *
     * @param $cardNumber
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function add_gift_card($cardNumber)
    {
        $this->check_if_order_is_editable();

        if (GiftCard::looksLikeAToggleCard($cardNumber)) {
            $card = GiftCard::syncWithToggle($cardNumber);
        } else {
            $card = GiftCard::getByCardNumber($cardNumber);
        }

        if (!$card) {
            throw CoteAtHomeException::withMessage("Unfortunately the gift card number:$cardNumber is not valid ");
        }

        if ($card->hasExpired()) {
            throw CoteAtHomeException::withMessage('Unfortunately, this gift card has expired');
        }

        if ($card->balance <= 0) {
            throw CoteAtHomeException::withMessage('This gift card has a £0 balance');
        }

        $cardItems = $this->get_gift_card_items();
        foreach ($cardItems as $cardItem) {
            if ($cardItem->card_number === $cardNumber) {
                throw CoteAtHomeException::withMessage('This gift card has already been added to this order');
            }
        }

        $total = $this->get_product_total() + $this->get_coupon_total() + $this->get_shipping_total() + $this->get_gift_card_total();
        $amount = $total > $card->balance ? $card->balance : $total;

        $giftCardItem = new GiftCardItem();
        $giftCardItem->card_number = $cardNumber;
        $giftCardItem->amount = $amount;
        $giftCardItem->processed = false;
        $giftCardItem->save();

        $this->add_item($giftCardItem);
        $this->add_note("Gift Card $cardNumber Applied");
        $this->recalculate_and_save();

        return $this;
    }



    /**
     * Add a manual payment
     * Thia happens once the order  leaves edit mode and there is a pending charge
     *
     * @param float $amount
     * @param string $note
     *
     * @return $this
     */
    public function add_manual_payment(float $amount, string $note = '')
    {

        $transaction = new TransactionItem();
        $transaction->fill([
            'amount'  => $amount,
            'type'    => 'manual',
            'balance' => $amount,
            'date'    => Carbon::now()->setTimezone(Time::tz())->format('Y-m-d H:i:s'),
        ]);
        $transaction->save();

        $this->add_item($transaction);
        if ($note) {
            $this->add_note($note);
        }
        $this->save();

        $this->add_notice('Manual Charge of £' . $amount . ' Processed');

        return $this;
    }



    /**
     * Add a note to the order
     *
     * @param $note
     *
     * @return Order
     */
    public function add_note($note)
    {
        if ($note) {
            $addedByUser = get_current_user_id() !== 0;
            parent::add_order_note($note, 0, $addedByUser);
        }

        return $this;
    }



    public function add_notice($notice)
    {
        if (function_exists('wc_add_notice')) {
            wc_add_notice($notice);
        }
    }



    /**
     * Add, update or remove products from the cart
     *
     * @param int $product_id product_id or variation_id
     * @param int $quantity
     * @param bool $append
     * @param bool $onlyIfNotAddedAlready Only add this product if it's not already present.
     * @param bool $checkCart
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function add_or_update_product(int $product_id, int $quantity, bool $append = false, bool $onlyIfNotAddedAlready = false, $checkCart = true)
    {
        $this->check_if_order_is_editable();

        $product = wc_get_product($product_id);

        // We need to determine the variation_id and Product_id
        $variation_id = null;
        if ($product->get_type() === 'variation') {
            $variation_id = $product_id;
            $product_id = $product->get_parent_id();
            $productToChange = wc_get_product($variation_id);
        } else {
            $productToChange = wc_get_product($product_id);
        }

        // This should never happen.
        if ($quantity > 0 && !$productToChange->exists()) {
            throw CoteAtHomeException::withMessage("Product with id $product_id does not exist");
        }

        // If we already have this item we can remove it or update the quantity
        $found = false;
        $productItems = $this->get_product_items();


        foreach ($productItems as $productItem) {

            $product = $productItem->get_product();
            if ($product->get_id() === $productToChange->get_id()) {
                $found = true;

                // We're removing the item
                if ($quantity === 0) {

                    $this->remove_item($productItem->get_id());

                    $attributes = Product::getAttributesForExponea($product);
                    $attributes['action'] = 'remove';
                    $attributes['purchase_id'] = $this->get_id();
                    $attributes['items'] = $this->generate_product_item_data();
                    $attributes['total_price'] = $this->get_product_total();
                    $attributes['quantity'] = 0;

                    Tracking::track('cart_update', $attributes);

                    // This was the only item?  Clear everything.
                    if (count($productItems) === 1) {
                        return $this->reset();
                    }

                } else {
                    // The product has already been added - we can leave
                    if ($onlyIfNotAddedAlready) {
                        return $this;
                    }

                    // We only check stock on change, as the user cannot add the product to their
                    // cart if it's out of stock.
                    $stock = $product->get_stock_quantity();
                    $managed = $product->get_manage_stock();
                    if ($managed && $stock < $quantity) {
                        $quantity = $stock;
                        $this->add_notice("Unfortunately this product is out of stock");
                    } else {
                        $quantity = $append ? $productItem->get_quantity() + $quantity : $quantity;
                    }

                    // if the total was already 0 ... keep it that way.
                    $total = Number::decimal($productItem->get_subtotal());
                    $newPrice = $total <= 0 ? 0 : Number::decimal($product->get_price()) * $quantity;
                    $productItem->set_quantity($quantity);
                    $productItem->set_subtotal($newPrice);
                    $productItem->set_total($newPrice);

                    $meta = Product::getMeta($productToChange->get_id());
                    foreach ($meta as $key => $value) {
                        $productItem->update_meta_data($key, $value);
                    }

                    $productItem->save();
                    $this->add_item($productItem);

                    $attributes = Product::getAttributesForExponea($product);
                    $attributes['action'] = 'update';
                    $attributes['purchase_id'] = $this->get_id();
                    $attributes['quantity'] = $quantity;
                    $attributes['items'] = $this->generate_product_item_data();
                    $attributes['total'] = $newPrice;
                    $attributes['total_price'] = $this->get_product_total();

                    Tracking::track('cart_update', $attributes);

                    break;
                }
            }
        }

        // OK - we need to add this product.
        if (!$found) {
            $total = $productToChange->get_price() * $quantity;
            $productItem = new WC_Order_Item_Product();
            $productItem->set_product($productToChange);
            $productItem->set_quantity($quantity);
            $productItem->set_subtotal($total);
            $productItem->set_total($total);

            $meta = Product::getMeta($productToChange->get_id());
            foreach ($meta as $key => $value) {
                $productItem->update_meta_data($key, $value);
            }

            $productItem->save();
            $this->add_item($productItem);

            $attributes = Product::getAttributesForExponea($product);
            $attributes['action'] = 'add';
            $attributes['purchase_id'] = $this->get_id();
            $attributes['quantity'] = $quantity;
            $attributes['items'] = $this->generate_product_item_data();
            $attributes['total'] = Number::decimal($quantity * $attributes['price']);
            $attributes['total_price'] = $this->get_product_total();

            // Update Shop Session
            $shopSession = $this->get_shop_session();
            $shopSession->added_to_cart  = true;
            $shopSession->save();

            Tracking::track('cart_update', $attributes);

        }

        // Adjust everything
        $this->recalculate_and_save();


        // Now let's check the products in the cart.
        if ($checkCart) {
            $this->check_cart_contents();
        }
        $this->maybe_apply_automatic_coupons();
        return $this;
    }



    /**
     * Calculate our totals - CAH style
     *
     * @param bool $and_taxes
     *
     * @return float
     * @throws WC_Data_Exception
     */
    public function calculate_totals($and_taxes = true)
    {
        $this->recalculate_gift_cards();
        $total = $this->get_product_total() + $this->get_coupon_total() + $this->get_shipping_total() + $this->get_gift_card_total();
        $this->set_total(Number::decimal($total));
        $this->save();
        return $total;
    }



    /**
     * Checks to see if we can add $code or not.
     * check for === true
     *
     * @param string $code
     * @param string|null $email
     *
     * @return bool|string
     * @throws CoteAtHomeException
     */
    public function can_coupon_be_added(string $code, string $email = null)
    {

        $coupon_id = wc_get_coupon_id_by_code($code);
        if (!$coupon_id) {
            throw CoteAtHomeException::withMessage("Unfortunately this discount code is not valid");
        }

        $coupons = $this->get_coupon_items();

        foreach ($coupons as $couponItem) {
            if (strtoupper($couponItem->get_code()) === strtoupper($code)) {
                throw CoteAtHomeException::withMessage("Unfortunately this discount code has already been added");
            }
        }

        $customers = get_field('customers', $coupon_id);
        if ($customers === 'new' || $customers === 'existing') {
            if (!$email) {
                throw CoteAtHomeException::withMessage("Please enter your email address before applying this discount code.");
            }
            $customer = Customer::getByEmail($email);
            if ($customers === 'new' && $customer) {
                throw CoteAtHomeException::withMessage("Unfortunately this discount code is for new customers only.");
            }
            if ($customers === 'existing' && !$customer) {
                throw CoteAtHomeException::withMessage("Unfortunately this discount code is for current customers only.");
            }
        }

        $excludeAddedCouponFromCount = get_field('exclude_from_count', $coupon_id);
        if (!$excludeAddedCouponFromCount) {

            $couponLimit = Settings::get('coupon_limit', 1);
            $couponLimitError = Settings::get('coupon_limit_error', 'Unfortunately only one discount code can be used at a time.');

            $count = 0;
            foreach ($coupons as $couponItem) {
                $checkID = wc_get_coupon_id_by_code($couponItem->get_code());
                if ($checkID && !get_field('exclude_from_count', $checkID)) {
                    $count++;
                }
            }
            if ($count >= $couponLimit) {
                throw CoteAtHomeException::withMessage($couponLimitError);
            }
        }

        //has the coupon expired?
        $coupon = new WC_Coupon($coupon_id);
        $expires = $coupon->get_date_expires();
        if ($expires) {

            $expireDate = new Carbon($expires);
            $now = Time::utcNow();
            if ($now->isAfter($expireDate)) {
                throw CoteAtHomeException::withMessage("Unfortunately this discount code has expired");
            }
        }

        // Check usage
        $usedCount = (int)$coupon->get_usage_count();
        $limit = (int)$coupon->get_usage_limit();
        if ($usedCount > 0 && $limit > 0 && $usedCount >= $limit) {
            throw CoteAtHomeException::withMessage("Unfortunately this discount code has already been redeemed");
        }

        return true;

    }



    /**
     * Cancel an order
     *
     * @param string $note
     * @param bool $refund
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function cancel($note = '', $refund = true)
    {

        $this->add_notice('The order has been cancelled');
        if ($note) {
            $this->add_note($note);
        }
        if ($refund) {
            $this->refund(0);
        }

        $this->set_status('cancelled');
        $this->save();

        return $this;
    }



    /**
     * Make an additional charge on this order
     * This happens once the order leaves edit mode and there is a pending charge
     *
     * @param float $amount
     * @param string $note
     *
     * @return static
     * @throws CoteAtHomeException
     */
    public function charge(float $amount, string $note = '')
    {

        $transactionID = $this->get_original_transaction_id();
        $result = SagePay::charge($this->get_id(), $transactionID, $amount);
        $details = $result->getResponseBody();

        $transaction = new TransactionItem();

        $transaction->fill([
            'order_id'                => $this->id,
            'success'                 => $result->wasSuccessful(),
            'status_code'             => $details->statusCode,
            'amount'                  => $amount,
            'balance'                 => $amount,
            'amount_refunded'         => 0,
            'transaction_type'        => $details->transactionType,
            'transaction_id'          => $details->transactionId,
            'retrieval_reference'     => $details->retrievalReference,
            'bank_authorisation_code' => $details->bankAuthorisationCode,
            'date'                    => Carbon::now()->setTimezone(Time::tz())->format('Y-m-d H:i:s'),
        ]);
        $transaction->save();

        $this->add_item($transaction);
        if ($note) {
            $this->add_note($note);
        }
        $this->save();

        if ($result->wasSuccessful()) {
            $note = 'Charge of £' . $amount . ' Processed';
        } else {
            $note = 'Charge of £' . $amount . ' Failed.';
        }
        $this->add_notice($note);

        if ($result->failed()) {
            throw CoteAtHomeException::withMessage($note);
        }

        return $this;
    }



    /**
     * A check to make sure this product can be added to the cart.
     * If there is a problem - we set an error and let the user take the necessary Action
     */
    public function check_and_adjust_products_in_cart()
    {

    }



    /**
     * Check products in the cart
     */
    function check_cart_contents()
    {

        $this->clear_cart_error();

        $productItems = $this->get_product_items();
        $couponItems = $this->get_coupon_items();

        $now = Time::utcNow();
        $productCategoriesInBasket = [];

        // Our product Limitation Container
        $limitPerOrder = [];
        foreach ($productItems as $productItem) {

            $product = $productItem->get_product();
            $quantity = $productItem->get_quantity();
            $name = $product->get_name();

            //What product should we be checking?
            // How many of this product can be added per order?
            // This is set on the parent level.  So if a variation get the parent.
            $productToCheckID = $product->get_type() === 'variation' ? $product->get_parent_id() : $product->get_id();

            $terms = wp_get_post_terms($productToCheckID, 'product_cat');
            foreach ($terms as $term) {
                $productCategoriesInBasket[$term->term_id] = $term->term_id;
            }

            //Check delivery date restrictions that would result in not dates showing
            $deliveryTo = get_field('delivery_to', $productToCheckID);
            if ($deliveryTo) {
                $maxDate = Carbon::createFromFormat('Y-m-d', $deliveryTo)->setTimezone(Time::tz())->endOfDay();

                if ($now->isAfter($maxDate)) {
                    $this->set_cart_error("unfortunately, {$product->get_name()} is no longer available");
                    return;
                }
            }

            // Does this product need to be bought alone?
            $boughtAlone = get_field('bought_alone', $product->get_id());
            if ($boughtAlone && count($productItems) > 1) {
                $this->set_cart_error("The product '$name' can only be purchased alone. Please remove the other items from your basket");
                return;
            }

            $limit_per_order = (int)get_field('limit_per_order', $productToCheckID);

            if ($limit_per_order > 0) {

                $limitedProduct = wc_get_product($productToCheckID);

                //Setup our array element to hold the parent or variations products if it does not already exist
                if (!array_key_exists($productToCheckID, $limitPerOrder)) {
                    $limitPerOrder[$productToCheckID] = (object)[
                        'limit'    => $limit_per_order,
                        'quantity' => 0,
                        'products' => [],  // The products found
                        'name'     => $limitedProduct->get_name(),
                    ];
                }
                // update the quantity
                $limitPerOrder[$productToCheckID]->quantity += $quantity;

                // The product (this could be the child or parent
                $limitPerOrder[$productToCheckID]->products[] = $product->get_id();

                // Are we over the limit?
                if ($limitPerOrder[$productToCheckID]->quantity > $limitPerOrder[$productToCheckID]->limit) {
                    $this->set_cart_error("Sorry, only $limit_per_order $name is allowed per order. Please adjust the quantity accordingly");
                    return;
                }
            }

            // This allows us to check variations in a poroduct group. To make sure 2 variations of the main product are not in the cart if the limit is 1
            if (isset($limitPerOrder[$productToCheckID]) && is_array($limitPerOrder[$productToCheckID])) {
                if ($limitPerOrder[$productToCheckID]->quantity > $limitPerOrder[$productToCheckID]->limit) {
                    $this->set_cart_error("Sorry, only $limit_per_order $name is allowed per order. Please change the quantity in your basket");
                    return;
                }
            }

            // Coupon restrictions are stored on the parent.
            $couponRestriction = get_field('coupons', $productToCheckID);

            switch ($couponRestriction) {
                case 'none' :
                    if (count($couponItems) > 0) {
                        $this->set_cart_error("The product '{$name}' can not be purchased with a discount code. Please remove the discount code from your basket");
                        return;
                    }
                    break;
                case 'only' :
                    $onlyWithCoupon = get_field('only_with_coupon', $productToCheckID);
                    if ($onlyWithCoupon) {
                        $couponFound = false;
                        foreach ($couponItems as $couponItem) {
                            if (strpos(strtoupper($couponItem->get_code()), strtoupper($onlyWithCoupon)) === 0) {
                                $couponFound = true;
                            }
                        }
                        if (!$couponFound) {
                            $this->set_cart_error("The product '{$name}' can only be used with a discount code.");
                            return;
                        }
                    }
                    break;
            }

        }

        $productCategoriesInBasket = array_values($productCategoriesInBasket);

        // Now check the limit
        foreach ($limitPerOrder as $mainProductID => $data) {
            if (count($data->products) > $data->limit) {
                $this->set_cart_error("Only {$data->limit} '{$data->name}' can be ordered per transaction");
                return;
            }
        }

        // Now let's check that the coupons are still valid
        foreach ($couponItems as $couponItem) {

            $code = $couponItem->get_code();

            $coupon_id = wc_get_coupon_id_by_code($couponItem->get_code());
            if (!$coupon_id || get_field('automatic', $coupon_id)) {
                continue;
            }

            $coupon = new WC_Coupon($coupon_id);

            // Cart Must contains product Categories
            $product_categories = get_field('product_categories', $coupon_id);

            if (is_array($product_categories) && !empty($product_categories)) {
                $intersect = array_intersect($product_categories, $productCategoriesInBasket);
                if (empty($intersect)) {
                    $this->set_cart_error("Unfortunately this voucher code $code is not applicable to the items in your basket");
                    return;
                }
            }

            // Cart must not contains product Categories
            $exclude_product_categories = get_field('exclude_product_categories', $coupon_id);
            if (is_array($exclude_product_categories) && !empty($exclude_product_categories)) {
                $intersect = array_intersect($exclude_product_categories, $productCategoriesInBasket);
                if (!empty($intersect)) {
                    $this->set_cart_error("Unfortunately this voucher code $code is not applicable to the items in your basket");
                    return;
                }
            }

            // Cart Must contain products
            $productRestrictions = $coupon->get_product_ids();
            if (!empty($productRestrictions)) {

                $max_product_ids = get_field('max_product_ids', $coupon_id);
                $productCount = 0;
                $found = false;

                foreach ($productItems as $productItem) {
                    if (in_array($productItem->get_product_id(), $productRestrictions) || in_array($productItem->get_variation_id(), $productRestrictions)) {
                        $found = true;
                        $productCount += $productItem->get_quantity();
                    }
                }
                if (!$found) {
                    $this->set_cart_error("Unfortunately this voucher code $code is not applicable to the items in your basket");
                    return;
                }

                if ($max_product_ids > 0 && $productCount > $max_product_ids) {
                    $this->set_cart_error("Unfortunately discount code $code is only applicable to $max_product_ids item(s) in your basket (you have $productCount)");
                    return;
                }
            }

            // Excluded products
            $productRestrictions = $coupon->get_excluded_product_ids();
            if (!empty($productRestrictions)) {

                $found = false;

                foreach ($productItems as $productItem) {
                    if (in_array($productItem->get_product_id(), $productRestrictions) || in_array($productItem->get_variation_id(), $productRestrictions)) {
                        $found = true;
                    }
                }
                if ($found) {
                    $this->set_cart_error("Unfortunately this voucher code $code is not applicable to the items in your basket");
                    return;
                }

            }
        }

    }



    /**
     * Check that this order is in edit Mode
     *
     * @throws CoteAtHomeException
     */
    public function check_if_order_is_editable()
    {
        if ($this->is_not_editable()) {
            throw CoteAtHomeException::withMessage('Order is not Editable');
        }
    }



    /**
     * Reset the product Error
     */
    public function clear_cart_error()
    {
        $this->set_cart_error('');
    }



    /**
     * remove Item cache
     */
    public function clear_item_cache()
    {
        wp_cache_delete('order-items-' . $this->get_id(), 'orders');
    }



    /**
     * Does this order contain a delivery slot?
     *
     * @return bool
     */
    public function contains_delivery_slot()
    {
        return $this->get_delivery_slot_code() !== '';

    }



    /**
     * Does this order contain a delivery slot?
     *
     * @return bool
     */
    public function contains_event()
    {

        $items = $this->get_product_items();
        foreach ($items as $item) {
            $cahType = get_field('cah_type', $item->get_product_id());
            if ($cahType === 'event') {
                return $item->get_product_id();
            }
        }

        return false;

    }



    public function convert_sagepay_meta_to_transaction()
    {

        $result = $this->get_meta('_sageresult');
        if ($result) {
            $order = new WC_Order($this->get_id());
            do_action('woocommerce_sagepay_direct_payment_complete', $result, $order);
        }

        return $this;

    }



    /**
     * Create a Cote customer and then update exponea.
     *
     * @return $this
     */
    public function create_customer_and_update_exponea()
    {

        // Customer
        if (!$this->get_billing_email()) {
            return $this;
        }

        $acceptsMarketing = $this->get_meta('_accepts_marketing');
        $sentExponeaConsent = $this->get_meta('_sent_exponea_consent');

        $customer = Customer::findOrNew($this->get_billing_email());
        $customer->first_name = $this->get_billing_first_name();
        $customer->last_name = $this->get_billing_last_name();
        $customer->phone = $this->get_billing_phone();
        $customer->billing_company = $this->get_billing_company();
        $customer->billing_address_1 = $this->get_billing_address_1();
        $customer->billing_address_2 = $this->get_billing_address_2();
        $customer->billing_city = $this->get_billing_city();
        $customer->billing_state = $this->get_billing_state();
        $customer->billing_postcode = $this->get_billing_postcode();
        $customer->billing_country = $this->get_billing_country();
        $customer->shipping_company = $this->get_shipping_company();
        $customer->shipping_address_1 = $this->get_shipping_address_1();
        $customer->shipping_address_2 = $this->get_shipping_address_2();
        $customer->shipping_city = $this->get_shipping_city();
        $customer->shipping_state = $this->get_shipping_state();
        $customer->shipping_postcode = $this->get_shipping_postcode();
        $customer->shipping_country = $this->get_shipping_country();
        $customer->shipping_phone = $this->get_shipping_phone();
        $customer->accepts_marketing = $acceptsMarketing;
        $customer->save();
        $this->create_exponea_customer();

        $this->set_cote_customer($customer->get_id());

        if ($acceptsMarketing && !$sentExponeaConsent) {
            $payload = [
                'customer_ids' => [
                    'registered' => $customer->email,
                ],
                'event_type'   => 'consent',
                'timestamp'    => time(),
                'properties'   => [
                    'action'      => "accept",
                    'category'    => 'cah',
                    'valid_until' => "unlimited",
                    'message'     => 'I agree to receive Cote email and marketing', //Phase2: copy new terms text
                    'location'    => 'https://coteathome.co.uk/checkout',
                    'domain'      => "coteathome.co.uk",
                    'language'    => "en",
                    'placement'   => "Order Form",
                ],
            ];
            Exponea::track($payload);


            //make sure we only do this once
            update_post_meta($this->get_id(), '_sent_exponea_consent', 1);
        }

        return $this;

    }



    /**
     * Create the customer in Exponea.
     */
    public function create_exponea_customer()
    {

        $data = $this->generate_order_data();

        $recipientEmail = $data['customer']['email'];

        // Add Recipient
        $payload = [
            'customer_ids' => [
                'registered' => $recipientEmail,
            ],
            'properties'   => [
                'email'      => $data['customer']['email'],
                'first_name' => $data['customer']['first_name'],
                'last_name'  => $data['customer']['last_name'],
                'phone'      => $data['customer']['phone'],
            ],
        ];

        Exponea::update($payload);
    }



    /**
     * Create and stream a PDF Invoice
     */
    function create_pdf_invoice()
    {

        $logo = get_stylesheet_directory_uri() . '/assets/cah_invoice_logo.png';

        $payment = '';
        $event = '';

        $event_product_id = $this->contains_event();

        if ($event_product_id) {

            $event = Event::getByEventProductID($event_product_id);

            $payment = View::with(Settings::get('invoice_payment'))
                ->addVar('order_id', $this->get_id())
                ->render();

        }

        $html = View::with('invoices/regular.twig')
            ->addVar('orderData', $this->generate_order_data())
            ->addVar('logo', $logo)
            ->addVar('date', $this->get_date_created()->format('d/m/Y'))
            ->addVar('footer', Settings::get('invoice_footer'))
            ->addVar('event', $event)
            ->addVar('payment', $payment)
            ->render();

        // instantiate and use the dompdf class
        $dompdf = new Dompdf();

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ]);

        $dompdf->setHttpContext($context);

        $dompdf->getOptions()->set([
                //'fontDir'                 => FontLoader::getFontDirectory(),
                'isRemoteEnabled'         => true,
                'isFontSubsettingEnabled' => true,
                'isHtml5ParserEnabled'    => true,
            ]
        );

        $dompdf->loadHtml($html);

        // (Optional) Setup the paper size and orientation
        $dompdf->setPaper('A4');

        // Render the HTML as PDF
        $dompdf->render();

        // Output the generated PDF to Browser
        $dompdf->stream('Invoice_' . $this->get_id(), ['Attachment' => 0]);

        exit;

    }



    /**
     * Delete this order
     *
     * @param false $force_delete
     *
     * @return array
     */
    public function delete($force_delete = false)
    {

        if (is_user_logged_in()) {
            $this->add_notice('Order deleted');
        }
        $this->add_order_note('Order deleted');
        $result = parent::delete($force_delete);
        return [
            'success'  => $result,
            'redirect' => '/wp-admin/edit.php?post_type=shop_order',
        ];

    }



    /**
     * Only used in development!
     */
    public static function delete_all_orders()
    {

        if (Theme::inProduction()) {
            return;
        }

        $posts = get_posts([
            'post_type'   => 'shop_order',
            'post_status' => 'any',
            'fields'      => 'ids',
            'numberposts' => -1,
        ]);

        foreach ($posts as $post) {
            wp_delete_post($post, true);
        }

    }



    /**
     * Remove the trace
     *
     * @return $this
     */
    public function delete_original_order_data()
    {

        $this->remove_order_items(OriginalOrderItem::$type);
        return $this;
    }



    /**
     * Create a copy of this order: Note that we dont duplicate gift cards
     *
     * @return Order
     * @throws CoteAtHomeException|WC_Data_Exception
     */
    public function duplicate()
    {

        $data = $this->generate_order_data();

        $newOrder = new Order();
        $newOrder->set_customer(
            $this->get_billing_first_name(),
            $this->get_billing_last_name(),
            $this->get_billing_email(),
            $this->get_billing_phone(),
        );

        $newOrder->set_billing_address(
            $this->get_billing_company(),
            $this->get_billing_address_1(),
            $this->get_billing_address_2(),
            $this->get_billing_city(),
            $this->get_billing_state(),
            $this->get_billing_postcode(),
            $this->get_billing_country(),
        );

        $newOrder->set_shipping_address(
            $this->get_shipping_first_name(),
            $this->get_shipping_last_name(),
            $this->get_shipping_company(),
            $this->get_shipping_address_1(),
            $this->get_shipping_address_2(),
            $this->get_shipping_city(),
            $this->get_shipping_state(),
            $this->get_shipping_postcode(),
            $this->get_shipping_country(),
            $this->get_shipping_phone(),
        );

        foreach ($data['items'] as $product) {

            $productIdToUse = $product['variation_id'] ? $product['variation_id'] : $product['product_id'];

            $newOrder->add_or_update_product(
                $productIdToUse,
                $product['quantity'],
            );
        }

        foreach ($data['coupons'] as $coupon) {
            $newOrder->add_coupon($coupon['code']);
        }

        $newOrder->set_cote_customer($this->get_cote_customer());

        //Get the first available shipping date
        $newOrder->set_date_created(Time::utcTimestamp());
        $shippingItem = $this->get_shipping_item();
        $deliveryArea = new DeliveryArea($shippingItem->delivery_area_id);

        $dateFound = false;
        try {
            $delivery = static::get_shipping_dates($shippingItem->postcode);
            foreach ($delivery['dates'] as $date) {
                if ($date['available']) {
                    if (!empty($date['prices'])) {
                        $price = $date['prices'][0];
                        $newOrder->set_shipping_data(
                            $date['date'],
                            $date['delivery_company_id'],
                            $deliveryArea->ID,
                            $price['price'],
                            $shippingItem->postcode,
                            $price['code'],
                            $price['name'],
                            $shippingItem->delivery_note,
                            $shippingItem->gift_message
                        );
                        $dateFound = true;
                        break;
                    }
                }
            }
        } catch (CoteAtHomeException $e) {

        }

        // Set our Status
        $newOrder->set_status(WooCommerce::orderIsPendingPayment);
        $newOrder->save();

        $message = "This order was duplicated from order: {$this->get_id()}.";

        if (!$dateFound) {
            $message .= 'Please note, that the system could not determine next delivery date while duplicating order';
        }

        $newOrder->add_order_note($message);

        if (!$dateFound) {
            $newOrder->add_order_note('Could not determine next delivery date while duplicating order');
        }

        wc_clear_notices();
        $this->add_notice('Order duplicated successfully');

        return $newOrder;

    }



    /**
     * Put this order into edit mode.
     *
     * @throws CoteAtHomeException
     */
    public function enter_edit_mode()
    {

        if (!$this->is_editable()) {
            throw CoteAtHomeException::withMessage('enterEditMode: order not editable');
        }

        if ($this->is_in_edit_mode()) {
            return $this;
        }

        // Just to be sure
        $this->remove_order_items(OriginalOrderItem::$type);

        // Save the current state
        $originalOrder = new OriginalOrderItem();
        $originalOrder->orderData = $this->generate_order_data();
        $originalOrder->addedAt = Time::utcTimestamp();
        $originalOrder->addedBy = get_current_user_id();
        $originalOrder->revertsAt = Time::utcTimestamp() + static::$cancelEditAfter;
        $originalOrder->save();
        $this->add_item($originalOrder);

        // Save as well
        $this->set_status('editing');
        $this->save();

        return $this;

    }



    /**
     * Ensure that this order is not counted towards the day's order regardless of the order status.
     *
     * @return $this
     */
    function exclude_from_order_count()
    {
        update_post_meta($this->get_id(), '_exclude_from_order_count', 1);
        return $this;
    }



    /**
     * Find an order
     *
     * @param $id
     *
     * @return static
     * @throws CoteAtHomeException
     */
    public static function find($id)
    {
        $order = new static($id);

        if ($order->get_id() === 0) {
            throw CoteAtHomeException::withMessage("order $id does not exist");
        }

        return $order;

    }



    /**
     * Build an array ofd order data for the order edit screen / saving as an original order.
     *
     * @return array
     */
    public function generate_order_data()
    {

        $total = $this->get_total();
        $customer = $this->get_customer();
        $pendingCharge = $this->get_pending_charge();
        $status = $this->get_status();

        $actions = [];
        $links = [];
        if (in_array($status, [WooCommerce::orderIsPendingPayment, WooCommerce::orderIsACart]) && $pendingCharge > 0) {
            $actions[] = [
                'button'    => 'Email Payment Link',
                'endpoint'  => 'sendPaymentLink',
                'actualUrl' => $this->get_checkout_payment_url(),
            ];
            $links[] = [
                'name' => 'Payment',
                'url'  => $this->get_checkout_payment_url(),
            ];
        }
        if ($status !== WooCommerce::orderIsBeingEdited) {
            $actions[] = [
                'button'   => 'Email Order Confirmation',
                'endpoint' => 'sendEmailConfirmation',
            ];

            $actions[] = [
                'button' => 'Email Order Confirmation (PDF)',
                'url'    => $this->get_order_invoice_url(),
            ];

            $links[] = [
                'name' => 'Invoice',
                'url'  => $this->get_order_invoice_url(),
            ];

        }
        if ($this->contains_delivery_slot()) {
            $actions[] = [
                'button'   => 'Email Delivery Slot Confirmation',
                'endpoint' => 'sendDeliverySlot',
            ];

        }

        $notices = [];
        if (function_exists('wc_get_notices')) {
            $notices = wc_get_notices();
            wc_clear_notices();
        }

        $orderData = [
            // Remember to increment this when the structure of orderData changes.
            'version'               => self::orderDataVersion,
            'customer'              => [
                'email'             => $this->get_billing_email(),
                'phone'             => $this->get_billing_phone(),
                'first_name'        => $this->get_billing_first_name(),
                'last_name'         => $this->get_billing_last_name(),
                'tags'              => $this->get_customer_tags(),
                'customer_id'       => $customer->ID ?? null,
                'accepts_marketing' => $this->get_meta('_accepts_marketing'),
                'pastOrders'        => [],
            ],
            'billingAddress'        => [
                'company'   => $this->get_billing_company(),
                'address_1' => $this->get_billing_address_1(),
                'address_2' => $this->get_billing_address_2(),
                'city'      => $this->get_billing_city(),
                'state'     => $this->get_billing_state(),
                'postcode'  => $this->get_billing_postcode(),
                'country'   => $this->get_billing_country(),

            ],
            'shippingAddress'       => [
                'first_name' => $this->get_shipping_first_name(),
                'last_name'  => $this->get_shipping_last_name(),
                'company'    => $this->get_shipping_company(),
                'address_1'  => $this->get_shipping_address_1(),
                'address_2'  => $this->get_shipping_address_2(),
                'city'       => $this->get_shipping_city(),
                'state'      => $this->get_shipping_state(),
                'postcode'   => $this->get_shipping_postcode(),
                'country'    => $this->get_shipping_country(),
                'phone'      => $this->get_shipping_phone(),
            ],
            'tags'                  => $this->get_tags(),
            'availableTags'         => Order::get_available_tags(),
            'availableCustomerTags' => Customer::get_available_tags(),
            'containsDeliverySlot'  => $this->contains_delivery_slot(),
            'containsEvent'         => $this->contains_event(),
            'id'                    => $this->get_id(),
            'items'                 => [],
            'coupons'               => [],
            'shippingData'          => '',
            'giftCards'             => [],
            'transactions'          => [],
            'pendingCharge'         => $pendingCharge,
            'total'                 => $total,
            'subtotals'             => [
                'products'  => $this->get_product_total(),
                'coupons'   => $this->get_coupon_total(),
                'shipping'  => $this->get_shipping_total(),
                'giftCards' => $this->get_gift_card_total(),
                'payments'  => $this->get_total_payments(),
            ],
            'beingEdited'           => $this->get_status() == WooCommerce::orderIsBeingEdited,
            'status'                => $this->get_status(),
            'timeline'              => $this->get_timeline(),
            'createdDate'           => !is_null($this->get_date_created()) ? $this->get_date_created()->format('Y-m-d') : Carbon::now()->format('Y-m-d'),
            'modifiedDate'          => !is_null($this->get_date_modified()) ? $this->get_date_modified()->format('Y-m-d') : Carbon::now()->format('Y-m-d'),
            'paidDate'              => !is_null($this->get_date_paid()) ? $this->get_date_paid()->format('Y-m-d') : '',
            'revertsAt'             => 0,
            'canBeEdited'           => $this->is_editable(),
            'notices'               => $notices,
            'editUrl'               => $this->get_edit_order_url(),
            'originalStatus'        => '',
            'permissions'           => [
                'order_editor_make_product_free' => current_user_can(WooCommerce::CanMakeAProductFree),
                'order_editor_create_gift_card'  => current_user_can(WooCommerce::CanCreateAGiftCard),
            ],
            'actions'               => $actions,
            'links'                 => $links,
            'orderKey'              => $this->get_order_key(),
        ];

        $customer = $this->get_customer();
        $pastOrders = [];
        if ($customer) {

            $orders = $customer->getOrders();
            foreach ($orders as $order) {
                if ($order->get_id() !== $this->get_id()) {
                    $pastOrders[] = [
                        'id'      => $order->get_id(),
                        'date'    => $order->get_date_created()->format('Y-m-d'),
                        'amount'  => $order->get_total(),
                        'editUrl' => $order->get_edit_order_url(),
                    ];
                }
            }
        }
        $orderData['customer']['pastOrders'] = $pastOrders;
        $orderData['items'] = $this->generate_product_item_data();

        $couponItems = $this->get_coupon_items();
        foreach ($couponItems as $couponItem) {
            $data = $couponItem->get_data();
            $data['automatic_discount'] = $couponItem->get_meta('automatic_discount');
            unset($data['meta_data']);
            $orderData['coupons'][] = $data;
        }

        $giftCardItems = $this->get_gift_card_items();
        foreach ($giftCardItems as $giftCardItem) {
            $data = $giftCardItem->get_data();
            unset($data['meta_data']);
            $orderData['giftCards'][] = $data;
        }

        $transactionItems = $this->get_transaction_items();
        foreach ($transactionItems as $transactionItem) {
            $data = $transactionItem->get_data();
            unset($data['meta_data']);
            $orderData['transactions'][] = $data;
        }

        $shippingItem = $this->get_shipping_item();
        if ($shippingItem) {
            $orderData['shippingData'] = $shippingItem->get_data();
            $deliveryCompany = new DeliveryCompany($orderData['shippingData']['delivery_company_id']);
            if ($deliveryCompany) {
                $orderData['shippingData']['delivery_company'] = $deliveryCompany->post_title;
            }
        }

        /**
         * Calculate if there is a pending charge
         */
        if ($this->is_in_edit_mode()) {

            $originalOrder = $this->get_original_order();

            // Was the original order validate_order_before_checkoutpaid ?
            if ($originalOrder) {
                $orderData['revertsAt'] = $originalOrder->revertsAt;
                $orderData['originalStatus'] = $originalOrder->orderData['status'];
            }
        }

        //Prepare post meta for searching products in backend
        $products = '';

        foreach ($orderData['items'] as $item) {
            $products .= $item['name'] . ',';
        }


        update_post_meta($this->get_id(), '_order_products', $products);
        update_post_meta($this->get_id(), '_order_data', $orderData);
        return $orderData;

    }



    public function get_cart_error()
    {
        $message = get_post_meta($this->get_id(), '_cart_error', true);
        return $message ? $message : false;
    }



    /**
     * Build all data needed for checkout
     *
     * @param array $errors
     *
     * @return array
     */
    public function get_checkout_data($errors = [])
    {

        $notices = wc_get_notices();
        wc_clear_notices();

        $defaultShippingData = (object)[
            'amount'              => 0,
            'code'                => '',
            'date'                => '',
            'delivery_area_id'    => 0,
            'delivery_company_id' => 0,
            'delivery_note'       => '',
            'gift_message'        => '',
            'name'                => '',
            'postcode'            => '',
            'coupon'              => '',
        ];

        $pending = Number::decimal($this->get_pending_charge());
        $total = Number::decimal($this->get_total());
        $paid = Number::decimal($total - $pending);

        $productMessages = $this->get_meta('_product_messages');
        if (empty($productMessages)) {
            $productMessages = '';
        } else {
            $productMessages = '<p>' . implode('</p><p>', $productMessages) . '</p>';
        }

        $checkoutData = [
            'version'          => Theme::getVersion(),
            'checkoutUrl'      => $this->get_checkout_payment_url(),
            'zeroCheckoutUrl'  => home_url('/?order_complete=1&order=' . Hasher::encode($this->get_id())),
            'orderMinimum'     => 40,
            'items'            => [],
            'coupons'          => [],
            'shippingData'     => $defaultShippingData,
            'giftCards'        => [],
            'notices'          => $notices,
            'paid'             => $paid,
            'total'            => $total,
            'pending'          => $pending,
            'product_messages' => $productMessages,
            'customerData'     => [
                'deliverToDifferentAddress' => $this->get_meta('_deliver_to_different_address'),
                'customer'                  => [
                    'firstName'        => $this->get_billing_first_name(),
                    'lastName'         => $this->get_billing_last_name(),
                    'email'            => $this->get_billing_email(),
                    'phone'            => $this->get_billing_phone(),
                    'acceptsMarketing' => $this->get_meta('_accepts_marketing'),
                ],
                'billingAddress'            => [
                    'address1' => $this->get_billing_address_1(),
                    'address2' => $this->get_billing_address_2(),
                    'city'     => $this->get_billing_city(),
                    'state'    => $this->get_billing_state(),
                    'country'  => $this->get_billing_country(),
                    'postcode' => $this->get_billing_postcode(),
                ],
                'shippingAddress'           => [
                    'firstName' => $this->get_shipping_first_name(),
                    'lastName'  => $this->get_shipping_last_name(),
                    'address1'  => $this->get_shipping_address_1(),
                    'address2'  => $this->get_shipping_address_2(),
                    'city'      => $this->get_shipping_city(),
                    'state'     => $this->get_shipping_state(),
                    'country'   => $this->get_shipping_country(),
                    'postcode'  => $this->get_shipping_postcode(),
                    'phone'     => $this->get_shipping_phone(),
                ],

            ],
            'errors'           => (object)[
                'products'          => $this->get_cart_error(),
                'firstName'         => false,
                'lastName'          => false,
                'email'             => false,
                'phone'             => false,
                'billingAddress1'   => false,
                'billingCity'       => false,
                'billingState'      => false,
                'billingPostcode'   => false,
                'shippingFirstName' => false,
                'shippingLastName'  => false,
                'shippingAddress1'  => false,
                'shippingCity'      => false,
                'shippingPhone'     => false,
                'shippingPostcode'  => false,
            ],
        ];

        $step1Error = false;

        if (!empty($errors)) {
            foreach ($errors as $error => $message) {
                $step1Error = true;
                $checkoutData['errors']->$error = $message;
            }
        }

        // Add all the items in the cart and adjust the cart minimum
        $productItems = $this->get_items();
        $giftCardItems = $this->get_gift_card_items();
        $couponItems = $this->get_coupon_items();

        /**
         * @var WC_Order_Item_Product $productItem
         * @var WC_Product $product
         */
        foreach ($productItems as $productItem) {

            $product = $productItem->get_product();
            $quantity = $productItem->get_quantity();

            $ignoreMinimum = get_field('ignore_minimum', $product->get_id());
            if ($ignoreMinimum || $product->get_price() == 0) {
                $checkoutData['orderMinimum'] = 0;
            }

            $productToCheckID = $product->get_type() === 'variation' ? $product->get_parent_id() : $product->get_id();

            $data = $productItem->get_data();
            $data['id'] = $product->get_id();
            $data['type'] = $product->get_type();
            $data['product_id_to_check'] = $productToCheckID;
            $data['product'] = $product->get_name();
            $data['image'] = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail')[0];
            $data['quantity'] = $quantity;
            $data['total'] = Number::decimal($product->get_price() * $quantity);
            $data['price'] = Number::decimal($product->get_price());

            $checkoutData['items'][] = $data;
        }

        foreach ($couponItems as $couponItem) {
            $amount = $couponItem->get_discount();
            $discount_amount_html = $amount * -1;
            $checkoutData['coupons'][] = [
                'label'  => '',
                'code'   => $couponItem->get_code(),
                'amount' => $discount_amount_html,
            ];
        }

        foreach ($giftCardItems as $giftCardItem) {

            $card = GiftCard::getByCardNumber($giftCardItem->card_number);

            if (!$card->ID) {
                continue;
            }
            $checkoutData['giftCards'][] = [
                'number'    => $card->post_title,
                'balance'   => $card->balance,
                'amount'    => $giftCardItem->amount * -1,
                'remaining' => $card->balance + $giftCardItem->amountProcessed - $giftCardItem->amount,
                'expired'   => $card->hasExpired(),
            ];
        }

        // Only send shipping data, if everything else is OK.
        // This is because Vue watches for ShippingData.
        if (!$step1Error) {
            $shippingData = $this->get_shipping_item();
            if ($shippingData) {
                $checkoutData['shippingData'] = $shippingData->get_data();
            }
        }

        return $checkoutData;
    }



    /**
     * @param false $on_checkout
     *
     * @return string
     */
    public function get_checkout_payment_url($on_checkout = false)
    {
        return parent::get_checkout_payment_url($on_checkout); // Phase2: use a better url
    }



    /**
     * get the cote Customer ID
     *
     * @return mixed
     */
    public function get_cote_customer()
    {
        return get_post_meta($this->get_id(), '_cc_id', true);
    }



    /**
     * @return WC_Order_Item_Coupon[]
     */
    public function get_coupon_items()
    {
        return $this->get_items('coupon');
    }



    /**
     * Calculate the total used by coupons
     *
     * @return float
     */
    public function get_coupon_total()
    {
        $total = 0;
        $items = $this->get_coupon_items();
        foreach ($items as $item) {
            $total += Number::decimal($item->get_discount()) * -1;
        }

        return Number::decimal($total);

    }



    /**
     * @return Customer|false
     */
    public function get_customer()
    {
        if (!$this->customer) {

            $id = $this->get_cote_customer();

            $this->customer = new Customer($id);
        }
        return $this->customer;
    }



    /**
     * @param  $first_name
     * @param  $last_name
     * @param  $email
     * @param  $phone
     *
     * @return Order
     * @throws CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public function set_customer($first_name, $last_name, $email, $phone)
    {

        $this->check_if_order_is_editable();

        $errors = [];

        if ($email) {
            $response = Validate::email($email);
            if (!$response['valid']) {
                $errors['email'] = $response;
            }

        }

        if ($phone) {
            $response = Validate::tel($phone);
            if (!$response['valid']) {
                $errors['phone'] = $response;
            }
        }

        if (!empty($errors)) {
            throw CoteAtHomeException::withMessage('Validation Errors')
                ->withData($errors);
        }

        $this->set_billing_email($email);
        $this->set_billing_phone($phone);

        if ($first_name) {
            $this->set_billing_first_name($first_name);
        }
        if ($last_name) {
            $this->set_billing_last_name($last_name);
        }
        $this->save();
        return $this;
    }



    public function get_customer_tags()
    {
        $customer = $this->get_customer();

        if (!$customer) {
            return [];
        }

        return $customer->get_tags();
    }



    public function get_deliver_to_a_different_address()
    {
        return $this->get_meta('_deliver_to_different_address');
    }



    /**
     * get the coupon code used when this booking was made
     *
     * @return array|mixed|string
     */
    public function get_delivery_slot_code()
    {
        return $this->get_meta('_delivery_slot_coupon');
    }



    /**
     * Get the total amount available on all gift cards
     *
     * @return float
     */
    public function get_gift_card_balance()
    {
        $total = 0;
        $items = $this->get_gift_card_items();
        foreach ($items as $item) {
            $card = new GiftCard($item->card_number);

            $total += Number::decimal($card->balance);
        }

        return Number::decimal($total);
    }



    /**
     * Get all the gift cards used
     *
     * @return GiftCardItem[]
     */
    public function get_gift_card_items()
    {
        return $this->get_items(GiftCardItem::$type);
    }



    /**
     * Get the total of all Gift Cards used on the order.
     *
     * @return float
     */
    public function get_gift_card_total()
    {
        $total = 0;
        $items = $this->get_gift_card_items();
        foreach ($items as $item) {
            $total += Number::decimal($item->amount) * -1;
        }

        return Number::decimal($total);
    }



    /**
     *  Get all notes
     *
     * @return array
     */
    public function get_notes()
    {

        remove_filter('comments_clauses', ['WC_Comments', 'exclude_order_comments']);

        $comments = get_comments([
            'post_id' => $this->get_id(),
        ]);

        add_filter('comments_clauses', ['WC_Comments', 'exclude_order_comments']);

        $notes = [];
        foreach ($comments as $comment) {
            $notes[] = [
                'id'   => $comment->comment_ID,
                'date' => $comment->comment_date,
                'note' => $comment->comment_content,
                'type' => $comment->comment_author === 'WooCommerce' ? 'system' : 'user',
                'user' => $comment->comment_author,
            ];
        }
        return $notes;
    }



    /**
     * Get the number of Gift Cards on this order
     *
     * @return int
     */
    public function get_number_of_gift_cards()
    {
        return count($this->get_gift_card_items());
    }



    /**
     * get the latest order data.
     *
     * @return mixed
     */
    public function get_order_data()
    {
        $orderData = get_post_meta($this->get_id(), '_order_data', true);

        if (!$orderData || !isset($orderData['version']) || $orderData['version'] < self::orderDataVersion) {
            $orderData = $this->generate_order_data();
        }

        $orderData['permissions'] = [
            'order_editor_make_product_free' => current_user_can(WooCommerce::CanMakeAProductFree),
            'order_editor_create_gift_card'  => current_user_can(WooCommerce::CanCreateAGiftCard),
        ];

        return $orderData;

    }



    public function get_order_invoice_url()
    {
        return home_url('/?order-invoice=' . Hasher::encode($this->get_id()));
    }



    /**
     * Get the original order Item. The original order was created when this order went into edit mode.
     *
     * @return false|OriginalOrderItem
     */
    public function get_original_order()
    {

        /**
         * @var OriginalOrderItem[] $originalOrderItems
         */
        $originalOrderItems = $this->get_items(OriginalOrderItem::$type);

        if (empty($originalOrderItems)) {
            return false;
        }

        return current($originalOrderItems);

    }



    /**
     * Get the original charge for this order.
     * Specific to SagePay
     *
     * @return mixed
     */
    public function get_original_transaction_id()
    {
        return get_post_meta($this->get_id(), '_RelatedVPSTxId', true);
    }



    /**
     * The payment method
     * TODO: add to wp_shop_sessions
     *
     * @return string
     */
    public function get_payment_method_used()
    {

        if (count($this->get_transaction_items()) > 0) {
            return static::paymentMethodCard;
        }
        if (count($this->get_gift_card_items()) > 0) {
            return static::paymentMethodGiftCard;
        }

        return static::paymentMethodNone;

    }



    /**
     * Determine the pending charge for the order.
     * This can be positive or negative.
     *
     * @return float
     */
    public function get_pending_charge()
    {

        // Nothing is due !
        if (!in_array($this->get_status(), [WooCommerce::orderIsBeingEdited, WooCommerce::orderIsPendingPayment, WooCommerce::orderIsACart])) {
            return Number::decimal(0);
        }
        if ($this->get_total_payments() > 0) {
            return Number::decimal($this->get_total()) - $this->get_total_payments();
        }

        $originalOrder = $this->get_original_order();

        if ($originalOrder && $this->get_total_payments() > 0) {
            return Number::decimal($this->get_total()) - Number::decimal($originalOrder->orderData['total']);
        }

        return Number::decimal($this->get_total());
    }



    /**
     * Get all product items
     *
     * @return WC_Order_Item_Product[]
     */
    public function get_product_items()
    {
        return $this->get_items('line_item');
    }



    /**
     * Get the total for all products
     *
     * @return float
     */
    public function get_product_total()
    {
        $total = 0;
        $items = $this->get_product_items();
        foreach ($items as $item) {

            // We use subtotal as WooCommerce adjusts the total based on coupons.
            $total += Number::decimal($item->get_subtotal());
        }

        return Number::decimal($total);

    }



    /**
     * A URl that will allow the user to continue checking out
     *
     * @return string|void
     */
    public function get_recover_order_url()
    {
        return home_url('/checkout/?continue_order=1&order=' . Hasher::encode($this->get_id()));
    }



    /**
     * Work out available delivery dates given all sorts of conditions
     *
     * @param $postcode
     *
     * @return array
     * @throws CoteAtHomeException
     */
    public function get_shipping_dates($postcode)
    {
        //cleanup
        $postcode = trim(strtoupper($postcode));

        $deliveryArea = DeliveryArea::getbyPostcode($postcode);

        //Check if the postcode is blocked

        $blockedMarker = false;
        if ($deliveryArea->blocked_postcodes != null) {
            $blocked = explode(',', $deliveryArea->blocked_postcodes);

            $workingPostCode = $postcode;
            while (strlen($workingPostCode) > 0) {
                if (in_array($workingPostCode, $blocked)) {
                    $blockedMarker = true;
                }
                $workingPostCode = substr($workingPostCode, 0, -1);
            }
        }

        if (!$deliveryArea || $blockedMarker == true) {
            throw CoteAtHomeException::withMessage("Unfortunately we do not currently deliver to your area ($postcode)");
        }


        $preBookedDate = false;
        $preBookedDateAvailable = false;
        $forceDeliveryCompanyID = false;

        // Who is using this?
        $powerUser = current_user_can('shop_user_plus') || current_user_can('shop_user') || current_user_can('shop_admin');

        // The number of days to show.
        $days = Settings::get('days_ahead', 45);
        $startDate = Time::utcNow();
        $endDate = Time::utcNow()->addDays($days)->endOfDay();
        $cutOffTime = Time::utcNow()->setTimeFromTimeString($deliveryArea->cut_off_time);

        // Do we allow a FREE Delivery with Cote option
        $showCoteDelivery = Settings::get('show_cote_delivery', false) && $powerUser;
        $firstAvailableDate = false;
        $orderAmount = $this->get_subtotal();

        $dayData = [];
        $disabledDates = [];

        // Get the number of days lead
        $leadDays = $deliveryArea->cut_off_days;

        // Add one more day if we're after today's cut off
        if ($startDate->isAfter($cutOffTime)) {
            $leadDays++;
        }

        // allow power users an additional day
        if ($powerUser) {
            $leadDays = $leadDays - 1;
        }

        // Set out lead date.
        $startDate->addDays($leadDays);

        $freeDeliveryFromDate = $startDate->clone();
        $freeDeliveryToDate = $endDate->clone();
        $couponDeliveryMessage = '';

        $productDeliveryFromDate = $startDate->clone();;
        $productDeliveryToDate = $endDate->clone();
        $productDeliveryMessage = '';

        $comments = [];

        // Check if any of the coupons allow free shipping.
        $freeDeliveryWithCoupon = false;
        foreach ($this->get_coupon_items() as $couponItem) {
            $code = $couponItem->get_code();
            $coupon_id = wc_get_coupon_id_by_code($code);
            $coupon = new WC_Coupon($coupon_id);
            if ($coupon && $coupon->get_free_shipping()) {
                $freeDeliveryWithCoupon = $code;

                // Check Delivery limitations on free shipping ( Y-m-d )
                $deliveryFrom = get_field('delivery_from', $coupon_id);
                if ($deliveryFrom) {
                    $freeDeliveryFromDate = Carbon::createFromFormat('Y-m-d', $deliveryFrom)->setTimezone(Time::tz())->startOfDay();
                }

                $deliveryTo = get_field('delivery_to', $coupon_id);
                if ($deliveryTo) {
                    $freeDeliveryToDate = Carbon::createFromFormat('Y-m-d', $deliveryTo)->setTimezone(Time::tz())->endOfDay();
                }

                if (!$couponDeliveryMessage && $deliveryFrom && !$deliveryTo) {
                    $couponDeliveryMessage = 'The discount code ' . $code . ' can only be used for deliveries from ' . $freeDeliveryFromDate->format('jS F Y');
                } else if (!$couponDeliveryMessage && !$deliveryFrom && $deliveryTo) {
                    $couponDeliveryMessage = 'The discount code ' . $code . ' can only be used for deliveries to ' . $freeDeliveryToDate->format('jS F Y');
                } else if (!$couponDeliveryMessage && $deliveryFrom && $deliveryTo) {
                    $couponDeliveryMessage = 'The discount code ' . $code . ' can only be used for deliveries from ' . $freeDeliveryFromDate->format('jS F Y') . ' to ' . $freeDeliveryToDate->format('jS F Y');
                }
            }

            // Does this coupon have a pre-booked date?
            $attachedToDelivery = get_field('delivery', $coupon_id);

            if ($attachedToDelivery) {
                $couponOrderID = get_field('order_id', $coupon_id);
                // if there an order ID - get the date from that.
                if ($couponOrderID) {
                    //get the delivery date attached to the order
                    $couponDeliveryDay = get_post_meta($couponOrderID, '_delivery_date', true);
                } else {
                    $couponDeliveryDay = get_field('delivery_date', $coupon_id);
                }

                if ($couponDeliveryDay) {
                    $preBookedDate = Carbon::createFromFormat('Y-m-d', $couponDeliveryDay);
                }
            }

            // All events have a specific delivery company - let's use that
            $event = Event::getByCouponCode($code);
            if ($event && !$forceDeliveryCompanyID) {
                $forceDeliveryCompanyID = $event->delivery_company_id;
            }
        }

        // Check if a Gift card has an order ID on it, or a delivery_date
        foreach ($this->get_gift_card_items() as $giftCardItem) {
            $giftCard = GiftCard::getByCardNumber($giftCardItem->card_number);
            if ($giftCard && $giftCard->isSlotCard() && $giftCard->order_id) {
                //get the delivery date attached to the order
                $couponDeliveryDay = get_post_meta($giftCard->order_id, '_delivery_date', true);
                if ($couponDeliveryDay) {
                    $preBookedDate = Carbon::createFromFormat('Y-m-d', $couponDeliveryDay);
                }
            }
        }

        // Go through all the products and see if any of them have delivery restrictions
        $items = $this->get_product_items();
        foreach ($items as $orderItem) {

            $product = $orderItem->get_product();

            // Grab the parent id as that's were limitations are stored.
            $id = $product->get_parent_id() > 0 ? $product->get_parent_id() : $product->get_id();
            $deliveryFrom = get_field('delivery_from', $id);
            $deliveryTo = get_field('delivery_to', $id);

            if ($deliveryFrom) {
                $productDeliveryFromDate = Carbon::createFromFormat('Y-m-d', $deliveryFrom)->setTimezone(Time::tz())->startOfDay();
            }
            if ($deliveryTo) {
                $productDeliveryToDate = Carbon::createFromFormat('Y-m-d', $deliveryTo)->setTimezone(Time::tz())->endOfDay();
            }

            if (!$productDeliveryMessage && $deliveryFrom && !$deliveryTo) {
                $productDeliveryMessage = '"' . $product->get_name() . '" can only be delivered from ' . $productDeliveryFromDate->format('jS F Y');
            } else if (!$productDeliveryMessage && !$deliveryFrom && $deliveryTo) {
                $productDeliveryMessage = '"' . $product->get_name() . '" can only be delivered to ' . $productDeliveryToDate->format('jS F Y');
            } else if (!$productDeliveryMessage && $deliveryFrom && $deliveryTo) {
                $productDeliveryMessage = '"' . $product->get_name() . '" can only be delivered from ' . $productDeliveryFromDate->format('jS F Y') . ' to ' . $productDeliveryToDate->format('jS F Y');
            }
        }

        if ($freeDeliveryToDate && $productDeliveryFromDate && $freeDeliveryToDate->isBefore($productDeliveryFromDate)) {
            throw CoteAtHomeException::withMessage("Unfortunately we cannot offer any dates as $couponDeliveryMessage and $productDeliveryMessage.");
        }

        if ($freeDeliveryFromDate && $productDeliveryToDate && $freeDeliveryFromDate->isAfter($productDeliveryToDate)) {
            throw CoteAtHomeException::withMessage("Unfortunately we cannot offer any dates as $couponDeliveryMessage and $productDeliveryMessage.");
        }

        // get the latest date
        $startDate->setDateFrom(Time::latest([$startDate, $freeDeliveryFromDate, $productDeliveryFromDate]));

        // get the last date
        $closestDate = Time::soonest([$freeDeliveryToDate, $productDeliveryToDate]);

        if ($closestDate->isBefore($endDate)) {
            $endDate->setDateFrom($closestDate);
        }

        // If we're power user - make sure we start from the Shipping Date
        // just to make sure it's always available
        if ($powerUser && $this->get_status() === WooCommerce::orderHasBeenPaid) {
            $shippingData = $this->get_shipping_item();
            if ($shippingData && $shippingData->date) {
                $currentShippingDate = Carbon::createFromFormat('Y-m-d', $shippingData->date);
                if ($currentShippingDate->isBefore($startDate)) {
                    $startDate->setDateFrom($currentShippingDate);
                }
            }
        }

        // Work out our period.
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $deliveryDate) {

            $notes = '';

            $day = strtolower($deliveryDate->format('l'));

            //Who is delivering today?
            $deliveryCompanyID = $forceDeliveryCompanyID ? $forceDeliveryCompanyID : $deliveryArea->$day;

            $maximumDeliveries = 0;
            $numberOfOrders = 0;
            $spillOverFrom = '';

            $status = Date::deliveryDateAvailable;

            while (true) {

                $deliveryCompany = new DeliveryCompany($deliveryCompanyID);

                // This should never happen :)
                if (!$deliveryCompany) {
                    Log::error('Cannot find delivery Company for id:' . $deliveryCompanyID);
                    break;
                }

                // What's the limit for this day?
                $maximumDeliveries = (int)$deliveryCompany->$day;

                // Check over rides
                foreach ($deliveryCompany->overrides as $override) {
                    if ($override['date'] === $deliveryDate->format('Y-m-d')) {
                        $maximumDeliveries = (int)$override['limit'];
                        $notes = 'Delivery company date override (frontend)';
                        if ($powerUser && isset($override['backend_limit']) && $override['backend_limit'] > 0) {
                            $maximumDeliveries = (int)$override['backend_limit'];
                            $notes = 'Delivery company date override (backend)';
                        }
                        break;
                    }
                }

                // MaximumDeliveries is now set , 0 = disabled for this delivery Company
                // A positive number means we can deliver on this day.
                if ($maximumDeliveries === 0) {
                    $status = Date::deliveryDateDisabled;
                } else {

                    // There are a couple of conditions when we should not check the limits
                    if (
                        // #1 If the date was attached from a coupon, just let people book it. It's probably a delivery slot.
                        $preBookedDate && $preBookedDate->isSameDay($deliveryDate)
                        // #2 if the current user can shop_user_plus
                        // || $powerUser  - Turned off for now
                    ) {
                        break;
                    }

                    // Order Count
                    $numberOfOrders = Date::getOrderCount($deliveryDate, $deliveryCompany->ID);

                    //We're good - this delivery company can handle the order
                    if ($numberOfOrders < $maximumDeliveries) {
                        $status = Date::deliveryDateAvailable;
                        break;
                    } else {
                        $status = Date::deliveryDateFull;
                    }
                }

                // is there a spill over and we're not enforcing a delivery company?
                if ($deliveryCompany->spill_over_id && !$forceDeliveryCompanyID) {
                    $spillOverFrom = $deliveryCompany->post_title;
                    $deliveryCompanyID = $deliveryCompany->spill_over_id;
                    continue;
                }
                break;

            }

            if (!$deliveryCompany) {
                $status = Date::deliveryDateDisabled;
            }

            // new ...  make sure a prebooked date is the only date showing.
            if ($preBookedDate && $preBookedDate->isAfter(Time::now()) && !$preBookedDate->isSameDay($deliveryDate)) {
                $status = Date::deliveryDateDisabled;
            }

            // By now the $status  and $deliveryCompany have been set.
            // Can do ?

            if ($status === Date::deliveryDateAvailable) {

                $validPrices = [];

                // Allow Power users to show a free option
                if ($showCoteDelivery) {
                    $validPrices[] = [
                        'code'  => 'CÔTE',
                        'name'  => 'Free Delivery from Côte',
                        'price' => 0,
                    ];
                }


                $workingPostCode = $postcode;

                // Go through all the prices
                foreach ($deliveryCompany->prices as $price) {

                    if ($price['hidden'] && !$powerUser) {
                        continue;
                    }

                    //Check if this postcode allows for premium deliveries

                    if ($price['blocked_postcodes'] != null) {
                        $premiumBlockedPostCodes = explode(',', $price['blocked_postcodes']);
                        $found = false;
                        while (strlen($workingPostCode) > 0) {
                            if (in_array($workingPostCode, $premiumBlockedPostCodes)) {
                                $found = true;
                            }
                            $workingPostCode = substr($workingPostCode, 0, -1);
                        }
                        if ($found) {
                            continue;
                        }
                    }


                    // Both limits set?
                    if ($price['from'] && $price['to']) {
                        if ($orderAmount < Number::decimal($price['from']) || $orderAmount > Number::decimal($price['to'])) {
                            continue;
                        }
                    }
                    // Just from
                    if ($price['from'] && Number::decimal($price['from']) > $orderAmount) {
                        continue;
                    }

                    // Just to
                    if ($price['to'] && Number::decimal($price['to']) < $orderAmount) {
                        continue;
                    }

                    // aha! price must be valid
                    $validPrices[] = $price;
                }

                // Do any of the coupon codes allow Free Delivery?
                if ($freeDeliveryWithCoupon) {

                    $freePrices = [];

                    $freePrices[] = [
                        'code'  => 'FREE',
                        'name'  => 'Free delivery with coupon: ' . strtoupper($freeDeliveryWithCoupon),
                        'price' => 0,
                    ];

                    // let's add back any premium options - just in case the customer would like
                    // to use them
                    foreach ($validPrices as $price) {
                        if (!in_array($price['code'], ['CÔTE', 'STANDARD', 'FREE'])) {
                            $freePrices[] = $price;
                        }
                    }
                    $validPrices = $freePrices;
                }

                if (!$firstAvailableDate) {
                    $firstAvailableDate = $deliveryDate->clone();
                }
                if ($preBookedDate && !$preBookedDateAvailable && $preBookedDate->isSameDay($deliveryDate)) {
                    $preBookedDateAvailable = true;
                }

                $dayData[] = [
                    'available'           => true,
                    'date'                => $deliveryDate->format('Y-m-d'),
                    'day'                 => $deliveryDate->format('l'),
                    'delivery_company_id' => $deliveryCompany->ID,
                    'delivery_company'    => $deliveryCompany->post_title,
                    'delivery_area_id'    => $deliveryArea->ID,
                    'delivery_area'       => $deliveryArea->post_title,
                    'limit'               => $maximumDeliveries,
                    'orders'              => $numberOfOrders,
                    'notes'               => $notes,
                    'prices'              => $validPrices,
                    'status'              => $status,
                    'spill_over_from'     => $spillOverFrom,
                ];

            } else {
                $disabledDates[] = $deliveryDate->format('Y-m-d');
                $dayData[] = [
                    'available' => false,
                    'date'      => $deliveryDate->format('Y-m-d'),
                    'day'       => $deliveryDate->format('l'),
                    'limit'     => $maximumDeliveries,
                    'orders'    => $numberOfOrders,
                    'notes'     => $notes,
                    'status'    => $status,
                ];
            }
            $deliveryDate->addDays(1);
        }

        if (count($dayData) === 0) {
            throw CoteAtHomeException::withMessage("Sorry we do not have any delivery dates available");
        }

        if (!$firstAvailableDate) {
            throw CoteAtHomeException::withMessage("Sorry we do not have any delivery dates available");
        }

        if ($couponDeliveryMessage) {
            $comments[] = $couponDeliveryMessage;
        }

        if ($productDeliveryMessage) {
            $comments[] = $productDeliveryMessage;
        }

        //What is the date we choose is no longer available?
        if ($preBookedDate) {

            if (!$preBookedDateAvailable) {
                $comments[] = ' Your pre-booked date of ' . $preBookedDate->format('jS F Y') . ' is no longer available. Please choose a new date below';
                $preBookedDate->setDateFrom($firstAvailableDate);
            } else {
                $firstAvailableDate->setDateFrom($preBookedDate);
                $comments[] = ' Your pre-booked date of ' . $preBookedDate->format('jS F Y') . ' has been selected';
            }
        }

        return [
            'dates'              => $dayData,
            // dates up to the start date are disabled
            'startDate'          => $startDate->format('Y-m-d'),
            // dats after the end date are disabled
            'endDate'            => $endDate->format('Y-m-d'),
            // Use this date when not date has ben selected
            'firstAvailableDate' => $firstAvailableDate->format('Y-m-d'),
            //The array
            'disabledDates'      => $disabledDates,
            // Not really needed - useful for debugging
            'orderAmount'        => $orderAmount,
            // Not really needed - useful for debugging
            'days'               => $days,
            'comments'           => implode('. ', $comments),
        ];

    }



    /**
     * @return ShippingItem|false
     */
    public function get_shipping_item()
    {
        /**
         * @var ShippingItem[] $shippingItems
         */
        $shippingItems = $this->get_items(ShippingItem::$type);
        if (empty($shippingItems)) {
            return false;
        }
        return current($shippingItems);

    }



    /**
     * Get the shipping phone number
     *
     * @return array|mixed|string|null
     */
    public function get_shipping_phone()
    {
        $shippingPhone = $this->get_meta('_shipping_phone');
        $billingPhone = $this->get_billing_phone();
        return $shippingPhone ? $shippingPhone : $billingPhone;
    }



    /**
     * Get the Shipping total
     *
     * @param string $context
     *
     * @return float|int|string
     */
    public function get_shipping_total($context = 'view')
    {
        $shippingItem = $this->get_shipping_item();
        if ($shippingItem) {
            return Number::decimal($shippingItem->amount);
        }
        return 0;
    }



    public function get_shop_session()
    {
        $shopSession = new ShopSession($this->get_id());
        $shopSession->status = WooCommerce::getStatusName($this->get_status());
        return $shopSession;
    }



    public function get_timeline()
    {
        $notes = $this->get_notes();
        $transactions = $this->get_transaction_items();

        $timeline = [];

        foreach ($notes as $note) {

            $timestamp = new Carbon($note['date']);
            $timeline[] =
                [
                    'date' => $timestamp->setTimezone(Time::tz())->getTimestamp(),
                    'type' => 'note',
                    'data' => $note,
                ];

        }
        foreach ($transactions as $transaction) {
            if (!$transaction->date) {
                $transaction->date = Carbon::now()->setTimezone(Time::tz())->format('Y-m-d H:i:s');
            }
            $timestamp = new Carbon($transaction->date);

            $data = $transaction->get_data();
            $data['success'] = $data['success'] ? true : false;

            $timeline[] =

                [
                    'date' => $timestamp->setTimezone(Time::tz())->getTimestamp(),
                    'type' => 'transaction',
                    'data' => $data,
                ];

        }

        usort($timeline, function ($item1, $item2) {
            return $item1['date'] <=> $item2['date'];
        });

        return $timeline;

    }



    /**
     * get the total amount paid for this order
     *
     * @return float
     */
    public function get_total_amount_charged()
    {

        $transactions = $this->get_transaction_items();

        if (empty($transactions)) {
            return Number::decimal(0);
        }

        $total = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->transaction_type !== 'Refund') {
                $total += Number::decimal($transaction->amount);
            }
        }

        return Number::decimal($total);
    }



    /**
     * get the total amount refunded
     *
     * @return float
     */
    public function get_total_amount_refunded()
    {

        $transactions = $this->get_transaction_items();

        if (empty($transactions)) {
            return Number::decimal(0);
        }

        $total = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->transaction_type === 'Refund') {
                $total += Number::decimal($transaction->amount);
            }
        }

        return Number::decimal($total);
    }



    /**
     * get the total amount paid for this order
     *
     * @return float
     */
    public function get_total_payments()
    {

        $transactions = $this->get_transaction_items();

        if (empty($transactions)) {
            return Number::decimal(0);
        }

        $total = 0;
        foreach ($transactions as $transaction) {
            if ($transaction->balance > 0) {
                $total += Number::decimal($transaction->balance);
            }
        }

        return Number::decimal($total);
    }



    /**
     * get all transactions for this order
     *
     * @return TransactionItem[]
     */
    public function get_transaction_items()
    {
        return $this->get_items(TransactionItem::$type);
    }



    /**
     * Does this order already have a coupon code.
     *
     * @param $code
     *
     * @return bool
     */
    public function has_coupon($code)
    {
        $coupons = $this->get_coupon_items();

        if (empty($coupons)) {
            return false;
        }

        foreach ($coupons as $couponItem) {
            if ($couponItem->get_code() === $code) {
                return true;
            }
        }

        return false;

    }



    /**
     * Check if this order in in edit mode
     *
     * @return bool
     */
    public function is_in_edit_mode()
    {
        return $this->get_status() === WooCommerce::orderIsBeingEdited;
    }



    /**
     * Handy !Not
     *
     * @return bool
     */
    public function is_not_editable()
    {
        return !$this->is_editable();
    }



    /**
     * Handy function
     *
     * @return bool
     */
    public function is_not_in_edit_mode()
    {
        return !$this->is_in_edit_mode();
    }



    /**
     * Leave edit mode
     * This is waht we need to do.
     *  - Adjust Gift Cards
     *  - refund or charge the customer
     *  - email customer
     *  - Fire event into Exponea
     *
     * @param bool $save
     *
     * @return Order
     * @throws CoteAtHomeException|WC_Data_Exception
     */
    public function leave_edit_mode($save = true)
    {
        // Duh!
        $this->check_if_order_is_editable();

        // not sure why we need this.
        //$this->recalculateAndSave();

        // if we're not saving, then we need to revert this back to the state it was in before editing.
        if ($save === false) {
            return $this->revert();
        }

        // Save the order !
        $originalOrder = $this->get_original_order();
        $amountPending = $this->get_pending_charge();

        //  We need to make a charge....
        if ((float)$amountPending !== (float)0) {

            switch ($this->get_payment_method_used()) {

                case static::paymentMethodNone :
                    $this->delete_original_order_data();
                    break;

                case static::paymentMethodCard :
                    if ($amountPending > 0) {
                        try {
                            $this->charge($amountPending);
                        } catch (CoteAtHomeException $e) {
                            // Set the order as pending
                            $note = 'The payment failed, please send the payment link to the customer';
                            $this->add_note($note);
                            $this->add_notice($note);
                            $this->set_status(WooCommerce::orderIsPendingPayment);
                            $this->save();
                            return $this;
                        }
                    }
                    if ($amountPending < 0) {

                        try {
                            $this->refund($amountPending * -1);
                        } catch (CoteAtHomeException $e) {
                            $note = "The refund failed, the order has been reverted to it's original state";
                            $this->add_note($note);
                            $this->add_notice($note);
                            $this->revert($note);
                            return $this;

                        }
                    }
                    break;

                case static::paymentMethodGiftCard :
                    if ($amountPending > 0) {
                        // Shit - we have a problem - We don't have the amount available on the gift cards
                        $this->set_status(WooCommerce::orderIsPendingPayment);
                        $note = "This order now needs to be paid, please send the payment link to the customer";
                        $this->add_note($note);
                        $this->save();
                        $this->add_notice($note);
                        return $this;
                    }
                    if ($amountPending < 0) {
                        // Oh crap... will this ever happen?  if so how will we refund the customer
                    }

                    break;
            }
        }
        $this->set_status($originalOrder->orderData['status']);
        $this->recalculate_and_save();
        return $this;
    }



    /**
     * Start processing this order.
     *
     * @param string $note
     *
     * @return $this
     */
    public function mark_as_fulfilled($note = 'Order fulfilled')
    {
        if ($this->get_status() === WooCommerce::orderHasBeenFulfilled) {
            return $this;
        }

        $this->set_status(WooCommerce::orderHasBeenFulfilled);
        $this->add_note($note);
        $this->save();
        return $this;
    }



    /**
     * Mark an order as paid.
     *
     * @return $this
     */
    public function mark_as_paid()
    {
        $this->add_order_note('The order was marked as paid');
        $this->set_status('processing');
        $this->save();
        return $this;
    }



    public function maybe_apply_automatic_coupons()
    {

        $automaticCoupons = Options::get('automatic_coupons', []);

        if (empty($automaticCoupons)) {
            return;
        }

        $productItems = $this->get_product_items();

        foreach ($automaticCoupons as $code => $automaticData) {

            $code = strtoupper($code);

            try {
                // Just in case they have deleted the qualifying products
                // we remove the coupon from the cart
                $coupons = $this->get_coupon_items();
                foreach ($coupons as $coupon) {
                    if (strtoupper($coupon->get_code()) === $code) {
                        $this->remove_item($coupon->get_id());
                        $this->save();
                        break;
                    }
                }

                // let's hopefully throw some errors
                $this->can_coupon_be_added($code);
            } catch (CoteAtHomeException $e) {
                Log::debug("maybe_apply_automatic_coupons $code: " . $e->getMessage());
                continue;
            }

            $coupon = new WC_Coupon($automaticData->coupon_id);

            if ($automaticData->when === 'product') {
                //Phase2: products
            } else if ($automaticData->when === 'category') {

                if ($automaticData->categories->category_type === 'quantity') {
                    $requiredCategoryIDs = $automaticData->categories->category_ids;
                    $requiredQuantity = $automaticData->categories->category_quantity;
                    $foundQuantity = 0;
                    $subtotal = 0;

                    foreach ($productItems as $productItem) {

                        $product = $productItem->get_product();
                        $productCategoryIDs = $product->get_category_ids();

                        $found = count(array_intersect($productCategoryIDs, $requiredCategoryIDs));
                        if ($found) {
                            $foundQuantity += (int)$productItem->get_quantity();
                            $subtotal += Number::decimal($productItem->get_subtotal());
                        }

                    }

                    if ($foundQuantity >= $requiredQuantity) {

                        if ($coupon->get_discount_type() == 'percent') {
                            $discountAmount = Number::decimal($coupon->get_amount()) * $subtotal / 100;
                        } else {
                            $discountAmount = $coupon->get_amount();
                        }

                        // This was deleted - so we need to add it again.
                        $item = new WC_Order_Item_Coupon();
                        $item->set_code($code);
                        $item->set_discount(0);
                        // This is used later when calculating the order,
                        $item->update_meta_data('automatic_discount', $discountAmount);
                        $item->save();
                        $this->add_item($item);

                        // we do this here as add_coupon checks to make sure a coupon is not automatic.
                        // the amount is calculated on a filter hooks in Coupons
                        $this->recalculate_and_save();
                    }

                } else if ($automaticData->categories->category_type === 'amount') {
                    //Phase2 !!!
                }
            }
        }
    }



    function maybe_assign_to_an_event()
    {

        $coupons = $this->get_coupon_items();

        foreach ($coupons as $couponItem) {
            $code = $couponItem->get_code();

            //Check if there is an event
            $event = Event::getByCouponCode($code);
            if ($event) {
                if (in_array($this->get_status(), [WooCommerce::orderHasBeenPaid, WooCommerce::orderHasBeenFulfilled, WooCommerce::orderHasBeenProduced])) {
                    $event->allocate($this->get_id());
                } else {
                    $event->deallocate($this->get_id());
                }
            }
        }

        return $this;
    }



    /**
     * Original Data is used to work out how many items have been refunded for the reports
     */
    public function maybe_capture_original_order()
    {

        $originalOrder = $this->get_meta('_original_order');
        if (!$originalOrder) {
            update_post_meta($this->get_id(), '_original_order', $this->generate_order_data());
        }

        return $this;
    }



    public function maybe_credit_gift_cards()
    {

        $giftCardItems = $this->get_gift_card_items();

        foreach ($giftCardItems as $giftCardItem) {
            $giftCard = GiftCard::getByCardNumber($giftCardItem->card_number);
            if (!$giftCard) {
                continue;
            }
            $giftCard->adjustBalance($giftCardItem->amount, "order_id: {$this->get_id()} " . $this->get_status());

            if ($giftCard->isSlotCard() && $giftCard->used_order_id) {
                $giftCard->logActivity('slot', 0, "Order {$giftCard->used_order_id} was cancelled. We reset the usage.", false);
                $giftCard->used_order_id = '';
                $giftCard->save();
            }

            $giftCardItem->processed = false;
            $giftCardItem->amount = 0;
            $giftCardItem->amountProcessed = 0;
            $giftCardItem->save();
            $this->add_item($giftCardItem);
        }
        $this->save();

        return $this;

    }



    /**
     * If this order contains a booking slot - Schedule the reminder emails.
     *
     * @return $this
     */
    public function maybe_schedule_booking_slot_reminder_emails()
    {
        //Remove all events
        $args1 = [
            'order_id' => $this->get_id(),
            'reminder' => 1,
        ];
        $args2 = [
            'order_id' => $this->get_id(),
            'reminder' => 2,
        ];

        //Remove anything that's currently Scheduled
        wp_clear_scheduled_hook('future_send_booking_slot_reminder', $args1);
        wp_clear_scheduled_hook('future_send_booking_slot_reminder', $args2);

        if (!$this->contains_delivery_slot()) {
            return $this;
        }

        if ($this->get_status() !== WooCommerce::orderHasBeenPaid) {
            return $this;
        }

        $product = current($this->get_product_items());
        if (!$product) {
            Log::error('Order ' . $this->get_id() . ' could not find product when sending delivery slot');
            return $this;
        }

        $code = $this->get_delivery_slot_code();

        if (GiftCard::looksLikeAGiftCard($code)) {
            $giftCard = GiftCard::getByCardNumber($code);
            if (!$giftCard || ($giftCard->isSlotCard() && $giftCard->used_order_id)) {
                return $this;
            }
        } else {
            $coupon_id = wc_get_coupon_id_by_code($code);
            if (!$coupon_id) {
                return $this;
            }
            $used = get_field('used_order_id', $coupon_id);
            if ($used) {
                return $this;
            }

        }

        $product_id = $product->get_product_id();
        $reminder1Days = get_field('reminder_1_days', $product_id);
        $reminder1Time = get_field('reminder_1_time', $product_id);
        $reminder2Days = get_field('reminder_2_days', $product_id);
        $reminder2Time = get_field('reminder_2_time', $product_id);

        //Defaults
        $reminder1Time = $reminder1Time ?? '11:00';
        $reminder2Time = $reminder2Time ?? '11:00';
        $reminder1Days = $reminder1Days ?? 9;
        $reminder2Days = $reminder2Days ?? 4;

        $reminder1Time = explode(':', $reminder1Time);
        $reminder2Time = explode(':', $reminder2Time);

        $reminder1Hour = $reminder1Time[0];
        $reminder1Minute = $reminder1Time[1];
        $reminder2Hour = $reminder2Time[0];
        $reminder2Minute = $reminder2Time[1];

        $shippingItem = $this->get_shipping_item();
        $cutOff = Time::now();
        $notification1 = Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->subDays($reminder1Days)->setTime($reminder1Hour, $reminder1Minute);
        $notification2 = Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->subDays($reminder2Days)->setTime($reminder2Hour, $reminder2Minute);

        $dates = [];

        if ($notification1->isAfter($cutOff)) {
            wp_schedule_single_event($notification1->getTimestamp(), 'future_send_booking_slot_reminder', $args1);
            $dates[] = $notification1->format('jS F Y H:i');
        }
        if ($notification2->isAfter($cutOff)) {
            wp_schedule_single_event($notification2->getTimestamp(), 'future_send_booking_slot_reminder', $args2);
            $dates[] = $notification2->format('jS F Y H:i');
        }
        if (!empty($dates)) {
            $this->add_note("Previous booking slot reminders deleted. Scheduled booking slot reminder emails for: " . implode(' and ', $dates));
            $this->save();
        }

        return $this;
    }



    /**
     * If this order contains a booking slot - Schedule the reminder emails.
     *
     * @return $this
     */
    public function maybe_schedule_review_request()
    {
        //Remove all events
        $args = [
            'order_id' => $this->get_id(),
        ];

        //Remove anything that's currently Scheduled
        wp_clear_scheduled_hook('future_send_review_request', $args);

        if ($this->contains_delivery_slot() || $this->contains_event()) {
            Log::info("maybe_schedule_review_request: failed for " . $this->get_id() . " order contains event or delivery slot ");
            return $this;
        }

        if (!$this->get_billing_email()) {
            Log::info("maybe_schedule_review_request: failed for " . $this->get_id() . " order does not have an email address");
            return $this;
        }

        if ($this->get_status() !== WooCommerce::orderHasBeenFulfilled) {
            Log::info("maybe_schedule_review_request: failed for " . $this->get_id() . " order has not been fulfilled");
            return $this;
        }

        $shippingItem = $this->get_shipping_item();
        if (!$shippingItem || !$shippingItem->date) {
            Log::info("maybe_schedule_review_request: failed for " . $this->get_id() . " no shipping date");
            return $this;
        }

        // Check for last review
        $customer = Customer::getById($this->get_cote_customer());
        if ($customer) {
            $lastReview = get_post_meta($customer->ID, 'last_cah_review', true);

            // Only continue this logic if there has been a previous review.
            // previous reviews are pulled in from Hub (from hgem).
            if ($lastReview && is_array($lastReview)) {

                $lastCompleted = (int)$lastReview['date'];

                // We only care about every nth order review if it's been less than 90 days.
                if (Time::utcTimestamp() - $lastCompleted < 90 * 24 * 60 * 60) {

                    // Work out which was the last order they did a review for
                    $previousOrderIDs = $customer->getOrderIDs();

                    // We only care about every nth review if they have past orders
                    if (count($previousOrderIDs) > 0) {
                        asort($previousOrderIDs);

                        // This was the last order they reviewed
                        $lastReviewOrderID = $lastReview['order_id'];

                        // Keep a track of the number of orders from the last one the customer reviewed
                        $numberOfOrdersSinceReview = null;

                        foreach ($previousOrderIDs as $previousOrderID) {

                            // Set the count to zero once we found the last order reviewed.
                            if ($previousOrderID === $lastReviewOrderID) {
                                $numberOfOrdersSinceReview = 0;
                            }
                            // Keep counting
                            if (!is_null($numberOfOrdersSinceReview)) {
                                $numberOfOrdersSinceReview++;
                            }
                        }

                        // Don't ask if it's been < 3 orders
                        if (!is_null($numberOfOrdersSinceReview) && $numberOfOrdersSinceReview < 3) {
                            Log::info("maybe_schedule_review_request: failed for " . $this->get_id() . " it's not been 3 orders since last review");
                            return $this;
                        }
                    }
                }
            }
        }

        //Defaults
        $reminderTime = '10:00 am';
        $reminderDays = 3;
        $notificationDate = Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->addDays($reminderDays)->setTimeFromTimeString($reminderTime);

        if ($notificationDate->isAfter(Time::utcNow())) {
            wp_schedule_single_event($notificationDate->getTimestamp(), 'future_send_review_request', $args);
            $this->add_note("Review requested reset & scheduled for: " . $notificationDate->format('jS F Y h:i a'));
            $this->save();
        }

        return $this;
    }



    /**
     * Hub needs to know about the coupon code for coutts tracking.
     * TODO: make sure Hub handles this if we send these multiple times.
     */
    function maybe_send_coupons_to_hub()
    {
        $coupons = $this->get_coupon_items();
        foreach ($coupons as $coupon) {
            $amount = $this->get_status() === WooCommerce::orderHasBeenPaid ? $coupon->get_discount() * -1 : $coupon->get_discount();
            $email = $this->get_billing_email();
            $code = $coupon->get_code();
            //$this->add_order_note("Sending Coupon to Hub for processing ($email, $code, $amount)");
            Hub::processOrderCoupon($this->get_id(), $email, $code, $amount);
        }

        return $this;

    }



    public function maybe_send_email()
    {

        if (!$this->get_billing_email()) {
            return $this;
        }

        $emailSent = $this->get_meta('_confirmation_email_sent');
        if (!$emailSent) {
            $this->send_email();
            update_post_meta($this->get_id(), '_confirmation_email_sent', 1);

        }


        return $this;

    }



    public function maybe_send_tracking_to_exponea($step)
    {

        // make sure we do this only once.
        $trackingDataSent = $this->get_meta('_tracking_data_sent');

        if (!$trackingDataSent) {
            $trackingDataSent = [
                100 => false,
                200 => false,
                300 => false,
                400 => false,
                500 => false,
            ];
        }

        if (isset($trackingDataSent[$step]) && !$trackingDataSent[$step]) {

            // Tracking
            $data = $this->get_order_data();

            $steps = [
                100 => 'reached checkout',
                200 => 'entered personal details',
                300 => 'choose shipping date',
                400 => 'entered card details',
                500 => 'paid',
            ];

            if ($step <= 500) {


                Tracking::track('checkout', [
                    'step_number'   => $step,
                    'step_title'    => $steps[$step],
                    'purchase_id'   => $this->get_id(),
                    'items'         => $data['items'],
                    'gift_cards'    => $data['giftCards'],
                    'coupons'       => $data['coupons'],
                    'shipping_data' => $data['shippingData'],
                    'total_price'   => Number::decimal($data['total']),
                    'domain'        => get_site_url(),
                ]);
            }

            $trackingDataSent[$step] = true;

            update_post_meta($this->get_id(), '_tracking_data_sent', $trackingDataSent);

            // Paid
            if ($step === 500) {
                Tracking::track('purchase', [
                    'purchase_id'     => $this->get_id(),
                    'purchase_status' => 'purchase',
                    'items'           => $data['items'],
                    'gift_cards'      => $data['giftCards'],
                    'coupons'         => $data['coupons'],
                    'shipping_data'   => $data['shippingData'],
                    'total_price'     => Number::decimal($data['total']),
                    'domain'          => get_site_url(),
                ]);

                foreach ($data['items'] as $item) {

                    $idToUse = $item['variation_id'] ? $item['variation_id'] : $item['product_id'];
                    $product = wc_get_product($idToUse);

                    $attributes = Product::getAttributesForExponea($product);
                    $attributes['purchase_id'] = $this->get_id();
                    $attributes['purchase_status'] = 'purchase';
                    $attributes['quantity'] = $item['quantity'];
                    $attributes['domain'] = get_site_url();

                    $attributes['total_price'] = Number::decimal($item['quantity'] * $attributes['price']);

                    Tracking::track('purchase_item', $attributes);
                }
            }


        }

        return $this;

    }



    /**
     * After the order has been processed - should we copy shipping data from billing data
     *
     * @return $this
     * @throws WC_Data_Exception
     */
    public function maybe_set_shipping_data()
    {

        if (!$this->get_deliver_to_a_different_address()) {

            $this->set_shipping_first_name($this->get_billing_first_name());
            $this->set_shipping_last_name($this->get_billing_last_name());
            $this->set_shipping_address_1($this->get_billing_address_1());
            $this->set_shipping_address_2($this->get_billing_address_2());
            $this->set_shipping_city($this->get_billing_city());
            $this->set_shipping_state($this->get_billing_state());
            $this->set_shipping_postcode($this->get_billing_postcode());
            $this->set_shipping_country($this->get_billing_country());
            $this->set_shipping_company($this->get_billing_company());
            $this->set_shipping_phone($this->get_billing_phone());
            $this->set_deliver_to_a_different_address(true);
            $this->save();
        }

        return $this;

    }



    /**
     * Does this order contain a coupon code that was a delivery slot ?
     */
    function process_coupons()
    {

        $coupons = $this->get_coupon_items();

        foreach ($coupons as $couponItem) {
            $code = $couponItem->get_code();

            $couponID = wc_get_coupon_id_by_code($code);

            // Maybe reward referrals
            Referral::maybeRewardReferral($code, $couponID, $this->get_id());

            //if the code begins with BS- it's a booking slot.
            if (strpos(strtoupper($code), 'BS-') === 0) {

                if ($couponID) {
                    $slotOrderID = get_field('order_id', $couponID);
                    if ($slotOrderID) {
                        $slotOrder = new static($slotOrderID);
                        if ($slotOrder) {
                            // So we no longer want that order to count towards the totals.
                            $slotOrder->exclude_from_order_count();
                            $slotOrder->update_order_delivery_meta();
                            $slotOrder->set_status(WooCommerce::orderHasBeenFulfilled);
                            $slotOrder->save();
                        }
                    }
                    update_field('used_order_id', $this->get_id(), $couponID);
                }
            }
        }

        return $this;

    }



    /**
     * Process changes to gift cards after the order has been processed
     *
     * @return $this
     */
    public function process_gift_cards()
    {

        $giftCardItems = $this->get_gift_card_items();

        foreach ($giftCardItems as $giftCardItem) {

            // let's keep a track of the cards in use
            $currentCards[] = $giftCardItem->card_number;

            $giftCard = GiftCard::getByCardNumber($giftCardItem->card_number);

            if (!$giftCard) {
                continue;
            }

            // if this Gift Card had an order attached to it. Remove the delivery from the original order
            if ($giftCard->isSlotCard() && $giftCard->order_id) {
                $slotOrder = new static($giftCard->order_id);
                if ($slotOrder) {
                    // So we no longer want that order to count towards the totals.
                    $slotOrder->exclude_from_order_count();
                    $slotOrder->update_order_delivery_meta();
                    $slotOrder->set_status(WooCommerce::orderHasBeenFulfilled);
                    $slotOrder->save();
                }

                $giftCard->used_order_id = $this->get_id();
            }

            //If there is no difference, then we can continue ?
            if ($giftCardItem->amountProcessed === $giftCardItem->amount) {
                continue;
            }

            $adjustmentAmount = ($giftCardItem->amount - $giftCardItem->amountProcessed) * -1;

            $giftCard->adjustBalance($adjustmentAmount, "order_id: " . $this->get_id() . " " . $this->get_status());
            $giftCardItem->amountProcessed = $giftCardItem->amount;
            $giftCardItem->processed = true;
            $giftCardItem->save();

            $this->add_item($giftCardItem);
            $this->save();
        }

        return $this;
    }



    /**
     * Creates the delivery coupon if a product was in the order.
     *
     * @return $this
     */
    function process_products_and_create_delivery_slot()
    {

        $productMessages = [];

        $items = $this->get_product_items();
        foreach ($items as $productItem) {

            $product = $productItem->get_product();

            $vars = [
                'product' => [
                    'name'        => $product->get_name(),
                    'price'       => Number::currency($product->get_price()),
                    'description' => $product->get_description(),
                    'link'        => $product->get_permalink(),
                ],
            ];

            $cahType = get_field('cah_type', $productItem->get_product_id());
            if ($cahType === 'slot') {

                $shippingItem = $this->get_shipping_item();

                // Is we have a coupon code, let's continue to use it.
                $couponCode = 'BS-' . Hasher::encode($this->get_id());
                $coupon_id = wc_get_coupon_id_by_code($couponCode);
                if ($coupon_id) {
                    $vars['slot'] = [
                        'delivery_date' => Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->format(' jS F Y'),
                        'deadline_date' => Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->subDays(3)->format(' jS F Y'),
                        'coupon_code'   => $couponCode,
                    ];
                    continue;
                }

                //let's create the gift Card
                $cardNumber = GiftCard::coteCardPrefix . 'BS-' . Hasher::encode($this->get_id(), 6);

                $vars['slot'] = [
                    'delivery_date' => Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->format(' jS F Y'),
                    'deadline_date' => Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->subDays(3)->format(' jS F Y'),
                    'coupon_code'   => $cardNumber,
                ];

                $giftCard = GiftCard::getByCardNumber($cardNumber);

                // Do we need to create the coupon ?
                if (!$giftCard) {

                    $this->update_meta_data('_delivery_slot_coupon', $cardNumber);

                    $giftCard = GiftCard::create([
                        'post_title' => $cardNumber,
                        'type'       => GiftCard::slotCard,
                        'expiry'     => Time::now()->addDays(30),
                        'cc_id'      => $this->get_cote_customer(),
                        'balance'    => 10,
                        'order_id'   => $this->get_id(),
                    ]);

                    $giftCard->logActivity('slot', 10, 'Gift Card Created');
                    $giftCard->save();

                    $this->add_order_note("Gift Card $cardNumber was created for the customer");
                }

            }

            // Do we have an order Message
            $productMessage = $product->get_meta('product_message');
            $priority = $product->get_meta('product_message_priority');
            if ($productMessage) {
                if (!isset($productMessages[$priority])) {
                    $productMessages[$priority] = [];
                }
                $productMessages[$priority] = View::with($productMessage)->addVars($vars)->render();
            }

            $this->send_delivery_slot_email();
        }

        $this->update_meta_data('_product_messages', $productMessages);

        return $this;
    }



    /**
     * This starts a chain reaction that ends in a save from calculate_totals() from WC_order
     */
    public function recalculate_and_save()
    {
        $this->recalculate_coupons();
        return $this;
    }



    /**
     * Work out how much to take from each gift card
     */
    public function recalculate_gift_cards()
    {

        // What's the total without Gift Cards ?
        $total = $this->get_product_total() + $this->get_coupon_total() + $this->get_shipping_total();

        $giftCardItems = $this->get_gift_card_items();
        foreach ($giftCardItems as $giftCardItem) {

            if ($giftCardItem->removed) {
                continue;
            }

            $card = GiftCard::getByCardNumber($giftCardItem->card_number);

            // the balance on the card is the balance + whatever has already been adjusted.
            $balance = $card->balance + $giftCardItem->amountProcessed;

            // Now work out how much to take from the balance
            $amount = $total > $balance ? $balance : $total;

            //Set the new amount ... Later when the card is processed by the
            // status change the card will be adjusted.
            $giftCardItem->amount = $amount;
            $giftCardItem->save();

            $this->add_item($giftCardItem);

            // go to the next card!
            $total -= $amount;
        }
    }



    /**
     * Find transactions that were paid and refund them one by one.
     *
     * @param float $amount 0 = refund in full
     * @param string $note
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function refund(float $amount = 0, string $note = '')
    {

        remove_action('woocommerce_order_status_refunded', 'wc_order_fully_refunded');

        if ($amount > 0 && $this->get_total_payments() < $amount) {
            throw CoteAtHomeException::withMessage("Unable to refund $amount as the order total is more than the amount that has been charged");
        }

        $refundInFull = false;
        $amountRefunded = 0;

        // Refund all ?
        if (Number::decimal($amount) === Number::decimal(0)) {
            $refundInFull = true;
            $amount = Number::decimal($this->get_total_payments());
        }

        // We have to refund transactions one by one.
        $transactions = $this->get_transaction_items();
        $transactionIDsRefunded = [];
        $refundWasSuccessful = false;

        foreach ($transactions as $transaction) {

            if (strtolower($transaction->transaction_type) === 'refund') {
                continue;
            }

            if (Number::decimal($transaction->balance) === Number::decimal(0)) {
                continue;
            }

            $amountToRefund = ($amount > $transaction->balance ? $transaction->balance : $amount);

            $amountRefunded += $amountToRefund;

            try {
                $result = SagePay::refund($this->get_id(), $transaction->transaction_id, $amountToRefund);

            } catch (CoteAtHomeException $e) {
                break;

            }
            $details = $result->getResponseBody();

            $refundTransaction = new TransactionItem();
            $refundTransaction->fill([
                'success'                 => $result->wasSuccessful(),
                'status_code'             => $details->statusCode,
                'transaction_id'          => $details->transactionId,
                'amount'                  => $amountToRefund,
                'transaction_type'        => $details->transactionType,
                'retrieval_reference'     => $details->retrievalReference,
                'bank_authorisation_code' => $details->bankAuthorisationCode,
                'description'             => $details->description,
                'date'                    => Carbon::now()->setTimezone(Time::tz())->format('Y-m-d H:i:s'),
            ]);

            $refundTransaction->save();
            $this->add_item($refundTransaction);

            if ($result->wasSuccessful()) {
                $refundWasSuccessful = true;
                $transaction->balance = $transaction->balance - $amountToRefund;
                $transaction->amount_refunded += $amountToRefund;
                $transaction->save();
                $this->add_item($transaction);

                $transactionIDsRefunded[] = $refundTransaction->get_id();
                if ($amountToRefund < $transaction->balance) {
                    break;
                }
                $amount -= $amountToRefund;
                if (Number::decimal($amount) === Number::decimal(0)) {
                    break;
                }
            }
        }

        $this->add_note($note);

        if ($refundInFull) {
            //TODO: deal with refunds!
            //$this->set_status('refunded');
        }
        $this->save();
        $this->add_notice('Refund of £' . $amountRefunded . ' Processed');
        //Phase2: Email

        if (!$refundWasSuccessful) {
            throw CoteAtHomeException::withMessage('Refund of £' . $amountRefunded . ' Failed');
        }

        return $this;

    }



    /**
     * @param  $code
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function remove_coupon($code)
    {

        $code = strtoupper($code);

        $this->check_if_order_is_editable();

        $coupons = $this->get_coupon_items();

        foreach ($coupons as $coupon) {
            if ($coupon->get_code() === $code) {
                $this->remove_item($coupon->get_id());
                $this->save();
                break;
            }
        }

        $this->recalculate_and_save();
        $this->check_cart_contents();
        $this->maybe_apply_automatic_coupons();
        return $this;

    }



    /**
     * @param string $cardNumber
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function remove_gift_card(string $cardNumber)
    {
        $this->check_if_order_is_editable();

        $giftCardItems = $this->get_gift_card_items();

        foreach ($giftCardItems as $giftCardItem) {
            if ((string)$giftCardItem->card_number === $cardNumber) {

                //  We will need this later to credit back the card. so we Zero out the amount.
                if (Number::decimal($giftCardItem->amountProcessed) > 0) {
                    $giftCardItem->removed = true;
                    $giftCardItem->amount = 0;
                    $giftCardItem->save();
                    $this->add_item($giftCardItem);
                    $this->add_note("Gift Card $cardNumber Removed (Amount set to £0)");
                } else {
                    $this->add_note("Gift Card $cardNumber Removed");
                    $this->remove_item($giftCardItem->get_id());
                }
                $this->recalculate_and_save();
                break;
            }
        }

        return $this;

    }



    /**
     * @return Order
     * @throws CoteAtHomeException
     */
    public function remove_shipping_data()
    {

        $this->check_if_order_is_editable();

        $this->remove_order_items(ShippingItem::$type);

        $this->recalculate_and_save();

        return $this;
    }



    /**
     * empties this order
     */
    public function reset()
    {

        //  cart_update  - Just in case we need a specific "empty" event.
        //        $this->remove_order_items();
        //        $attributes['action'] = 'empty';
        //        $attributes['purchase_id'] = $this->get_id();
        //        $attributes['total_price'] = 0;
        //        Tracking::track('cart_update', $attributes);


        return $this;

    }



    /**
     * revert the order to it's original state
     *
     * @param string $note
     * @param bool $originalData
     *
     * @return Order
     * @throws CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public function revert($note = '', $originalData = false)
    {

        if (!$originalData) {
            $originalOrder = $this->get_original_order();

            if (!$originalOrder) {
                Log::error('No original order found on revert:' . $this->get_id());
                return $this;
            }
            $originalData = $originalOrder->orderData;

        }

        $customer = $originalData['customer'];
        if (isset($customer['first_name']) && $customer['first_name']) {
            $this->set_billing_first_name($customer['first_name']);
        }
        if (isset($customer['last_name']) && $customer['last_name']) {
            $this->set_billing_last_name($customer['last_name']);
        }
        if (isset($customer['email']) && $customer['email']) {
            $this->set_billing_email($customer['email']);
        }
        if (isset($customer['phone']) && $customer['phone']) {
            $this->set_billing_phone($customer['phone']);
        }

        $billing = $originalData['billingAddress'];
        if (isset($billing['company']) && $billing['company']) {
            $this->set_billing_company($billing['company']);
        }
        if (isset($billing['address_1']) && $billing['address_1']) {
            $this->set_billing_address_1($billing['address_1']);
        }
        if (isset($billing['address_2']) && $billing['address_2']) {
            $this->set_billing_address_2($billing['address_2']);
        }
        if (isset($billing['city']) && $billing['city']) {
            $this->set_billing_city($billing['city']);
        }
        if (isset($billing['state']) && $billing['state']) {
            $this->set_billing_state($billing['state']);
        }
        if (isset($billing['postcode']) && $billing['postcode']) {
            $this->set_billing_postcode($billing['postcode']);
        }
        if (isset($billing['country']) && $billing['country']) {
            $this->set_billing_country($billing['country']);
        }

        $shipping = $originalData['shippingAddress'];
        if (isset($shipping['first_name']) && $shipping['first_name']) {
            $this->set_shipping_first_name($shipping['first_name']);
        }
        if (isset($shipping['last_name']) && $shipping['last_name']) {
            $this->set_shipping_last_name($shipping['last_name']);
        }
        if (isset($shipping['phone']) && $shipping['phone']) {
            $this->set_shipping_phone($shipping['phone']);
        }
        if (isset($shipping['company']) && $shipping['company']) {
            $this->set_shipping_company($shipping['company']);
        }
        if (isset($shipping['address_1']) && $shipping['address_1']) {
            $this->set_shipping_address_1($shipping['address_1']);
        }
        if (isset($shipping['address_2']) && $shipping['address_2']) {
            $this->set_shipping_address_2($shipping['address_2']);
        }
        if (isset($shipping['city']) && $shipping['city']) {
            $this->set_shipping_city($shipping['city']);
        }
        if (isset($shipping['state']) && $shipping['state']) {
            $this->set_shipping_state($shipping['state']);
        }
        if (isset($shipping['postcode']) && $shipping['postcode']) {
            $this->set_shipping_postcode($shipping['postcode']);
        }
        if (isset($shipping['country']) && $shipping['country']) {
            $this->set_shipping_country($shipping['country']);
        }

        // Remove all items from this order
        $this->remove_order_items('line_item');
        $this->remove_order_items('coupon');
        $this->remove_order_items(GiftCardItem::$type);

        // Add back products
        foreach ($originalData['items'] as $item) {

            // We dont have this on the order - let's add it
            $product = wc_get_product($item['product_id']);

            $productItem = new WC_Order_Item_Product();
            $productItem->set_product($product);
            $productItem->set_quantity($item['quantity']);
            $productItem->set_subtotal($item['subtotal']);
            $productItem->set_total($item['total']);
            $productItem->update_meta_data('vat_rate', $item['vat_rate']);
            $productItem->update_meta_data('components', $item['components']);
            $productItem->update_meta_data('ignore_for_picklist', $item['ignore_for_picklist']);
            $productItem->save();
            $this->add_item($productItem);
        }

        // Add back coupons
        foreach ($originalData['coupons'] as $coupon) {

            $couponItem = new WC_Order_Item_Coupon();
            $couponItem->set_code($coupon['code']);
            $couponItem->set_discount($coupon['discount']);
            $couponItem->update_meta_data('automatic_discount', $coupon['automatic_discount']);
            $couponItem->save();
            $this->add_item($couponItem);
        }

        // Add back our Gift Cards
        foreach ($originalData['giftCards'] as $giftCard) {
            // Just add the item not need to call add_gift_card()  as we dont need any checks.
            $giftCardItem = new GiftCardItem();
            $giftCardItem->card_number = $giftCard['card_number'];
            $giftCardItem->amount = $giftCard['card_number'];
            $giftCardItem->processed = $giftCard['processed'];
            $giftCardItem->amountProcessed = $giftCard['amountProcessed'];
            $giftCardItem->save();
            $this->add_item($giftCardItem);
        }

        if (is_array($originalData['shippingData'])) {
            // Add back Shipping

            $this->set_shipping_data(
                $originalData['shippingData']['date'],
                $originalData['shippingData']['delivery_company_id'],
                $originalData['shippingData']['delivery_area_id'],
                $originalData['shippingData']['amount'],
                $originalData['shippingData']['postcode'],
                $originalData['shippingData']['code'],
                $originalData['shippingData']['name'],
                $originalData['shippingData']['delivery_note'],
                $originalData['shippingData']['gift_message'],
            );
        }

        // Change the status
        $this->set_status($originalData['status']);

        $this->recalculate_and_save();

        wc_clear_notices();
        if ($note) {
            $this->add_note('The original order was restored');
            $this->add_notice('Order has been reverted');
        }

        $this->remove_order_items(OriginalOrderItem::$type);

        return $this;

    }



    /**
     * All I want for christmas is $this
     *
     * @return $this|int
     */
    public function save()
    {
        parent::save();
        return $this;
    }



    public function send_abandoned_cart_email()
    {

        $sentAbandonedCartEvent = $this->get_meta('_sent_abandoned_cart_event');
        if (!$sentAbandonedCartEvent) {
            $this->create_exponea_customer();
            $this->send_email('Abandoned');
            update_post_meta($this->get_id(), '_sent_abandoned_cart_event', 1);
        }
        return $this;
    }



    /**
     * Send an event into Exponea to trigger the booking slot email
     *
     * @return Order
     */
    public function send_delivery_slot_email()
    {
        if (!$this->contains_delivery_slot()) {
            return $this;
        }

        $product = current($this->get_product_items());
        if (!$product) {
            Log::error('Order ' . $this->get_id() . ' could not find product when sending delivery slot');
            return $this;
        }

        $code = $this->get_delivery_slot_code();

        $this->create_exponea_customer();
        $shippingItem = $this->get_shipping_item();
        $deliveryDate = Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz());
        $useByDate = Carbon::createFromFormat('Y-m-d', $shippingItem->date)->setTimezone(Time::tz())->subDays(3);

        $payload = [
            'customer_ids' => [
                'registered' => $this->get_billing_email(),
            ],
            'event_type'   => 'cah_booking_slot',
            'timestamp'    => time(),
            'properties'   => [
                'status'        => 'issued',
                'name'          => $product->get_name(),
                'order_id'      => $this->get_id(),
                'delivery_date' => $deliveryDate->getTimestamp(),
                'use_by_date'   => $useByDate->getTimestamp(),
                'postcode'      => $shippingItem->postcode,
                'code'          => $code,
            ],
        ];
        Exponea::track($payload);

        if (is_user_logged_in()) {
            $this->add_notice('Email Sent');
        }

        return $this;

    }



    /**
     * Send the order email to the customer.
     */
    public function send_email($status = '')
    {

        if (!$this->get_billing_email()) {
            return $this;
        }

        $this->create_exponea_customer();
        $data = $this->generate_order_data();

        if (!$status) {
            $statuses = wc_get_order_statuses();
            $status = $statuses['wc-' . $data['status']];
        }

        $productMessages = $this->get_meta('_product_messages');
        if ($productMessages) {
            $productMessages = '<p>' . implode('</p><p>', $productMessages) . '</p>';
        }

        $shopSession = $this->get_shop_session();

        $payload = [
            'customer_ids' => [
                'registered' => $this->get_billing_email(),
            ],
            'event_type'   => 'order',
            'timestamp'    => time(),
            'properties'   => [
                'order_id'         => $this->get_id(),
                'status'           => $status,
                'billing_address'  => json_encode($data['billingAddress']),
                'shipping_address' => json_encode($data['shippingAddress']),
                'item_count'       => count($data['items']),
                'gift_card_count'  => count($data['giftCards']),
                'coupon_count'     => count($data['coupons']),
                'items'            => json_encode($data['items']),
                'gift_cards'       => json_encode($data['giftCards']),
                'coupons'          => json_encode($data['coupons']),
                'shipping_data'    => json_encode($data['shippingData']),
                'total'            => Number::decimal($data['total']),
                'pending_charge'   => Number::decimal($data['pendingCharge']),
                'total_paid'       => Number::decimal($this->get_total_amount_charged()),
                'total_refunded'   => Number::decimal($this->get_total_amount_refunded()),
                'payment_link'     => $this->get_checkout_payment_url(),
                'cancel_link'      => $this->get_cancel_order_url(),
                'recover_link'     => $this->get_recover_order_url(),
                'product_messages' => $productMessages,
                'utm_campaign'     => $shopSession->utm_campaign,
                'utm_source'       => $shopSession->utm_source,
                'utm_medium'       => $shopSession->utm_medium,

            ],
        ];
        Exponea::track($payload);
        $this->add_notice('Email Sent');

        return $this;
    }



    /**
     * @param  $company
     * @param  $address_1
     * @param  $address_2
     * @param  $city
     * @param  $state
     * @param  $postcode
     * @param  $country
     *
     * @return Order
     * @throws CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public function set_billing_address($company, $address_1, $address_2, $city, $state, $postcode, $country)
    {
        $this->check_if_order_is_editable();

        if ($postcode) {
            $response = Validate::postcode($postcode);
            if (!$response['valid']) {
                throw CoteAtHomeException::withMessage('Postcode number invalid')
                    ->withData($response);
            }
            $postcode = $response['formattedPostcode'];
        }

        $this->set_billing_company($company);
        $this->set_billing_address_1($address_1);
        $this->set_billing_address_2($address_2);
        $this->set_billing_city($city);
        $this->set_billing_state($state);
        $this->set_billing_postcode($postcode);
        $this->set_billing_country($country);
        $this->save();

        return $this;

    }



    public function set_cart_error($message)
    {
        update_post_meta($this->get_id(), '_cart_error', $message);
    }



    /**
     * @param $id
     */
    public function set_cote_customer($id)
    {
        update_post_meta($this->get_id(), '_cc_id', $id);
    }



    /**
     * @param $tags
     *
     * @return $this
     * @throws CoteAtHomeException
     */
    public function set_customer_tags($tags)
    {
        $customer = $this->get_customer();

        if (!$customer) {
            throw CoteAtHomeException::withMessage('Customer not set');
        }

        $customer->set_tags($tags);

        return $this;
    }



    public function set_date_created($date = null)
    {
        parent::set_date_created($date);
        return $this;
    }



    public function set_date_paid($date = null)
    {
        if (!$this->get_date_paid()) {
            parent::set_date_paid($date);
        }
        return $this;
    }



    /**
     * @param $bool
     *
     * @return Order
     */
    public function set_deliver_to_a_different_address($bool): Order
    {
        $this->update_meta_data('_deliver_to_different_address', $bool);
        return $this;
    }



    /**
     * @param  $first_name
     * @param  $last_name
     * @param  $company
     * @param  $address1
     * @param  $address2
     * @param  $city
     * @param  $state
     * @param  $postcode
     * @param  $country
     * @param $phone
     *
     * @return Order
     * @throws CoteAtHomeException
     * @throws WC_Data_Exception
     */
    public function set_shipping_address($first_name, $last_name, $company, $address1, $address2, $city, $state, $postcode, $country, $phone)
    {

        if (!$postcode) {
            throw CoteAtHomeException::withMessage('Postcode required');
        }

        $response = Validate::postcode($postcode);
        if (!$response['valid']) {
            throw CoteAtHomeException::withMessage('Postcode invalid')
                ->withData($response);
        }

        $postcode = $response['formattedPostcode'];

        //TODO: work on back end error checking
        //  if($phone) {
        //    $response = Validate::tel($phone);
        //    if (!$response['valid']) {
        //      throw CoteAtHomeException::withMessage('Phone number invalid')->withData($response);
        //    }
        // }

        $this->set_shipping_first_name($first_name);
        $this->set_shipping_last_name($last_name);
        $this->set_shipping_company($company);
        $this->set_shipping_address_1($address1);
        $this->set_shipping_address_2($address2);
        $this->set_shipping_city($city);
        $this->set_shipping_state($state);
        $this->set_shipping_postcode($postcode);
        $this->set_shipping_country($country);
        $this->set_shipping_phone($phone);
        $this->set_deliver_to_a_different_address(true);
        $this->save();

        return $this;

    }



    /**
     * @param string $date
     * @param int $delivery_company_id
     * @param int $delivery_area_id
     * @param float $amount
     * @param string $postcode
     * @param string $code
     * @param string $name
     * @param string $delivery_note
     * @param string $gift_message
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function set_shipping_data(string $date, int $delivery_company_id, int $delivery_area_id, float $amount, string $postcode, string $code, string $name, string $delivery_note, string $gift_message)
    {

        $this->check_if_order_is_editable();

        $this->remove_shipping_data();

        $shippingItem = new ShippingItem();

        $shippingItem->fill([
            'date'                => $date,
            'delivery_company_id' => $delivery_company_id,
            'delivery_area_id'    => $delivery_area_id,
            'amount'              => $amount,
            'postcode'            => $postcode,
            'code'                => $code,
            'name'                => $name,
            'delivery_note'       => $delivery_note,
            'gift_message'        => $gift_message,
        ]);

        $shippingItem->save();
        $this->add_item($shippingItem);
        $this->recalculate_and_save();

        return $this;
    }



    public function set_shipping_phone($phone)
    {
        return $this->update_meta_data('_shipping_phone', $phone);
    }



    /**
     * Set the new status
     *
     * @param  $new_status
     * @param  $note
     * @param  $manual_update
     *
     * @return $this|array
     */
    public function set_status($new_status, $note = '', $manual_update = false)
    {

        parent::set_status($new_status, $note, $manual_update);

        return $this;
    }



    /**
     * Grab a copy of the order in case sometime goes wrong
     *
     * @param $note
     *
     * @return Order
     */
    public function snapshot()
    {

        Snapshot::order($this->get_id());
        return $this;

    }



    /**
     * Start processing this order.
     *
     * @return $this
     */
    public function start_processing()
    {
        $this->set_status('produced');
        $this->add_order_note('Processing started');
        $this->save();
        return $this;
    }



    public function update_components()
    {

        $items = $this->get_product_items();
        foreach ($items as $item) {
            $product_id_to_use = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();

            $meta = Product::getMeta($product_id_to_use);
            foreach ($meta as $key => $value) {
                $item->update_meta_data($key, $value);
            }
            $item->save();
            $this->add_item($item);

        }
        $this->save();

        return $this;

    }



    /**
     * Update the meta data so this order can be counted correctly when selecting the data
     *
     * @return $this
     */
    public function update_order_delivery_meta()
    {

        global $wpdb;

        $include = in_array($this->get_status(), [WooCommerce::orderHasBeenPaid, WooCommerce::orderIsBeingEdited, WooCommerce::orderIsPendingPayment, WooCommerce::orderHasBeenFulfilled, WooCommerce::orderHasBeenProduced,]);

        // Orders that don't have a delivery will set this.
        if (get_post_meta($this->get_id(), '_exclude_from_order_count', true)) {
            $include = false;
        }

        // delete anything that's there.
        $wpdb->query("delete from wp_postmeta where meta_key like '_order_delivery_%' and post_id = " . $this->get_id());

        $shippingData = $this->get_shipping_item();
        if ($shippingData) {

            Date::firstOrCreate($shippingData->date);

            $company = new DeliveryCompany($shippingData->delivery_company_id);

            $order_id = $this->get_id();

            $key = '_order_delivery_' . $shippingData->date . '_' . $shippingData->delivery_company_id;
            update_post_meta($order_id, $key, $include ? 1 : 0);

            $key = '_order_delivery_' . $shippingData->date;
            update_post_meta($order_id, $key, $include ? 1 : 0);

            // Standard Data - used in the order editor.
            update_post_meta($order_id, '_delivery_date', $include ? $shippingData->date : '');
            update_post_meta($order_id, '_delivery_company_id', $include ? $shippingData->delivery_company_id : '');
            update_post_meta($order_id, '_delivery_company_name', $include ? $company->post_title : '');

        }

        return $this;
    }



    /**
     * Edit the price of a product
     *
     * @param int $product_id
     * @param float $price
     *
     * @return Order
     * @throws CoteAtHomeException
     */
    public function update_product_price(int $product_id, float $price)
    {

        $this->check_if_order_is_editable();

        $productToChange = wc_get_product($product_id);

        $productItems = $this->get_product_items();
        foreach ($productItems as $productItem) {
            $product = $productItem->get_product();

            if ($productToChange->get_id() === $product->get_id()) {
                $productItem->set_subtotal($price);
                $productItem->set_total($price);
                $productItem->save();
                $this->add_item($productItem);

                break;
            }
        }
        $this->recalculate_and_save();

        return $this;
    }



    /**
     * Save our data to the shop_sessions table to be processed later.
     */
    public function update_shop_session()
    {

        $shopSession = $this->get_shop_session();

        $shopSession->last_updated_date = $this->get_date_modified()->format('Y-m-d H:i:s');

        $cartError = $this->get_cart_error();

        $shopSession->cart_error = $cartError ? $cartError : null;

        $orderData = $this->get_order_data();

        if (is_array($orderData['timeline'])) {
            $first = PHP_INT_MAX;
            foreach ($orderData['timeline'] as $timeline) {
                if ($orderData['timeline'][0]['date'] < $first) {
                    $first = $orderData['timeline'][0]['date'];
                }
            }
            if ($first !== PHP_INT_MAX) {
                $shopSession->cart_created_date = Carbon::createFromTimestamp($first)->format('Y-m-d H:i:s');
            }
        }

        if (!$shopSession->cart_created_date) {
            $shopSession->cart_created_date = $this->get_date_created()->format('Y-m-d H:i:s');
        }

        if (count($orderData['coupons']) > 0) {
            $shopSession->used_coupon = true;

            $couponsUsed = [];
            foreach ($orderData['coupons'] as $couponUsed) {
                $couponsUsed[] = strtoupper($couponUsed['code']);
                if (strpos(strtoupper($couponUsed['code']), 'BS-') !== false) {
                    $shopSession->used_booking_slot = true;
                }
            }

            $shopSession->coupons = implode(',', $couponsUsed);

        }
        if (count($orderData['giftCards']) > 0) {
            $shopSession->used_gift_card = true;

            $cardsUsed = [];
            foreach ($orderData['giftCards'] as $cardUsed) {
                $cardsUsed[] = $cardUsed['card_number'];
            }

            $shopSession->gift_cards = implode(',', $cardsUsed);

        }
        if (count($orderData['items']) > 0) {
            $shopSession->added_to_cart = true;
        }
        if ($orderData['customer']['email']) {
            $shopSession->added_details = true;
            $shopSession->email = trim(strtolower($orderData['customer']['email']));
            $cc_id = $this->get_cote_customer();
            if ($cc_id) {
                $shopSession->cc_id = $cc_id;
            }

        }

        // the created date is changed when the order is paid.
        if (in_array($this->get_status(), [
            WooCommerce::orderHasBeenProduced,
            WooCommerce::orderHasBeenPaid,
            WooCommerce::orderHasBeenFulfilled,
        ])) {
            $shopSession->added_details = true;
            $shopSession->added_to_cart = true;
            $shopSession->paid = true;
            $paidDate = $this->get_date_paid();
            if (!$paidDate) {
                $paidDate = $this->get_date_created();
            }
            $shopSession->paid_date = $paidDate->format('Y-m-d H:i:s');
        }

        if (isset($orderData['shippingData']['code'])) {
            $shopSession->delivery_company_id = $orderData['shippingData']['delivery_company_id'];
            $shopSession->delivery_date = $orderData['shippingData']['date'];
            $shopSession->delivery_code = $orderData['shippingData']['code'];
            $shopSession->delivery_postcode = $orderData['shippingData']['postcode'];
            $shopSession->delivery_company_name = $orderData['shippingData']['delivery_company'];
        }

        $shopSession->total = Number::decimal($orderData['total']);
        $shopSession->total_items = Number::decimal($orderData['subtotals']['products']);
        $shopSession->total_coupons = Number::decimal($orderData['subtotals']['coupons']);
        $shopSession->total_delivery = Number::decimal($orderData['subtotals']['shipping']);
        $shopSession->total_gift_cards = Number::decimal($orderData['subtotals']['giftCards']);
        $shopSession->total_payments = Number::decimal($orderData['subtotals']['payments']);

        $shopSession->save();

        return $this;

    }



    /**
     * @throws CoteAtHomeException
     */
    public function validate_order_before_checkout()
    {

        $shippingData = $this->get_shipping_item();

        $availableDates = $this->get_shipping_dates($shippingData->postcode);


        foreach ($availableDates['dates'] as $date) {
            if ($date['available'] && $date['date'] === $shippingData->date) {
                return true;
            }
        }

        $date = Carbon::createFromFormat('Y-m-d', $shippingData->date)->format('jS F');

        throw CoteAtHomeException::withMessage("Sorry delivery on $date is no longer available. Please click 'NEXT' and select another date");
    }



    private function generate_product_item_data()
    {
        $productItems = $this->get_product_items();
        $return = [];
        foreach ($productItems as $productItem) {
            if ($productItem->get_quantity() > 0) {
                $price = $productItem->get_subtotal() / $productItem->get_quantity();
            } else {
                $price = $productItem->get_subtotal();
            }

            $product = $productItem->get_product();
            $data = $productItem->get_data();
            $data['subtotalPrice'] = $productItem->get_quantity() * $price;
            $data['price'] = $price;
            $data['category_ids'] = $product->get_category_ids();
            $image = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail');
            if ($image) {
                $data['image'] = $image[0];
            }
            $data['cah_type'] = get_field('cah_type', $product->get_id());
            $data['vat_rate'] = $productItem->get_meta('vat_rate');
            $data['components'] = $productItem->get_meta('components');
            $data['ignore_for_picklist'] = $productItem->get_meta('ignore_for_picklist');
            unset($data['meta_data']);
            $return[] = $data;
        }
        return $return;

    }

}
