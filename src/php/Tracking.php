<?php

namespace Theme;


use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Session;
use Lnk7\Genie\Utilities\RegisterAjax;

class Tracking implements GenieComponent
{

    public static function setup()
    {


        RegisterAjax::url('tracking')
            ->run(function () {

                $tracking = Session::get('tracking', []);
                Session::remove('tracking');
                return $tracking;
            });
    }



    public static function identify($email)
    {
        static::addCommand([
            'command' => 'identify',
            'email'   => $email,
        ]);

    }



    public static function track($event, $properties)
    {

        static::addCommand([
            'command'    => 'track',
            'event'      => $event,
            'properties' => $properties,
        ]);

    }



    public static function update($properties)
    {
        static::addCommand([
            'command'    => 'update',
            'properties' => $properties,
        ]);

    }



    private static function addCommand($command)
    {

        if (defined('EXPONEA_TRACKING') && EXPONEA_TRACKING && !is_user_logged_in()) {

            $tracking = Session::get('tracking', []);
            $tracking[] = $command;
            Session::set('tracking', $tracking);
        }
    }

}
