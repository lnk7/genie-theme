<?php

namespace Theme;

use Theme\Objects\Order;
use Dompdf\Dompdf;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\View;

class GiftPage implements GenieComponent
{

    /**
     * A list of urls needed for the checkout Page
     *
     * @var array
     */
    static $urls = [];



    public static function setup()
    {


        AjaxHandler::register('generateGiftPage', function ($order_id) {


            $order = Order::find($order_id);

            $shippingItem = $order->get_shipping_item();
            $giftMessage = $shippingItem->gift_message;

            if ($giftMessage) {
                return [
                    'success' => true,
                    'url'     => home_url('') . '?gift_order_id=' . $order_id,
                ];
            }
            return [
                'success' => false,
                'message' => 'No Gift message on order',
            ];

        });

        add_shortcode('gift_page', function () {
            View::with('gift_page/shortcode.twig')
                ->addVar('endpoint', AjaxHandler::generateUrl('generateGiftPage'))
                ->display();
        });


        HookInto::action('init')
            ->run(function () {

                if (!isset($_REQUEST['gift_order_id'])) {
                    return;
                }

                $order_id = (int)$_REQUEST['gift_order_id'];

                $order = Order::find($order_id);

                $shippingItem = $order->get_shipping_item();
                $giftMessage = $shippingItem->gift_message;

                $html = View::with('gift_page/message.twig')->addVar('giftMessage', $giftMessage)->render();

                // instantiate and use the dompdf class
                $dompdf = new Dompdf();
                $dompdf->loadHtml($html);

                // (Optional) Setup the paper size and orientation
                $dompdf->setPaper('A4');

                // Render the HTML as PDF
                $dompdf->render();

                // Output the generated PDF to Browser
                $dompdf->stream('Gift_message_for_order_' . $order_id, ['Attachment' => 0]);

                exit;

            });


    }


}
