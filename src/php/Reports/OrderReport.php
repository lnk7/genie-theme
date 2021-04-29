<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Theme\Exceptions\CoteAtHomeException;
use Theme\Log;
use Theme\Objects\Coupon;
use Theme\Objects\GiftCard;
use Theme\Objects\Order;
use Theme\Objects\Product;
use Theme\Objects\ProductComponent;
use Theme\Objects\ShopSession;
use Theme\Theme;
use Theme\Utils\Hasher;
use Theme\Utils\Number;
use Theme\Utils\Time;
use Theme\WooCommerce;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Filesystem;
use Lnk7\Genie\Options;
use WC_Product_Variation;

class OrderReport
{


    /**
     * This is the column we'll use for the net amount
     */
    const netColumn = 'Line: Total Net';


    /**
     * This is the column we'll use for the VAT amount
     */
    const vatColumn = 'Total Tax';


    static $orderWriter;


    static $pickListWriter;


    static $columns = [
        'ID',
        'Name',
        'Command',
        'Send Receipt',
        'Inventory Behaviour',
        'Number',
        'Note',
        'Tags',
        'Tags Command',
        'Created At',
        'Updated At',
        'Cancelled At',
        'Cancel: Reason',
        'Cancel: Send Receipt',
        'Cancel: Refund',
        'Processed At',
        'Closed At',
        'Currency',
        'Source',
        'User ID',
        'Checkout ID',
        'Cart Token',
        'Token',
        'Order Status URL',
        'Weight Total',
        'Price: Total Line Items',
        'Price: Subtotal',
        'Tax 1: Title',
        'Tax 1: Rate',
        'Tax 1: Price',
        'Tax 2: Title',
        'Tax 2: Rate',
        'Tax 2: Price',
        'Tax 3: Title',
        'Tax 3: Rate',
        'Tax 3: Price',
        'Tax: Included',
        'Tax: Total',
        'Price: Total',
        'Payment: Status',
        'Payment: Processing Method',
        'Order Fulfillment Status',
        'Additional Details',
        'Customer: ID',
        'Customer: Email',
        'Customer: Phone',
        'Customer: First Name',
        'Customer: Last Name',
        'Customer: Note',
        'Customer: Orders Count',
        'Customer: State',
        'Customer: Total Spent',
        'Customer: Tags',
        'Customer: Accepts Marketing',
        'Billing: First Name',
        'Billing: Last Name',
        'Billing: Company',
        'Billing: Phone',
        'Billing: Address 1',
        'Billing: Address 2',
        'Billing: Zip',
        'Billing: City',
        'Billing: Province',
        'Billing: Province Code',
        'Billing: Country',
        'Billing: Country Code',
        'Shipping: First Name',
        'Shipping: Last Name',
        'Shipping: Company',
        'Shipping: Phone',
        'Shipping: Address 1',
        'Shipping: Address 2',
        'Shipping: Zip',
        'Shipping: City',
        'Shipping: Province',
        'Shipping: Province Code',
        'Shipping: Country',
        'Shipping: Country Code',
        'Browser: IP',
        'Browser: Width',
        'Browser: Height',
        'Browser: User Agent',
        'Browser: Landing Page',
        'Browser: Referrer',
        'Browser: Referrer Domain',
        'Browser: Search Keywords',
        'Browser: Ad URL',
        'Browser: UTM Source',
        'Browser: UTM Medium',
        'Browser: UTM Campaign',
        'Browser: UTM Term',
        'Browser: UTM Content',
        'Row #',
        'Top Row',
        'Line: Type',
        'Line: ID',
        'Line: Product ID',
        'Line: Product Handle',
        'Line: Title',
        'Line: Name',
        'Line: Variant ID',
        'Line: Variant Title',
        'Line: SKU',
        'Line: Quantity',
        'Line: Price',
        'Line: Discount',
        'Line: Total',
        'Line: Grams',
        'Line: Requires Shipping',
        'Line: Vendor',
        'Line: Properties',
        'Line: Gift Card',
        'Line: Taxable',
        'Line: Tax 1 Title',
        'Line: Tax 1 Rate',
        'Line: Tax 1 Price',
        'Line: Tax 2 Title',
        'Line: Tax 2 Rate',
        'Line: Tax 2 Price',
        'Line: Tax 3 Title',
        'Line: Tax 3 Rate',
        'Line: Tax 3 Price',
        'Line: Fulfillable Quantity',
        'Line: Fulfillment Service',
        'Line: Fulfillment Status',
        'Shipping Origin: Name',
        'Shipping Origin: Country Code',
        'Shipping Origin: Province Code',
        'Shipping Origin: City',
        'Shipping Origin: Address 1',
        'Shipping Origin: Address 2',
        'Shipping Origin: Zip',
        'Line: Product Type',
        'Line: Product Tags',
        'Line: Variant SKU',
        'Line: Variant Barcode',
        'Line: Variant Weight',
        'Line: Variant Weight Unit',
        'Line: Variant Inventory Qty',
        'Line: Variant Cost',
        'Refund: ID',
        'Refund: Created At',
        'Refund: Note',
        'Refund: Restock',
        'Refund: Restock Type',
        'Refund: Restock Location',
        'Refund: Send Receipt',
        'Transaction: ID',
        'Transaction: Kind',
        'Transaction: Processed At',
        'Transaction: Amount',
        'Transaction: Currency',
        'Transaction: Status',
        'Transaction: Message',
        'Transaction: Gateway',
        'Transaction: Test',
        'Transaction: Authorization',
        'Transaction: Error Code',
        'Transaction: CC AVS Result',
        'Transaction: CC Bin',
        'Transaction: CC CVV Result',
        'Transaction: CC Number',
        'Transaction: CC Company',
        'Risk: Source',
        'Risk: Score',
        'Risk: Recommendation',
        'Risk: Cause Cancel',
        'Risk: Message',
        'Fulfillment: ID',
        'Fulfillment: Status',
        'Fulfillment: Created At',
        'Fulfillment: Updated At',
        'Fulfillment: Tracking Company',
        'Fulfillment: Location',
        'Fulfillment: Shipment Status',
        'Fulfillment: Tracking Number',
        'Fulfillment: Tracking URL',
        'Fulfillment: Send Receipt',
        'Delivery Date',
        'Delivery Location ID',
        'Delivery Company',
        'Delivery Time',
        'Delivery Slot Id',
        'Gift Message',
        'Line: Total Net',
        'Total Tax',

    ];


