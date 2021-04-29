<?php

namespace Theme\APIs;

use Lnk7\Genie\Utilities\ApiCall;

class FreshDesk
{


    /**
     * Create a ticket
     *
     * @param $message
     * @param $subject
     * @param $email
     * @param string $name
     * @param string $phone
     * @param $tags
     *
     * @return Object
     */
    public static function createTicket($message, $subject, $email, $name = '', $phone = '', $tags)
    {

        $freshDeskData = [
            "description" => $message,
            "subject"     => $subject,
            "email"       => $email,
            "type"     => "Web Comment",
            "priority" => 1,
            "status"   => 2,
        ];
        if ($phone) {
            $freshDeskData["phone"] = $phone;
        }
        if ($name) {
            $freshDeskData["name"] = $name;
        }

        return static::makeCall("tickets", $freshDeskData, 'POST');
    }


    /**
     * Do the API Call.
     *
     * @param $endpoint
     * @param $payload
     * @param string $method
     *
     * @return ApiCall
     */
    protected static function makeCall($endpoint, $payload, $method = 'GET')
    {

        $url = trailingslashit(FRESHDESK_ENDPOINT) . $endpoint;

        return ApiCall::to($url)
            ->usingMethod($method)
            ->addHeader('Authorization', 'Basic ' . base64_encode(FRESHDESK_KEY . ':x'))
            ->withJson($payload)
            ->send();

    }

}
