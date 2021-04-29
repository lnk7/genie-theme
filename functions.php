<?php

namespace Theme;

use Theme\Forms\ContactForm;
use Theme\Objects\Coupon;
use Theme\Objects\Customer;
use Theme\Objects\DeliveryArea;
use Theme\Objects\DeliveryCompany;
use Theme\Objects\Date;
use Theme\Objects\Event;
use Theme\Objects\GiftCard;
use Theme\Objects\Review;
use Theme\Objects\ShortLink;
use Theme\Objects\Order;
use Theme\Objects\OriginalOrder;
use Theme\Objects\Product;
use Theme\Objects\ProductComponent;
use Theme\Objects\Redirection;
use Theme\Objects\Referral;
use Theme\Objects\Shipping;
use Theme\Objects\ShopifyType;
use Theme\Objects\ShopSession;
use Theme\Objects\Snapshot;
use Theme\Objects\Transaction;
use Theme\Tests\OrderEditorTests;
use Lnk7\Genie\Genie;

require 'vendor/autoload.php';

Genie::createTheme()
    ->withComponents([

    ])
    ->start();
