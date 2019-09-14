<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use IngressITSolutions\Generator\Generator;
use IngressITSolutions\Generator\Exception\BaseException;

/**
 * Class GeneratorTest
 *
 * @package Tests
 * @author  Ingress Team <bhavikf@gmail.com>
 */
class GeneratorTest extends TestCase
{
    /**
     * @throws \IngressITSolutions\Generator\Exception\BaseException
     */
    public function testCanGenerateLicenseKey()
    {
        echo "help";
        $data       = [
            "email" => "bhavikf@gmail.com",
        ];
        $privateKey = file_get_contents(__DIR__ . '/keys/private_key.pem');

        $license = Generator::generate($data, $privateKey);

        $this->assertIsString($license);
    }

    public function testCanThrowExceptionForWrongPublicKey()
    {
        $data       = [
            "email" => "bhavikf@gmail.com",
        ];
        $privateKey = '';

        try {
            Generator::generate($data, $privateKey);
        } catch (BaseException $e) {
            $this->assertIsString($e->getMessage());
            $this->assertStringContainsString('OpenSSL: Unable to get private key', $e->getMessage());
        }
    }

    /**
     * @throws \IngressITSolutions\Generator\Exception\BaseException
     */
    public function testCanParseLicenseKey()
    {
        $data       = [
            "email" => "bhavikf@gmail.com",
        ];
        $publicKey  = file_get_contents(__DIR__ . '/keys/public_key.pem');
        $privateKey = file_get_contents(__DIR__ . '/keys/private_key.pem');

        $license       = Generator::generate($data, $privateKey);
        $parsedLicense = Generator::parse($license, $publicKey);

        $this->assertIsString($license);
        $this->assertIsArray($parsedLicense);
    }

    public function testCanThrowExceptionForWrongPrivateKey()
    {
        $licenseKey = 'license-key';
        $privateKey = '';

        try {
            Generator::parse($licenseKey, $privateKey);
        } catch (BaseException $e) {
            $this->assertIsString($e->getMessage());
            $this->assertStringContainsString('OpenSSL: Unable to get public key', $e->getMessage());
        }
    }
}
