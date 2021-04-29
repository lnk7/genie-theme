<?php

namespace Theme\Objects;

use Carbon\Carbon;
use Theme\APIs\Toggle;
use Theme\DataStores\GiftCardDataStore;
use Theme\Exceptions\CoteAtHomeException;
use Theme\Log;
use Theme\OrderItems\GiftCardItem;
use Theme\Theme;
use Theme\Utils\Number;
use Lnk7\Genie\Abstracts\CustomPost;
use Lnk7\Genie\Fields\DateField;
use Lnk7\Genie\Fields\DateTimeField;
use Lnk7\Genie\Fields\NumberField;
use Lnk7\Genie\Fields\PostObjectField;
use Lnk7\Genie\Fields\RepeaterField;
use Lnk7\Genie\Fields\SelectField;
use Lnk7\Genie\Fields\TextAreaField;
use Lnk7\Genie\Fields\TextField;
use Lnk7\Genie\Fields\TrueFalseField;
use Lnk7\Genie\Utilities\CreateCustomPostType;
use Lnk7\Genie\Utilities\CreateSchema;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\Utilities\When;
use Lnk7\Genie\Utilities\Where;
use Throwable;

/**
 * Class GiftCard
 *
 * @package CoteAtHome\Objects
 * @property string $type
 * @property float $balance
 * @property Carbon $expiry
 * @property string $expiration_date
 * @property bool $delivery
 * @property string $delivery_date
 * @property int $delivery_area_id
 * @property int $delivery_company_id
 * @property string $postcode
 * @property string $order_id
 * @property string $used_order_id
 * @property array $activity
 */
class GiftCard extends CustomPost
{


    const toggleCard = 'toggle';


    const slotCard = 'slot';


    const coteCardPrefix = 'GC-';


    static $postType = 'gift-card';


    /**
     * GiftCard constructor.
     *
     * @param null $id
     */
    function __construct($id = null)
    {
        parent::__construct($id);

        // Turn the date into something more useful.
        if ($this->expiration_date) {
            $this->expiry = Carbon::createFromFormat('Y-m-d', $this->expiration_date);
        }
        //make sure !
        $this->balance = Number::decimal($this->balance);

    }


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
            ->icon('dashicons-tickets-alt')
            ->removeSupportFor(['editor', 'thumbnail'])
            ->set('capabilities', [
                'edit_post'          => 'shop_user_plus',
                'edit_posts'         => 'shop_user_plus',
                'edit_others_posts'  => 'shop_user_plus',
                'publish_posts'      => 'shop_user_plus',
                'read_post'          => 'shop_user_plus',
                'read_private_posts' => 'shop_user_plus',
                'delete_post'        => 'shop_user_plus',
            ])
            ->backendOnly()
            ->register();

        /**
         * The Schema
         */
        CreateSchema::Called('Gift Card')
            ->instructionPlacement('field')
            ->style('seamless')
            ->withFields([

                SelectField::called('type')
                    ->choices([
                        self::toggleCard => 'Toggle',
                        self::slotCard   => 'Delivery Slot',
                    ])
                    ->returnFormat('value')
                    ->wrapperWidth(33)
                    ->default(self::toggleCard),

                NumberField::called('balance')
                    ->prepend('£')
                    ->wrapperWidth(33),
                DateField::called('expiration_date')
                    ->returnFormat('Y-m-d')
                    ->wrapperWidth(33)
                    ->required(true),

                PostObjectField::called('cc_id')
                    ->label('Customer')
                    ->postObject('customer')
                    ->returnFormat('id')
                    ->shown(When::field('type')->equals(static::slotCard))
                    ->wrapperWidth(50),

                NumberField::called('order_id')
                    ->label('Bought with Order - Use the date attached to this order')
                    ->shown(When::field('type')->equals(static::slotCard))
                    ->wrapperWidth(25),
                NumberField::called('used_order_id')
                    ->shown(When::field('type')->equals(static::slotCard))
                    ->label('Used on Order')
                    ->wrapperWidth(25),

                RepeaterField::called('activity')
                    ->layout('table')
                    ->withFields([
                        DateTimeField::called('date_time')
                            ->displayFormat('d/m/Y g:i:s a'),
                        TextField::called('action'),
                        NumberField::called('amount')
                            ->prepend('£'),
                        TextAreaField::called('note')
                            ->rows(2),
                        TrueFalseField::called('toggle_updated')
                            ->shown(When::field('type')->notEquals(static::slotCard)),
                        TextAreaField::called('toggle_message')
                            ->shown(When::field('type')->notEquals(static::slotCard))
                            ->rows(2),
                        NumberField::called('reference_activity_id'),
                    ]),
            ])
            ->shown(Where::field('post_type')->equals(static::$postType))
            ->attachTo(static::class)
            ->register();

        /**
         * Create our own data store for Woo Commerce
         */
        HookInto::filter('woocommerce_data_stores')
            ->run(function ($stores) {
                if (!isset($stores['order-item-cah_gift_card'])) {
                    $stores['order-item-cah_gift_card'] = GiftCardDataStore::class;
                }

                return $stores;

            });

        /**
         * The Item
         */
        HookInto::filter('woocommerce_get_items_key')
            ->run(function ($key, $item) {
                if ($item instanceof GiftCardItem) {
                    return 'cah_gift_card_lines';
                }

                return $key;
            });

        /**
         * get the group name
         */
        HookInto::filter('woocommerce_order_type_to_group')
            ->run(function ($groups) {
                $groups['cah_gift_card'] = 'cah_gift_card_lines';
                return $groups;
            });

