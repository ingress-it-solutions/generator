<?php

namespace IngressITSolutions\Generator;

use IngressITSolutions\Generator\Exception\BaseException;

class Generator
{

    //Block size for encryption block cipher
    //private $ENCRYPT_BLOCK_SIZE = 200;// this for 2048 bit key for example, leaving some room

    //Block size for decryption block cipher
    //private $DECRYPT_BLOCK_SIZE = 256;// this again for 2048 bit key


    /**
     * Generate license file.
     *
     * @param array  $data
     * @param string $privateKey
     *
     * @return string
     * @throws \IngressITSolutions\Generator\Exception\BaseException
     */
    public static function generate($data, $privateKey)
    {
        $encrypted = '';
        $plainData = json_encode($data);
        $plainData = str_split($plainData, 200);

        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new BaseException("OpenSSL: Unable to get private key");
        }

        foreach($plainData as $chunk)
        {
            $partialEncrypted = '';

            //using for example OPENSSL_PKCS1_PADDING as padding
            $encryptionOk = openssl_private_encrypt($chunk, $partialEncrypted, $privateKey, OPENSSL_PKCS1_PADDING);

            if($encryptionOk === false){
                throw new BaseException("OpenSSL: Unable to generate signature");
            }//also you can return and error. If too big this will be false
            $encrypted .= $partialEncrypted;
        }
        return base64_encode($encrypted);//encoding the whole binary String as MIME base 64

    }

    /**
     * Parse license file.
     *
     * @param string $licenseKey
     * @param string $publicKey
     *
     * @return string
     * @throws \IngressITSolutions\Generator\Exception\BaseException
     */
    public static function parse($licenseKey, $publicKey)
    {

        $decrypted = '';

        //decode must be done before spliting for getting the binary String
        $data = str_split(base64_decode($licenseKey), 256);

        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            throw new BaseException("OpenSSL: Unable to get public key");
        }

        foreach($data as $chunk)
        {
            $partial = '';

            //be sure to match padding
            $decryptionOK = openssl_public_decrypt($chunk, $partial, $publicKey, OPENSSL_PKCS1_PADDING);

            if($decryptionOK === false){
                throw new BaseException("OpenSSL: Unable to parse signature");
            }//here also processed errors in decryption. If too big this will be false
            $decrypted .= $partial;
        }
        $decryptedData = json_decode($decrypted, true);
        return $decryptedData;


    }
}
