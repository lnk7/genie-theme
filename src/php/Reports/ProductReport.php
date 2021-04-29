<?php

namespace Theme\Reports;

use Carbon\Carbon;
use Theme\Objects\ShopifyType;
use Theme\Theme;
use League\Csv\CharsetConverter;
use League\Csv\Writer;
use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Filesystem;
use WC_Product_Variable;

class ProductReport
{


    static $columns = [
        'ID',
        'Shopify ID',
        'Shopify Product ID',
        'Shopify Variant ID',
        'Handle',
        'Command',
        'Title',
        'Body HTML',
        'Vendor',
        'Type',
        'Tags',
        'Tags Command',
        'Created At',
        'Updated At',
        'Published',
        'Published At',
        'Published Scope',
        'Template Suffix',
        'URL',
        'Row #',
        'Top Row',
        'Custom Collections',
        'Smart Collections',
        'Variant Inventory Item ID',
        'Variant ID',
        'Variant Command',
        'Option1 Name',
        'Option1 Value',
        'Option2 Name',
        'Option2 Value',
        'Option3 Name',
        'Option3 Value',
        'Variant Position',
        'Variant SKU',
        'Variant Barcode',
        'Variant Image',
        'Variant Weight',
        'Variant Weight Unit',
        'Variant Price',
        'Variant Compare At Price',
        'Variant Taxable',
        'Variant Tax Code',
        'Variant Inventory Tracker',
        'Variant Inventory Policy',
        'Variant Fulfillment Service',
        'Variant Requires Shipping',
        'Variant Inventory Qty',
        'Variant Inventory Adjust',
        'Variant Cost',
        'Variant HS Code',
        'Variant Country of Origin',
        'Image Src',
        'Image Command',
        'Image Position',
        'Image Width',
        'Image Height',
        'Image Alt Text',
        'Inventory Available: 2nd floor, Woolverstone House',
        'Inventory Available Adjust: 2nd floor, Woolverstone House',
        'Metafield: title_tag [string]',
        'Metafield: description_tag [string]',
        'Metafield: cote.ingredients [string]',
        'Metafield: cote.gluten_free [string]',
        'Metafield: cote.home_freezing [string]',
        'Metafield: cote.vegetarian [string]',
        'Metafield: cote.set_menu [json_string]',
        'Metafield: seo.hidden [integer]',
        'Metafield: cote.hide [string]',
    ];


    static $delivery = [
        'FREE'      => ['id' => 1001, 'price' => 0],
        'STANDARD'  => ['id' => 1002, 'price' => 4.95],
        'PREMIUM'   => ['id' => 1003, 'price' => 10],
        'PREBOOKED' => ['id' => 1004, 'price' => 0],
        'CÔTE'      => ['id' => 1005, 'price' => 0],
        'DLY010'    => ['id' => 1006, 'price' => 0],
        'DLY011'    => ['id' => 1007, 'price' => 5],

    ];



    static function cleanShopifyID($id)
    {
        return preg_replace('/[^0-9]*/', '', $id);
    }



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

        // Grab all our products
        $posts = get_posts([
            'post_status'    => ['publish', 'draft', 'trash'],
            'posts_per_page' => -1,
            'post_type'      => 'product',
        ]);

        $rowNumber = 1;

        foreach (static::$delivery as $code => $data) {

            // Create a row
            $row = [];

            // create a row, and fill it with Blanks
            foreach (static::$columns as $column) {
                $row[$column] = '';
            }

            $row['ID'] = $data['id'];
            $row['Handle'] = $code;
            $row['Command'] = 'UPDATE';
            $row['Title'] = $code;
            $row['Vendor'] = 'Côte at Home';
            $row['Tags Command'] = 'REPLACE';
            $row['Published Scope'] = 'web';
            $row['Row #'] = $rowNumber;
            $row['Type'] = 'Delivery';
            $row['Top Row'] = 'TRUE';
            $row['Variant Price'] = $data['price'];
            $report[] = $row;
            $rowNumber++;
        }

        // Coupons
        $row = [];
        // create a row, and fill it with Blanks
        foreach (static::$columns as $column) {
            $row[$column] = '';
        }
        $row['ID'] = 2000;
        $row['Handle'] = 'Discounts';
        $row['Command'] = 'UPDATE';
        $row['Title'] = 'Discounts';
        $row['Type'] = 'Discounts';
        $row['Vendor'] = 'Côte at Home';
        $row['Tags Command'] = 'REPLACE';
        $row['Published Scope'] = 'web';
        $row['Row #'] = $rowNumber;
        $row['Top Row'] = 'TRUE';
        $row['Variant Price'] = 0;
        $report[] = $row;
        $rowNumber++;

        // Refunds
        $row = [];
        // create a row, and fill it with Blanks
        foreach (static::$columns as $column) {
            $row[$column] = '';
        }
        $row['ID'] = 3000;
        $row['Handle'] = 'Refunds';
        $row['Type'] = 'Refunds';
        $row['Command'] = 'UPDATE';
        $row['Title'] = 'Refunds';
        $row['Vendor'] = 'Côte at Home';
        $row['Tags Command'] = 'REPLACE';
        $row['Published Scope'] = 'web';
        $row['Row #'] = $rowNumber;
        $row['Top Row'] = 'TRUE';
        $row['Variant Price'] = 0;
        $report[] = $row;
        $rowNumber++;