        /**
         * Get the classname for this item
         */
        HookInto::filter('woocommerce_get_order_item_classname')
            ->run(function ($classname, $item_type) {
                if ($item_type !== 'cah_gift_card') {
                    return $classname;
                }
                return GiftCardItem::class;
            });

    }


    /**
     * Since we use title as the card number - just use that
     *
     * @param $cardNumber
     *
     * @return bool|GiftCard
     */
    public static function getByCardNumber($cardNumber)
    {
        return static::getByTitle($cardNumber);
    }


    /**
     * Check is a code is a Cote Gift Card
     *
     * @param $card
     *
     * @return false|int
     */
    public static function looksLikeACoteCard($card)
    {
        return preg_match('/^' . static::coteCardPrefix . '[A-Z]{2}-[A-Z0-9]{5,}$/', $card);
    }


    /**
     * Check if the card is a toggle card
     *
     * @param $card
     *
     * @return false|int
     */
    public static function looksLikeAGiftCard($card)
    {
        $card = strtoupper(trim($card));
        return static::looksLikeAToggleCard($card) || static::looksLikeACoteCard($card);
    }


    /**
     * Check if a code is a toggle gift card
     *
     * @param $card
     *
     * @return false|int
     */
    public static function looksLikeAToggleCard($card)
    {
        return preg_match('/^[0-9]{19}$/', $card);
    }


    /**
     * @param $cardNumber
     *
     * @return bool|GiftCard|static
     * @throws CoteAtHomeException
     */
    public static function syncWithToggle($cardNumber)
    {

        try {
            $toggleCard = Toggle::getCard($cardNumber);
        } catch (Throwable|CoteAtHomeException $e) {
        }

        if (!$toggleCard || !$toggleCard->card_reference) {
            Log::error("Unable to find card $cardNumber, please contact customer services.");
            throw CoteAtHomeException::withMessage("Unable to find card $cardNumber, please check the card number.");
        }

        $balance = Number::decimal($toggleCard->balance / 100);

        $date = Carbon::createFromFormat('Y-m-d H:i:s', $toggleCard->expiry_time);

        $card = GiftCard::getByCardNumber($cardNumber);

        if (!$card) {
            $card = new static();
            $card->type = self::toggleCard;
            $card->post_title = $cardNumber;
        }

        $card->balance = $balance;
        $card->expiry = $date;

        $card->logActivity('sync', $balance, 'Sync', false, 'Synced from Toggle');
        $card->save();

        return $card;

    }


    //Check balance is not 0 and balance has not changed
    public function checkCardBalance($cardNumber, $balance){

        $card = self::syncWithToggle($cardNumber);

        if($card->balance != number_format($balance, 2)){
            Throw CoteAtHomeException::withMessage("Giftcard has been updated. Please readd your giftcard to continue");
        }

        if (Theme::inDevelopment() && $cardNumber === '6301190003022629372' || Number::Decimal($card->balance,2) === Number::decimal(0,2)) {
            Throw CoteAtHomeException::withMessage("Sorry gift card $cardNumber has no balance");
        }

    }

    // is this a slot card ?

    /**
     * Adjust the balance of the gift card
     *
     * @param $amount
     * @param string $note
     */
    public function adjustBalance($amount, $note = '')
    {
        $amount = Number::decimal($amount);

        if (($this->balance + $amount) < 0) {
            wp_die(sprintf(__('Balance is currently %s, unable to adjust by %s', 'pw-woocommerce-gift-cards'), $this->balance, $amount));
        }

        $this->balance += $amount;

        switch ($this->type) {

            case static::toggleCard :
                try {
                    Toggle::maybeAdjustCard($this->post_title, $amount, $note);
                    $success = true;
                    $message = 'Toggle updated successfully';

                } catch (Throwable $e) {
                    $success = false;
                    $message = $e->getMessage();

                }
                $this->logActivity('transaction', $amount, $note, $success, $message,);

                break;
            case static::slotCard :
                $this->logActivity('transaction', $amount, $note, false);
                break;
        }
        $this->save();

    }


    public function beforeSave()
    {
        // convert back to wordpress Format
        $this->expiration_date = $this->expiry->format('Ymd');
    }


    /**
     * Check if this gift card has expired.
     *
     * @return bool
     */
    public function hasExpired()
    {
        $now = Carbon::now()->endOfDay();
        return $now->isAfter($this->expiry->endOfDay());
    }


    /**
     * is this gift card still active ?
     *
     * @return bool
     */
    public function hasNotExpired()
    {
        return !$this->hasExpired();
    }


    function isSlotCard()
    {
        return $this->type === self::slotCard;
    }


    /**
     * is this a toggle card?
     *
     * @return bool
     */
    function isToggleCard()
    {
        return $this->type === self::toggleCard;
    }


    public function logActivity($action, $amount = null, $note = null, $toggle_updated = true, $toggle_message = null, $reference_activity_id = null)
    {

        if (!is_array($this->activity)) {
            $this->activity = [];
        }

        $rows = $this->activity;

        $rows[] = [
            static::getKey('date_time')             => Carbon::now()->format('Y-m-d H:i:s'),
            static::getKey('action')                => $action,
            static::getKey('amount')                => $amount,
            static::getKey('note')                  => $note,
            static::getKey('toggle_updated')        => $toggle_updated,
            static::getKey('toggle_message')        => $toggle_message,
            static::getKey('reference_activity_id') => [$reference_activity_id],
        ];

        $this->activity = $rows;

    }


    public function setDefaults()
    {
        parent::setDefaults();
        $this->activity = [];
        $this->expiry = Carbon::now();

    }

}
