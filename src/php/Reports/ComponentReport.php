<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Theme\Objects\ProductComponent;
use Theme\Objects\ShopifyType;
use Theme\Theme;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Filesystem;
use Lnk7\Genie\Debug;
use WC_Product_Variable;

class ComponentReport
{


    static $columns = [
        'product_id',
        'product_name',
        'component_id',
        'quantity',
        'component_name'
    ];


    public static function run($ftp = true)
    {

        set_time_limit(0);

        // The lines of the report
        $report = [];

        // Build the header row
        $row = [];
        foreach (static::$columns as $column) {
            $row[$column] = $column;
        }
        $report[] = $row;


        $pcs = ProductComponent::get();
        foreach ($pcs as $pc) {

            $id = $pc->getProductID();

            // Create a row
            $row = [];

            // create a row, and fill it with Blanks
            foreach (static::$columns as $column) {
                $row[$column] = '';
            }

            $row['product_id']  = $id;
            $row['product_name']  = $pc->post_title;

            foreach($pc->components as $component) {

                $product = wc_get_product($component['product_id']);
                if(!$product) {
                    continue;
                }

                $row['component_id'] = $component['product_id'];
                $row['component_name'] = $product->get_title();
                $row['quantity'] = $component['quantity'];
                $report[] = $row;
            }

        }




        $orderReportFolder = Theme::getCahDataFolder();

        $filename = 'components_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
        $pathAndFileName = $orderReportFolder . $filename;



        //load the CSV document from a string
        $writer = Writer::createFromPath($pathAndFileName, "w");
        CharsetConverter::addTo($writer, 'utf-8', 'iso-8859-15');

        //insert all the records
        $writer->insertAll($report);
    }

}