    static $orderCount = 0;


    static $pickListCount = 0;



    /**
     * create the report based on the sql.
     *
     * @param $sql
     * @param $ftp
     *
     * @throws \Theme\Exceptions\CoteAtHomeException
     */
    public static function do($sql, $ftp)
    {

        global $wpdb;

        set_time_limit(0);
        ini_set('memory_limit', '8000M');

        // Should we upload the files ?
        $uploadToDataWarehouse = $ftp && Theme::inProduction() && defined('FTP_HOST') && defined('COTE_FTP_REPORTS') && COTE_FTP_REPORTS;

        $orderReportFolder = Theme::getCahDataFolder();
        $reportDate = Carbon::now()->format('Y-m-d_H-i-s');

        $orderFilename = "orders_{$reportDate}.csv";
        $pickListFilename = "picklist_{$reportDate}.csv";
        $orderPathAndFileName = $orderReportFolder . $orderFilename;
        $pickListPathAndFileName = $orderReportFolder . $pickListFilename;

        // Setup our writers.
        static::$orderWriter = Writer::createFromPath($orderPathAndFileName, "w");
        static::$pickListWriter = Writer::createFromPath($pickListPathAndFileName, "w");

        // not too sure this is actually needed.
        CharsetConverter::addTo(static::$orderWriter, 'utf-8', 'iso-8859-15');
        CharsetConverter::addTo(static::$pickListWriter, 'utf-8', 'iso-8859-15');

        static::$orderWriter->setDelimiter('|');
        static::$pickListWriter->setDelimiter('|');

        Log::info("Order report sql : $sql");

        $posts = $wpdb->get_results($sql);

        if (empty($posts)) {
            Log::info("No orders for report.");
            return;
        }

        Log::info("processing " . count($posts) . " orders");

        static::addOrderHeader();

        // load our default product components.
        $productComponents = [];
        $pcs = ProductComponent::get();
        foreach ($pcs as $pc) {
            $id = $pc->getProductID();
            $productComponents[$id] = $pc;
        }

        // Go through each order.
        foreach ($posts as $post) {

            $order_id = $post->ID;
            $orderData = get_post_meta($order_id, '_order_data', true);


            if (!$orderData) {
                $orderData = Order::find($order_id)->generate_order_data();
            }

            // Clever shit this - convert everything to objects
            $orderData = json_decode(json_encode($orderData));

            // we dont care about booking slots
            if ($orderData->containsDeliverySlot) {
                self::logOrder($order_id, "Rejected - booking slot");
                continue;
            }

            // Pull the paid date from the comments
            $paidDate = $wpdb->get_var("select comment_date_gmt from $wpdb->comments where comment_post_ID = $order_id and comment_content like '%to Paid.' order by comment_date_gmt limit 1");

            if (!$paidDate) {
                Log::info("OrderReport: order $order_id has not been paid. Ignoring");
                self::logOrder($order_id, "Rejected - Order has not been paid for");
                continue;
            }

            // Pull the cancelled date (if there is one)
            $cancelledDate = $wpdb->get_var("select comment_date_gmt from $wpdb->comments where comment_post_ID = $order_id and comment_content like '%to Cancelled.' ");

            // We dont care about event orders so no need to put them in the picklist.
            $includeInPickList = true;
            // Does this order contain an event? Exclude from the pick list file.
            if ($orderData->containsEvent) {
                $includeInPickList = false;
                self::logOrder($order_id, "Rejected - contains event");
            }

            // No shipping data?  we dont care.
            if (!is_object($orderData->shippingData) || !isset($orderData->shippingData->date)) {
                Log::error("OrderReport: order $order_id has no shipping data. Status: " . $orderData->status);
                self::logOrder($order_id, "Rejected - No shipping data");
                continue;
            }

            $row = static::createBlankRow();


            $shippingDate = Carbon::createFromFormat('Y-m-d', $orderData->shippingData->date);

            $tagDate = $shippingDate->format('Y/m/d');

            // Populate what we know...
            $row['ID'] = $order_id;
            $row['Name'] = '#' . $order_id;
            $row['Command'] = 'New';
            $row['Send Receipt'] = 'FALSE';
            $row['Inventory Behaviour'] = 'bypass';
            $row['Number'] = $order_id;
            $row['Tags'] = "{$tagDate} {$orderData->shippingData->delivery_company}";
            $row['Tags Command'] = 'REPLACE';
            $row['Created At'] = Carbon::createFromFormat('Y-m-d', $orderData->createdDate)->format('c');
            $row['Updated At'] = Carbon::createFromFormat('Y-m-d', $orderData->modifiedDate)->format('c');

            if ($orderData->status === 'cancelled') {
                $row['Cancelled At'] = Carbon::createFromFormat('Y-m-d H:i:s', $cancelledDate)->format('c');
            }

            $row['Cancel: Send Receipt'] = 'FALSE';
            $row['Cancel: Refund'] = 'FALSE';

            $row['Processed At'] = Carbon::createFromFormat('Y-m-d H:i:s', $paidDate)->format('c');

            // Fill data from Shop Session.
            $shopSession = ShopSession::find($order_id);
            if ($shopSession) {

                $row['Browser: IP'] = $shopSession->ip_address;
                $row['Browser: User Agent'] = $shopSession->user_agent;
                $row['Browser: Landing Page'] = $shopSession->landing_page;
                $row['Browser: Referrer'] = $shopSession->referrer;
                $row['Browser: Search Keywords'] = $shopSession->search_term;
                $row['Browser: UTM Source'] = $shopSession->utm_source;
                $row['Browser: UTM Medium'] = $shopSession->utm_medium;
                $row['Browser: UTM Campaign'] = $shopSession->utm_campaign;
                $row['Browser: UTM Term'] = $shopSession->utm_term;
                $row['Browser: UTM Content'] = $shopSession->utm_content;
            }

            $row['Currency'] = 'GBP';
            $row['Source'] = 'web';
            $row['Checkout ID'] = $order_id;
            $row['Cart Token'] = Hasher::encode($order_id);
            $row['Token'] = $orderData->orderKey;

            $weight = 0;

            // Go through the current Items and determine th weight.
            foreach ($orderData->items as $item) {
                $product = wc_get_product($item->product_id);
                $currentItems[] = $item->product_id;

                if ($product) {
                    $weight += Number::decimal($product->get_weight()) * Number::integer($item->quantity);
                }
            }

            if ($orderData->paidDate) {
                $row['Payment: Status'] = 'paid';
            }
            $row['Payment: Processing Method'] = 'direct';

            if ($orderData->status === 'completed') {
                $row['Order Fulfillment Status'] = 'fulfilled';
            }

            $row['Customer: ID'] = $orderData->customer->customer_id;
            $row['Customer: Email'] = $orderData->customer->email;
            $row['Customer: Phone'] = $orderData->customer->phone;
            $row['Customer: First Name'] = $orderData->customer->first_name;
            $row['Customer: Last Name'] = $orderData->customer->last_name;

            $row['Delivery Date'] = $orderData->shippingData->date;
            $row['Delivery Location ID'] = $orderData->shippingData->delivery_company_id;
            $row['Delivery Company'] = $orderData->shippingData->delivery_company;
            if (in_array($orderData->shippingData->code, ['PREMIUM', 'DLY011'])) {
                $row['Delivery Time'] = 'Before 12:00 noon';
            } else {
                $row['Delivery Time'] = '7:00 AM - 6:00 PM';
            }

            $row['Gift Message'] = $orderData->shippingData->gift_message;

            $total = 0;
            foreach ($orderData->customer->pastOrders as $pastOrder) {
                $total += $pastOrder->amount;
            }

            $row['Gift Message'] = $orderData->shippingData->gift_message;

            // Set a few flags
            $topRowAddedForOrders = false;
            $topRowAddedForPickList = false;
            $net = 0;
            $vat = 0;
            $effectiveVatRate = 0;
            $allOrderLines = [];
            $allPickListLines = [];

            // Work out our total. This is because some line items maybe have been zeroed out.
            $productTotal = 0;
            foreach ($orderData->items as $index => $item) {
                $productTotal += $item->quantity * $item->price;
            }

            // Setup teh top row.
            $topRow = [
                'Top Row'                     => 'TRUE',
                'Note'                        => $orderData->shippingData->delivery_note,
                'Customer: Orders Count'      => count($orderData->customer->pastOrders),
                'Customer: Total Spent'       => $total,
                'Weight Total'                => $weight,
                'Price: Total Line Items'     => $productTotal,
                'Price: Subtotal'             => $productTotal + $orderData->subtotals->coupons,
                'Tax: Included'               => 'TRUE',
                'Tax: Total'                  => 0,
                'Price: Total'                => $orderData->total,
                'Customer: Accepts Marketing' => $orderData->customer->accepts_marketing ? 'TRUE' : 'FALSE',
                'Billing: Phone'              => $orderData->customer->phone,
                'Billing: First Name'         => $orderData->customer->first_name,
                'Billing: Last Name'          => $orderData->customer->last_name,
                'Billing: Company'            => $orderData->billingAddress->company,
                'Billing: Address 1'          => $orderData->billingAddress->address_1,
                'Billing: Address 2'          => $orderData->billingAddress->address_2,
                'Billing: City'               => $orderData->billingAddress->city,
                'Billing: Province'           => $orderData->billingAddress->state,
                'Billing: Zip'                => $orderData->billingAddress->postcode,
                'Billing: Country'            => $orderData->billingAddress->country,
                'Billing: Country Code'       => 'GB',
                'Shipping: First Name'        => $orderData->shippingAddress->first_name,
                'Shipping: Last Name'         => $orderData->shippingAddress->last_name,
                'Shipping: Phone'             => $orderData->shippingAddress->phone,
                'Shipping: Company'           => $orderData->shippingAddress->company,
                'Shipping: Address 1'         => $orderData->shippingAddress->address_1,
                'Shipping: Address 2'         => $orderData->shippingAddress->address_2,
                'Shipping: City'              => $orderData->shippingAddress->city,
                'Shipping: Province'          => $orderData->shippingAddress->state,
                'Shipping: Zip'               => $orderData->shippingAddress->postcode,
                'Shipping: Country'           => $orderData->shippingAddress->country,
                'Shipping: Country Code'      => 'GB',
            ];

            foreach ($orderData->items as $index => $item) {

                $ignoreComponentsForPickList = false;
                $components = [];

                $idToUse = $item->variation_id ? $item->variation_id : $item->product_id;

                // do we have default components for this product?
                $foundComponents = array_key_exists($idToUse, $productComponents);

                // We only started added components and VAT for each line item after we went live.
                if (!empty($item->components)) {
                    $components = $item->components;
                    $ignoreComponentsForPickList = $item->ignore_for_picklist;
                    $vatRate = $item->vat_rate / 100;
                } else {
                    $vatRate = Product::getVatRate($idToUse) / 100;
                    if ($foundComponents) {
                        $components = $productComponents[$idToUse]->components;
                    }
                }

                // the current overrides what's in the order.
                if ($foundComponents) {
                    $ignoreComponentsForPickList = $productComponents[$idToUse]->ignore_for_picklist;
                }

                $ordersLine = $row;
                $pickListLine = $row;

                //calculate subtotal
                $subtotal = $item->quantity * $item->price;

                // Add stuff to the orderLine
                $lineNet = $subtotal / (1 + $vatRate);
                $lineVat = $subtotal - $lineNet;
                $ordersLine[static::netColumn] = Number::decimal($lineNet);
                $ordersLine[static::vatColumn] = Number::decimal($lineVat);
                $net += $lineNet;
                $vat += $lineVat;

                if (!$topRowAddedForOrders) {
                    $ordersLine = array_merge($ordersLine, $topRow);
                    $topRowAddedForOrders = true;
                }

                try {

                    $allOrderLines[] = static::addItem($ordersLine, $idToUse, $item->quantity, $item->price, $subtotal, true, false);

                    //Pick List
                    if ($includeInPickList) {

                        self::logOrder($order_id, "Success");

                        if (!empty($components) && !$ignoreComponentsForPickList) {
                            foreach ($components as $component) {

                                $component = (object)$component;

                                if (!$topRowAddedForPickList) {
                                    $pickListLine = array_merge($pickListLine, $topRow);
                                    $topRowAddedForPickList = true;
                                }
                                $allPickListLines[] = static::addItem($pickListLine, $component->product_id, ((int)$component->quantity * (int)$item->quantity), 0, 0, false, true);

                            }
                        } else {
                            if (!$topRowAddedForPickList) {
                                $pickListLine = array_merge($pickListLine, $topRow);
                                $topRowAddedForPickList = true;
                            }
                            $allPickListLines[] = static::addItem($pickListLine, $idToUse, $item->quantity, $item->price, $subtotal, false, true);

                        }
                    }
                } catch (CoteAtHomeException $e) {
                    self::logOrder($order_id, "Rejected - " . $e->getMessage());
                    continue;
                }


            }

            if ($net > 0) {
                $effectiveVatRate = $vat / $net;
            }

            // Coupons
            foreach ($orderData->coupons as $coupon) {
                $coupon_id = wc_get_coupon_id_by_code($coupon->code);
                $wooCoupon = new Coupon($coupon_id);
                $line = $row;

                //These are a tender type
                if (strpos($coupon->code, 'BS-') === 0) {

                    $line['Line: Type'] = 'Delivery Slot';
                    $line['Line: Title'] = $coupon->code;
                    $line['Line: Name'] = $coupon->code;
                    $line['Transaction: Amount'] = $coupon->discount;
                    $line['Transaction: Kind'] = 'Gift Card';
                    $line['Transaction: Currency'] = 'GBP';
                    $orderLine = $line;
                } else if (strpos($coupon->code, 'CC-') === 0) {

                    $line['Line: Type'] = 'Coutts Voucher';
                    $line['Line: Title'] = $coupon->code;
                    $line['Line: Name'] = $coupon->code;
                    $line['Transaction: Amount'] = $coupon->discount;
                    $line['Transaction: Kind'] = 'Coutts Voucher';
                    $line['Transaction: Currency'] = 'GBP';
                    $orderLine = $line;

                } else {
                    // Hard Coded
                    $line['Line: Product ID'] = 2000;
                    $line['Line: Type'] = 'Discount';
                    $line['Line: Title'] = $wooCoupon->get_discount_type() === 'percent' ? 'percentage' : 'fixed_amount';
                    $line['Line: Name'] = $coupon->code;
                    $line['Line: Discount'] = $coupon->discount * -1;
                    $line['Line: Total'] = $coupon->discount * -1;
                    $line['Fulfillment: Send Receipt'] = 'FALSE';
                    $orderLine = $line;

                    if (strpos($coupon->code, 'EV-') === 0) {
                        $line['Line: Type'] = 'Event';
                        $orderLine['Line: Type'] = 'Event';
                    } else {
                        $orderLine[static::netColumn] = Number::decimal(($coupon->discount * -1) / (1 + $effectiveVatRate));
                        $orderLine[static::vatColumn] = Number::decimal(($coupon->discount * -1) - $orderLine[static::netColumn]);
                    }
                }

                static::addOrderRow($orderLine);
                $allOrderLines[] = $orderLine;
                if ($includeInPickList) {
                    static::addPickListRow($line);
                    $allPickListLines[] = $line;
                }
            }

            // Shipping Lines
            if ($orderData->shippingData) {

                $productCode = ProductReport::$delivery[$orderData->shippingData->code];

                $line = $row;
                $line['Line: ID'] = $orderData->shippingData->id;
                $line['Line: Type'] = 'Shipping Line';
                $line['Line: Product ID'] = $productCode['id'];
                $line['Line: Title'] = $orderData->shippingData->name;
                $line['Line: Name'] = $orderData->shippingData->name;
                $line['Line: Price'] = $orderData->shippingData->amount;
                $line['Line: Total'] = $orderData->shippingData->amount;

                $orderLine = $line;
                $orderLine[static::netColumn] = Number::decimal(($orderData->shippingData->amount) / (1 + $effectiveVatRate));
                $orderLine[static::vatColumn] = Number::decimal(($orderData->shippingData->amount) - $orderLine[static::netColumn]);

                static::addOrderRow($orderLine);
                $allOrderLines[] = $orderLine;
                if ($includeInPickList) {
                    static::addPickListRow($line);
                    $allPickListLines[] = $line;
                }
            }

            // Discounts
            foreach ($orderData->giftCards as $card) {
                $line = $row;

                $giftCard = GiftCard::getByCardNumber($card->card_number);

                $line['Line: Type'] = ($giftCard && $giftCard->isSlotCard()) ? 'Delivery Slot' : 'Gift Card';
                $line['Line: Title'] = $card->card_number;
                $line['Line: Name'] = $card->card_number;
                $line['Transaction: Amount'] = $card->amount;
                $line['Transaction: Kind'] = 'Gift Card';
                $line['Transaction: Currency'] = 'GBP';

                //$line['Line: Total'] = $card->amount;
                static::addOrderRow($line);
                $allOrderLines[] = $line;
                if ($includeInPickList) {
                    static::addPickListRow($line);
                    $allPickListLines[] = $line;
                }
            }

            // we need to keep a track of when the order was produced so we can zero out refunds.
            $calculated = Number::decimal(
                $orderData->subtotals->products +
                $orderData->subtotals->coupons +
                $orderData->subtotals->shipping +
                $orderData->subtotals->giftCards
            );

            if ($orderData->status === WooCommerce::orderHasBeenCancelled) {

                $calculated = 0;

                if (!$cancelledDate) {
                    Log::info("OrderReport: could not determine cancelled date for order $order_id - using paid date");
                    $cancelledDate = $paidDate;
                }

                // if the cancelled date is before shipping date, use the shipping date
                $processedAtDate = Carbon::createFromFormat('Y-m-d H:i:s', $cancelledDate);
                if ($processedAtDate->isBefore($shippingDate)) {
                    $processedAt = $shippingDate->format('c');
                } else {
                    $processedAt = $processedAtDate->format('c');
                }


                foreach ($allOrderLines as $type => $orderLine) {
                    static::addOrderRow(static::reverseRow($orderLine, $processedAt));
                }
                if ($includeInPickList) {
                    foreach ($allPickListLines as $type => $Line) {
                        static::addPickListRow(static::reverseRow($Line, $processedAt));
                    }
                }
            } else {

                // odd ball case where the order was marked as paid by the team

                if (
                    //  Our new total is not equal to the subtotal
                    Number::decimal($productTotal) !== Number::decimal($orderData->subtotals->products) ||
                    // there were no payment methods
                    //(Number::decimal($orderData->subtotals->payments + ($orderData->subtotals->giftCards*-1)) !==  Number::decimal($productTotal + $orderData->subtotals->shipping + $orderData->subtotals->coupons)) ||
                    ($productTotal > 0 && count($orderData->giftCards) === 0 && count($orderData->transactions) === 0 && count($orderData->coupons) === 0)
                ) {

                    $difference = $calculated - $orderData->subtotals->payments;
                    $line = $row;
                    // Hard Coded
                    $line['Line: Product ID'] = 2000;
                    $line['Line: Type'] = 'Discount';
                    $line['Line: Title'] = 'fixed_amount';
                    $line['Line: Name'] = 'ADJUSTMENT';
                    $line['Line: Discount'] = $difference * -1;
                    $line['Line: Total'] = $difference * -1;
                    $line['Fulfillment: Send Receipt'] = 'FALSE';
                    $orderLine = $line;
                    $orderLine[static::netColumn] = Number::decimal(($difference * -1) / (1 + $effectiveVatRate));
                    $orderLine[static::vatColumn] = Number::decimal(($difference * -1) - $orderLine[static::netColumn]);
                    static::addOrderRow($orderLine);
                    $allOrderLines[] = $orderLine;
                    if ($includeInPickList) {
                        static::addPickListRow($line);
                        $allPickListLines[] = $line;
                    }
                }

            }

            $totalPayments = 0;
            $afterProduced = false;

            foreach ($orderData->transactions as $transaction) {

                $type = strtolower($transaction->transaction_type) === 'refund' ? 'refund' : 'sale';

                $amount = $type === 'refund' ? $transaction->amount * -1 : $transaction->amount;

                $totalPayments += $amount;

                // Transaction
                $line = $row;
                $line['Line: Type'] = 'Transaction';
                $line['Transaction: ID'] = $transaction->transaction_id;
                $line['Transaction: Kind'] = $type;
                $line['Transaction: Processed At'] = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->date)->format('c');
                $line['Processed At'] = $line['Transaction: Processed At'];
                $line['Transaction: Amount'] = $amount;
                $line['Transaction: Status'] = $transaction->success ? 'success' : 'failed';
                $line['Transaction: Message'] = $transaction->description;
                $line['Transaction: Currency'] = 'GBP';
                $line['Transaction: Gateway'] = 'Sage Pay';
                $line['Transaction: Test'] = 'FALSE';
                $line['Transaction: Authorization'] = $transaction->bank_authorisation_code;

                if ($afterProduced) {
                    $line['Delivery Date'] = Carbon::createFromFormat('Y-m-d H:i:s', $transaction->date)->format('Y-m-d');
                    $line[static::netColumn] = Number::decimal(($amount) / (1 + $effectiveVatRate));
                    $line[static::vatColumn] = Number::decimal(($amount) - $line[static::netColumn]);
                    $line['Line: Total'] = $amount;
                    // Hard Coded
                    $line['Line: Product ID'] = 3000;

                }

                if ($totalPayments === $calculated) {
                    $afterProduced = true;
                }

                static::addOrderRow($line);
                if ($includeInPickList) {
                    static::addPickListRow($line);
                }
            }

        }

