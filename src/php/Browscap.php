<?php

namespace Theme;

use Theme\Log;

class Browscap
{
    public static function importBrowscapFile(){

        try {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, BROWSCAP_SOURCE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $data = curl_exec($ch);
            curl_close($ch);

            $file = fopen(BROWSCAP_LOCAL_DESINATION, "w+");
            fputs($file, $data);
            fclose($file);

        }catch(\Exception $exception){
            log::error("Import of browscap file failed. $exception");
        }

    }
}

