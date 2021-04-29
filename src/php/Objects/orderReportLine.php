<?php

namespace Theme\Objects;

use Theme\Reports\OrderReport;
use Lnk7\Genie\Interfaces\GenieComponent;
use Lnk7\Genie\Traits\HasData;
use Lnk7\Genie\Utilities\HookInto;
use Lnk7\Genie\View;


class orderReportLine implements GenieComponent
{


    use HasData;

    static $tableName = 'order_report';




    public function __construct($id)
    {
        global $wpdb;
        $this->data = $wpdb->get_row("select * from " . static::getTableName() . "  where order_id = $id", ARRAY_A);
        if (!$this->data) {
            $this->order_id = $id;

        }

    }


    static public function setup()
    {

        HookInto::filter('deploy_tables')->run(function ($tables) {
            global $wpdb;

            $sql = View::with('admin/tables/order_report.twig')
                ->addVars([
                    'tableName'       => static::getTableName(),
                    'columns'         => orderReport::$columns,
                    'charset_collate' => $wpdb->get_charset_collate(),
                ])
                ->render();

            $tables[] = $sql;
            return $tables;

        });

    }


    public static function find($id)
    {
        return new static($id);
    }


    public static function getTableName()
    {
        global $wpdb;

        return $wpdb->prefix . static::$tableName;
    }


    /**
     * magic set
     *
     * @param $var
     * @param $value
     */
    public function __set($var, $value)
    {

        if (array_key_exists($var, static::$columns)) {

            $once = static::$columns[$var]['once'] ?? false;

            if ($once && isset($this->data[$var]) && $this->data[$var]) {
                return;
            }

            $this->data[$var] = $value;
        }

    }


    public function save()
    {

        global $wpdb;
        //$this->processed = null;
        $wpdb->replace(static::getTableName(), $this->data);

        return $this;

    }

}
