<?php

namespace Theme;

use Dompdf\Dompdf;
use FontLib\Exception\FontNotFoundException;
use FontLib\Font;
use Lnk7\Genie\Debug;

class FontLoader
{


    /**
     * Tell domPDF about our custom fonts, and convert them for usage
     * These needed to be loaded into the vendor directory, so this
     * needs to be done after each composer update / install
     * If you know of a way not to store fonts in the vendor folder... more power to you.
     *
     * You can test this by removing the vendor directory, doing a composer update/install
     * and then trying to create a PDF invoice for an order.
     */
    static function installFonts()
    {

        $fontsFolder = trailingslashit(get_stylesheet_directory() . '/assets/fonts');

        $dompdf = new Dompdf();
        $fontMetrics = $dompdf->getFontMetrics();

        $fontsToLoad = [
            'Copperplate' => [
                'normal'      => $fontsFolder . 'Copperplate.ttf',
                'bold'        => $fontsFolder . 'CopperplateBold.ttf',
                'italic'      => null,
                'bold_italic' => null,
            ],
            'MrsEaves'    => [
                'normal'      => $fontsFolder . 'MrsEaves.ttf',
                'bold'        => null,
                'italic'      => null,
                'bold_italic' => null,
            ],
        ];

        foreach ($fontsToLoad as $fontName => $fontVariations) {

            $entry = [];

            foreach ($fontVariations as $var => $src) {

                // If we don't have a variation - use the normal one
                if (is_null($src)) {
                    $entry[$var] = $dompdf->getOptions()->get('fontDir') . '/' . mb_substr(basename($fontVariations['normal']), 0, -4);
                    continue;
                }

                $dest = $dompdf->getOptions()->get('fontDir') . '/' . basename($src);

                // Copy our fonts into the vendor folder
                if (!copy($src, $dest)) {
                    Log::error(static::class . "::installFonts  - Unable to copy '$src' to '$dest'");
                    continue;
                }

                $fontEntryName = mb_substr($dest, 0, -4);

                try {
                    $font_obj = Font::load($dest);
                } catch (FontNotFoundException $e) {
                    Log::error(static::class . "::installFonts " . $e->getMessage());
                    continue;
                }
                $font_obj->saveAdobeFontMetrics($fontEntryName . '.ufm');
                $font_obj->close();

                $entry[$var] = $fontEntryName;
            }
            $fontMetrics->setFontFamily($fontName, $entry);
        }

        // Save the changes
        $fontMetrics->saveFontFamilies();
    }
}