        foreach ($posts as $post) {

            $product = wc_get_product($post->ID);

            // Create a row
            $row = [];

            // create a row, and fill it with Blanks
            foreach (static::$columns as $column) {
                $row[$column] = '';
            }

            $image = wp_get_attachment_image_src($product->get_image_id(), 'full');

            $terms = wp_get_post_terms($post->ID, ShopifyType::$taxonomy);
            $typeStrings = [];
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    $typeStrings[] = $term->name;
                }
            }

            $terms = wp_get_post_terms($post->ID, 'product_cat');
            $categories = [];
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    $categories[] = $term->name;
                }
            }

            $terms = wp_get_post_terms($post->ID, 'product_tag');
            $tags = [];
            if (!empty($terms)) {
                foreach ($terms as $term) {
                    $tags[] = $term->name;
                }
            }

            $row['ID'] = $product->get_id();
            $row['Handle'] = $post->post_name;
            $row['Command'] = 'UPDATE';
            $row['Title'] = str_replace('[NOT FOR SALE] ', '', $product->get_name());
            $row['Body HTML'] = htmlentities($product->get_description());
            $row['Vendor'] = 'Côte at Home';
            $row['Type'] = implode(',', $typeStrings);
            $row['Tags'] = implode(',', $tags);
            $row['Tags Command'] = 'REPLACE';
            $row['Created At'] = $product->get_date_created() ? $product->get_date_created()->format('c') : '';
            $row['Updated At'] = $product->get_date_modified() ? $product->get_date_modified()->format('c') : '';
            $row['Published'] = $post->post_status === 'published';
            $row['Published At'] = $product->get_date_modified() ? $product->get_date_modified()->format('c') : '';
            $row['Published Scope'] = 'web';
            $row['URL'] = $product->get_permalink();
            $row['Row #'] = $rowNumber;
            $row['Top Row'] = 'TRUE';
            $row['Custom Collections'] = implode(',', $categories);
            $row['Variant Command'] = 'MERGE';
            $row['Variant Barcode'] = get_post_meta($post->ID, '_barcode', true);
            $row['Variant Weight'] = $product->get_weight();
            $row['Variant Weight Unit'] = 'g';
            $row['Variant Price'] = $product->get_price();
            $row['Variant Taxable'] = 'FALSE';
            $row['Variant Inventory Policy'] = 'continue';
            $row['Variant Fulfillment Service'] = 'manual';
            $row['Variant Requires Shipping'] = 'TRUE';
            $row['Variant Cost'] = get_field('cost', $post->ID);
            $row['Image Src'] = $image ? $image[0] : '';
            $row['Image Command'] = 'MERGER';
            $row['Image Position'] = 1;
            $row['Image Width'] = $image ? $image[1] : '';
            $row['Image Height'] = $image ? $image[2] : '';
            $row['Option1 Name'] = 'Default Title';

            $row['Shopify ID'] = get_post_meta($product->get_id(), '_shopify_id', true);
            // Make sure this is not a variant

            $parent_id = $product->get_parent_id();
            if ($parent_id) {
                $row['Shopify Product ID'] = static::cleanShopifyID(get_post_meta($parent_id, '_shopify_id', true));
            } else {
                $row['Shopify Product ID'] = static::cleanShopifyID(get_post_meta($product->get_id(), '_shopify_id', true));
            }

            if ($product->get_type() === 'simple') {
                $report[] = $row;
                $rowNumber++;
                continue;
            }

            /**
             * @var WC_Product_Variable $product
             */

            $mainProductRow = $row;

            $variations = $product->get_available_variations();

            foreach ($variations as $index => $variation) {

                $row = $mainProductRow;

                // Add a new row from the 2nd variation
                $variationProduct = wc_get_product($variation['variation_id']);
                $attributes = $variationProduct->get_attributes();

                $count = 1;
                foreach ($attributes as $variationName => $variationValue) {

                    if (strpos($variationName, 'pa_') !== false) {
                        $tax = get_taxonomy($variationName);
                        $variationName = $tax->label;
                    }

                    $row["Option{$count} Name"] = $variationName;
                    $row["Option{$count} Value"] = $variationValue;
                    $count++;
                }

                $row['ID'] = $variationProduct->get_id();
                $row['Title'] = $variationProduct->get_name();
                $row['Row #'] = $rowNumber;
                $row['Type'] = implode(',', $typeStrings);
                $row['Variant Barcode'] = get_post_meta($variationProduct->get_id(), '_barcode', true);
                $row['Variant Weight'] = $variationProduct->get_weight();
                $row['Variant Weight Unit'] = 'g';
                $row['Variant Price'] = $variationProduct->get_price();
                $row['Shopify ID'] = get_post_meta($variationProduct->get_id(), '_shopify_id', true);
                $row['Shopify Variant ID'] = static::cleanShopifyID(get_post_meta($variationProduct->get_id(), '_shopify_id', true));

                $report[] = $row;
                $rowNumber++;

            }

        }

        $orderReportFolder = Theme::getCahDataFolder();

        $filename = 'products_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
        $pathAndFileName = $orderReportFolder . $filename;

        //load the CSV document from a string
        $writer = Writer:: createFromPath($pathAndFileName, "w");
        CharsetConverter::addTo($writer, 'utf-8', 'iso-8859-15');

        //insert all the records
        $writer->insertAll($report);

        if ($ftp && Theme::inProduction() && defined('FTP_HOST') && defined('COTE_FTP_REPORTS') && COTE_FTP_REPORTS) {

            $filesystem = new Filesystem(new Ftp([
                'host'     => FTP_HOST,
                'port'     => FTP_PORT,
                'username' => FTP_USER,
                'password' => FTP_PASSWORD,
                'passive'  => true,
                'ssl'      => true,
                'root'     => '/',
                'timeout'  => 10,
            ]));

            $filesystem->put($filename, file_get_contents($pathAndFileName)); // upload file

            // Remove the file so we don't clutter the data directory
            unlink($pathAndFileName);

        }

    }

}