        // Ftp the files ?
        if ($uploadToDataWarehouse) {

            $filesystem = new Filesystem(new Ftp([
                'host'     => FTP_HOST,
                'port'     => FTP_PORT,
                'username' => FTP_USER,
                'password' => FTP_PASSWORD,
                'passive'  => true,
                'ssl'      => true,
                'root'     => '/',
                'timeout'  => 10,
            ]));

            $filesystem->put($pickListFilename, file_get_contents($pickListPathAndFileName));
            $filesystem->put($orderFilename, file_get_contents($orderPathAndFileName));

            // Remove the file so we don't clutter the data directory
            unlink($pickListPathAndFileName);
            unlink($orderPathAndFileName);

        }

        Log::info("Order report end");

    }



    /**
     * runFor a timestamp - defaults to last run time stamp.
     *
     * @param $order_id
     * @param bool $ftp
     */
    public static function orderID($order_id, $ftp = true)
    {
        static::orderIDsBetween($order_id, $order_id, $ftp);
    }



    /**
     * Run the reports between two dates
     *
     * @param int $from
     * @param int $to
     * @param bool $ftp
     */
    public static function orderIDsBetween(int $from, int $to, bool $ftp = true)
    {

        $status = WooCommerce::validStatusesForSQL();

        $sql = "
            select ID 
            from 
                 wp_posts                  
            where
                post_type = 'shop_order'
                and
                post_status in ($status)
                and
                ID >= $from 
                and
                ID <= $to
            order by ID 
        ";

        static::do($sql, $ftp);

    }



    /**
     * For for a status a timestamp - defaults to last run time stamp.
     *
     * @param $status
     * @param bool $ftp
     */
    public static function orderStatus($status, $ftp = true)
    {

        $sql = "
            select ID 
            from wp_posts 
            where
                post_type = 'shop_order'
                and
                post_status = '$status'
            order by ID 
        ";

        static::do($sql, $ftp);

    }



    /**
     * Run the reports between two dates
     *
     * @param $from
     * @param $to
     * @param bool $ftp
     */
    public static function ordersBetween($from, $to, $ftp = true)
    {

        $dates = [];
        $period = CarbonPeriod::create($from, $to);

        // Iterate over the period
        foreach ($period as $date) {
            $dates[] = $date->format('Y-m-d');
        }

        $dates = "'" . implode("','", $dates) . "'";

        $status = WooCommerce::validStatusesForSQL();

        $sql = "
            select 
                ID 
            from 
                wp_posts P,
                wp_postmeta PM
            where
                P.post_type = 'shop_order'
                and
                P.post_status in ($status)
                and
                P.ID = PM.post_id
                and
                PM.meta_key = '_delivery_date'
                and
                PM.meta_value in ($dates)
                  
            order by ID 
        ";

        static::do($sql, $ftp);

    }



    /**
     * runSince a timestamp - defaults to last run time stamp.
     *
     * @param null $lastRun
     * @param null $to
     * @param bool $ftp
     */
    public static function runSince($lastRun = null, $to = null, $ftp = true)
    {

        Log::info("Order report start");

        $useLastRun = is_null($lastRun);

        if ($useLastRun) {
            $lastRun = Options::get('order_report_last_run_time', 0);
        }
        $toWhereClause = '';
        if (!is_null($to)) {
            $toDate = Carbon::createFromTimestampUTC($to)->format('Y-m-d H:i:s');
            $toWhereClause = " and post_modified_gmt <= '$toDate' ";
        }

        $lastDate = Carbon::createFromTimestampUTC($lastRun)->subHour()->format('Y-m-d H:i:s');

        $status = WooCommerce::validStatusesForSQL();

        $sql = "
            select ID 
            from wp_posts 
            where
                post_type = 'shop_order'
                and
                post_status in ($status)
                and
                post_modified_gmt >= '$lastDate'
                $toWhereClause
            order by ID 
        ";

        if ($useLastRun) {
            Options::set('order_report_last_run_time', Time::utcTimestamp());
        }

        static::do($sql, $ftp);

    }



    /**
     * @param $line
     * @param $product_id
     * @param $quantity
     * @param $price
     * @param $subtotal
     * @param $order
     * @param $pick
     * @return mixed
     * @throws CoteAtHomeException
     */
    protected static function addItem($line, $product_id, $quantity, $price, $subtotal, $order, $pick)
    {
        $line['Line: Type'] = 'Line Item';
        $line['Line: ID'] = '';
        $line['Line: Product ID'] = $product_id;

        $product = wc_get_product($product_id);

        if (!$product) {
            Log::error("Could not find product id: $product_id while creating report", $line);
            throw CoteAtHomeException::withMessage("Could not find product id: $product_id while creating report");
        }


        $line['Line: Product Handle'] = $product->get_slug();
        $line['Line: Title'] = str_replace('[NOT FOR SALE] ', '', $product->get_name());
        $line['Line: Name'] = str_replace('[NOT FOR SALE] ', '', $product->get_name());

        $parent_id = $product->get_parent_id();
        if ($parent_id) {
            $variation = new WC_Product_Variation($product_id);
            $line['Line: Variant ID'] = $variation->get_id();
            $line['Line: Variant Title'] = $variation->get_title();
            $barcode = get_post_meta($variation->get_id(), '_barcode', true);
        } else {
            $barcode = get_post_meta($product_id, '_barcode', true);
        }
        $line['Line: Quantity'] = $quantity;
        $line['Line: Price'] = $price;
        $line['Line: Discount'] = 0;
        $line['Line: Total'] = $subtotal;   ///
        $line['Line: Grams'] = $product->get_weight();
        $line['Line: Requires Shipping'] = 'TRUE';
        $line['Line: Vendor'] = 'Côte at Home';
        $line['Line: Gift Card'] = 'FALSE';
        $line['Line: Taxable'] = 'FALSE';
        $line['Line: Fulfillable Quantity'] = $line['Line: Quantity'];
        $line['Line: Fulfillment Service'] = 'manual';
        $line['Shipping Origin: Name'] = 'Côte at Home';
        $line['Shipping Origin: Country Code'] = 'GB';
        $line['Shipping Origin: City'] = 'Fitzrovia';
        $line['Shipping Origin: Address 1'] = '2nd floor, Woolverstone House';
        $line['Shipping Origin: Address 2'] = '61-62 Berners Street';
        $line['Shipping Origin: Zip'] = 'W1T 3NJ';
        $line['Line: Variant SKU'] = $barcode;
        $line['Line: Variant Barcode'] = $barcode;
        $line['Line: Variant Weight'] = $line['Line: Grams'];
        $line['Line: Variant Weight Unit'] = 'g';
        $line['Line: Variant Inventory Qty'] = '';
        $line['Line: Variant Cost'] = get_post_meta($product_id, 'cost', true);

        if ($order) {
            static::addOrderRow($line);
        }

        if ($pick) {
            static::addPickListRow($line);
        }

        return $line;
    }



    protected static function addOrderHeader()
    {
        $row = [];
        foreach (static::$columns as $column) {
            $row[$column] = $column;
        }
        static::addOrderRow($row);
        static::addPickListRow($row);

    }



    protected static function addOrderRow($row)
    {
        if (static::$orderCount > 0) {
            $row['Row #'] = static::$orderCount;
        }
        static::$orderCount++;
        static::$orderWriter->insertOne($row);
    }



    protected static function addPickListRow($row)
    {
        if (static::$pickListCount > 0) {
            $row['Row #'] = static::$pickListCount;
        }

        unset($row[self::vatColumn]);
        unset($row[self::netColumn]);
        static::$pickListCount++;
        static::$pickListWriter->insertOne($row);
    }



    protected static function createBlankRow()
    {
        $row = [];
        foreach (static::$columns as $column) {
            $row[$column] = '';
        }

        return $row;

    }



    protected static function reverseRow($line, $processedAt)
    {

        $line["Processed At"] = $processedAt;
        $line["Delivery Date"] = $processedAt;
        $line[static::netColumn] = Number::decimal($line[static::netColumn]) * -1;
        $line[static::vatColumn] = Number::decimal($line[static::vatColumn]) * -1;
        if ($line['Line: Total']) {
            $line['Line: Total'] = $line['Line: Total'] * -1;
        }

        switch ($line['Line: Type']) {
            case 'Line Item' :
                $line['Line: Type'] = 'Refund Line';
                $line['Line: Quantity'] = $line['Line: Quantity'] * -1;
                $line['Line: Price'] = $line['Line: Price'] * -1;
                break;
            case 'Discount' :
                $line['Line: Type'] = 'Refund Discount';
                $line['Line: Discount'] = $line['Line: Discount'] * -1;
                break;
            case 'Delivery Slot' :
                $line['Line: Type'] = 'Refund Delivery Slot';
                $line['Transaction: Amount'] = $line['Transaction: Amount'] * -1;
                break;
            case 'Event' :
                $line['Line: Type'] = 'Refund Event';
                $line['Line: Discount'] = $line['Line: Discount'] * -1;
                break;
            case 'Shipping Line' :
                $line['Line: Type'] = 'Refund Shipping Line';
                $line['Line: Price'] = $line['Line: Price'] * -1;
                break;
            case 'Gift Card' :
                $line['Line: Type'] = 'Refund Gift Card';
                $line['Transaction: Amount'] = $line['Transaction: Amount'] * -1;
                break;
        }

        return $line;
    }



    private static function logOrder($order_id, $message)
    {
        update_post_meta($order_id, '_order_report_log', [
            'status' => $message,
            'date'   => Time::now()->format('Y-m-d H:i:s'),
        ]);
    }


}
