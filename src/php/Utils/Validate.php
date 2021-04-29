<?php

namespace Theme\Utils;

use Theme\APIs\Postcode;
use Theme\Settings;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Lnk7\Genie\Options;
use Mailcheck\Mailcheck;
use Theme\Log;

class Validate
{


    /**
     * Validate an email address using PHP function
     *
     * @param $email
     * @return array
     */
    public static function email(string $email): array
    {

        $response = [
            'valid'      => static::isEmailValid($email),
            'suggestion' => '',
        ];

        $mailCheck = new Mailcheck();
        $suggestion = $mailCheck->suggest($email);
        if ($suggestion && $suggestion != $email && static::isEmailValid($suggestion)) {
            $response['suggestion'] = $suggestion;
        }

        return $response;
    }



    /**
     * Check if an email is formatted correctly.
     *
     * @param $email
     * @return bool
     */
    protected static function isEmailValid($email)
    {

        $domainValid = false;
        $emailValid = filter_var(trim($email), FILTER_VALIDATE_EMAIL) === $email;

        if ($emailValid) {
            $domain = substr($email, strpos($email, '@') + 1);
            $domainValid = checkdnsrr($domain);
        }

        return $emailValid and $domainValid;

    }



    /**
     * Validate a postcode, again the api database
     *
     * @param $postcode
     * @return array
     */
    public static function postcode($postcode)
    {

        $apiCall = Postcode::get($postcode);

        $response = [
            'valid'             => false,
            'formattedPostcode' => '',
        ];

        if ($apiCall->wasSuccessful()) {
            $data = $apiCall->getResponseBody();
            if ($data->status === 200) {
                $response['valid'] = true;
                $response['formattedPostcode'] = $data->result->postcode;
            }
        }

        if($response['valid'] == false){
            $backupapiCall = Postcode::getFallbackAPI($postcode);

            if ($backupapiCall->wasSuccessful()) {
                $data = $backupapiCall->getResponseBody();
                if($data->addresses){
                    $response['valid'] = true;
                    $response['formattedPostcode'] = $postcode;
                }

            }

            //Custom WP option to track paid api usage
            $currentCount = Options::get('_BACKUP_API_TRACKER');
            $dateNow = date('d/m/Y');

            if($currentCount != NULL){

                $storedDate = Options::get('_BACKUP_API_TRACKER_DATE');

                if ($dateNow > $storedDate) {
                    Options::set('_BACKUP_API_TRACKER_DATE', $dateNow);
                    Options::set('_BACKUP_API_TRACKER', 1);
                }else {
                    $currentCount++;
                    Options::set('_BACKUP_API_TRACKER', $currentCount);
                }

                $limit = Settings::get('postcode_api_limit', 2000);

                if($currentCount >= $limit){
                    LOG::error('Backup API checker for postcode has gone over it\'s daily limit', $currentCount);
                }
            }else{
                Options::set('_BACKUP_API_TRACKER_DATE', $dateNow);
                Options::set('_BACKUP_API_TRACKER', 1);
            }


        }

        return $response;

    }



    /**
     * Validate an email address using google's services.
     *
     * @param $number
     * @param $countryCode
     * @return array
     */
    public static function tel($number, $countryCode = 'GB')
    {

        $response = [
            'valid'       => false,
            'countryCode' => '',
            'nationalNumber' => ''
        ];

        /**
         * Lovely hack for Cote Staff :)
         */
        if (strpos($number, '777777') !== false) {
            return $response;
        }
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $phone = $phoneUtil->parse($number, $countryCode);
            $valid = $phoneUtil->isValidNumber($phone);

            if ($valid) {
                $response['valid'] = true;
                $response['countryCode'] = $phone->getCountryCode();
                $response['nationalNumber'] = $phone->getNationalNumber();
            }

        } catch (NumberParseException $e) {
        }

        return $response;

    }


}
