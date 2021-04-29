<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Theme\Theme;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use WC_Product_Variable;

class JosieProductReport
{


    static $columns = [
        'ID',
        'Parent ID',
        'Title',
        'Price',
    ];


    public static function run()
    {

        // The lines of the report
        $report = [];

        // Build the header row
        $row = [];
        foreach (static::$columns as $column) {
            $row[$column] = $column;
        }
        $report[] = $row;

        // Grab all our products
        $posts = get_posts([
            'post_status'    => ['publish', 'trash'],
            'posts_per_page' => -1,
            'post_type'      => 'product',
        ]);

        $rowNumber = 1;

        foreach ($posts as $post) {

            $product = wc_get_product($post->ID);

            // Create a row
            $row = [];

            // create a row, and fill it with Blanks
            foreach (static::$columns as $column) {
                $row[$column] = '';
            }

            /**
             * @var WC_Product_Variable $product
             */

            $mainProductRow = $row;

            if ($product->get_type() === 'simple') {
                $row['ID'] = $product->get_id();
                $row['Title'] = $product->get_name();
                $row['Price'] = $product->get_price();
                $report[] = $row;
            } else {

               $variations = $product->get_available_variations();

                foreach ($variations as $index => $variation) {

                    $row = $mainProductRow;

                    // Add a new row from the 2nd variation
                    $variationProduct = wc_get_product($variation['variation_id']);
                    $row['ID'] = $variationProduct->get_id();
                    $row['Parent ID'] = $product->get_id();
                    $row['Title'] = $variationProduct->get_name();
                    $row['Price'] = $variationProduct->get_price();
                    $report[] = $row;
                    $rowNumber++;

                }
            }

        }

        $orderReportFolder = Theme::getCahDataFolder();

        $filename = 'josie_products.csv';
        $pathAndFileName = $orderReportFolder . $filename;

        //load the CSV document from a string
        $writer = Writer:: createFromPath($pathAndFileName, "w");
        CharsetConverter::addTo($writer, 'utf-8', 'iso-8859-15');

        $writer->setOutputBOM("\xEF\xBB\xBF");

        //insert all the records
        $writer->insertAll($report);

    }

}
