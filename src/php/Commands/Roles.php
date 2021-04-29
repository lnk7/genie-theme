<?php

namespace Theme\Commands;

class Roles
{


    static $capabilities = [
        'order_editor_make_product_free',
        'order_editor_create_gift_card',
        'shop_user',
        'shop_user_plus',
        'shop_admin',
    ];


    public function setup()
    {

        add_role('shop_user', 'Shop User', []);
        add_role('shop_user_plus', 'Shop User Plus', []);
        add_role('shop_admin', 'Shop Admin', []);

        $admin = get_role('administrator');
        foreach (static::$capabilities as $cap) {
            $admin->add_cap($cap);
        }

    }

}
