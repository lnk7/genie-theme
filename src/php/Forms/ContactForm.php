<?php

namespace Theme\Forms;

use Theme\APIs\Exponea;
use Theme\APIs\FreshDesk;
use Theme\Log;
use Theme\Settings;
use Lnk7\Genie\AjaxHandler;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Utilities\SendEmail;
use Lnk7\Genie\View;
use Throwable;

class ContactForm implements GenieComponent
{


    public static function setup()
    {

        AjaxHandler::register('contact_form', function ($purpose, $photoCount, $firstName, $lastName, $email, $phone, $message, $orderID, $location) {

            $photos = [];

            $contactFormFolder = static::getContactFormFolder();

            for ($i = 0; $i < $photoCount; $i++) {

                $originalName = $_FILES['photos']['name'][$i];
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $filename = wp_generate_uuid4() . '.' . $ext;

                $tempFile = $_FILES['photos']['tmp_name'][$i];

                // make sure it's an image
                if (!@is_array(getimagesize($tempFile))) {
                    continue;
                }
                copy($tempFile, $contactFormFolder . $filename);
                $photos[] = (object)[
                    'url'          => home_url('/wp-content/uploads/contact_form/' . $filename),
                    'originalName' => $originalName,
                    'filename'     => $filename,
                    'extension'    => $ext,
                ];
            }

            $data = compact('purpose', 'firstName', 'lastName', 'email', 'phone', 'message', 'orderID', 'location');

            $to = Settings::get('contact_form_email', 'athome@cote.co.uk');

            SendEmail::to($to)
                ->subject("Cote Website Enquiry [Côte at Home]")
                ->body(
                    View::with('admin/emails/contact_form.twig')
                        ->addVar('data', $data)
                        ->addVar('photos', $photos)
                        ->render()
                )
                ->send();

            $email = strtolower(trim($email));

            // Let's update the customers data
            $payload = [
                'customer_ids' => [
                    'registered' => $email,
                ],
                'properties'   => [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'email'      => $email,
                ],
            ];
            if ($phone) {
                $payload['properties']['phone'] = $phone;
            }

            Exponea::update($payload);

            $payload = [
                'customer_ids' => [
                    'registered' => $email,
                ],
                'event_type'   => 'contact_us',
                'timestamp'    => time(),
                'properties'   => [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'phone'      => $phone,
                    'email'      => $email,
                    'orderID'    => $orderID ? $orderID : '',
                    'location'   => $location,
                    'message'    => $message,
                ],
            ];
            Exponea::track($payload);

            $purpose = $purpose ? $purpose : 'General';

            $message = View::with('admin/freshdesk/contact_form.twig')
                ->addVar('data', $data)
                ->addVar('photos', $photos)
                ->render();

            $fullName = $firstName . ' ' . $lastName;

            try {
                $freshDesk = FreshDesk::createTicket($message, "Cote At Home Contact Us form", $email, $fullName, $phone, [ucfirst($purpose)]);
                $freshDeskResult = true;
                if ($freshDesk->failed()) {
                    $freshDeskResult = $freshDesk->getResponseBody();
                }
            } catch (Throwable $e) {
                $freshDeskResult = $e->getMessage();
            }

            if ($freshDeskResult !== true) {
                Log::error('Fresh Desk Error', [
                    'data'            => $data,
                    'freshDeskResult' => $freshDeskResult,
                ]);
            }

            return [
                'success'   => true,
                'message'   => 'message saved successfully',
                'freshDesk' => $freshDeskResult,
            ];

        });

    }


    /**
     * Where do we store files uploaded through the form?
     *
     * @return string
     */
    public static function getContactFormFolder()
    {
        $upload = wp_upload_dir();
        $upload_dir = $upload['basedir'];
        return trailingslashit($upload_dir . '/contact_form');

    }

}
