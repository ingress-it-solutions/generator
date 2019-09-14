<h1 align="center">
	<img height="90" src="" alt="Generator is a library for generating and parsing Data with Private and Public Key" />
	<br> Generator
</h1>
<p align="center">
  <a href="https://codecov.io/gh/ingress-it-solutions/generator">
  <img src="https://codecov.io/gh/ingress-it-solutions/generator/branch/master/graph/badge.svg" />
</a>
  
  <a href="https://twitter.com/home?status=PHP%20License%20by%20%40ziishaned%20http%3A//github.com/ingress-it-solutions/generator">
    <img src="https://img.shields.io/badge/twitter-tweet-blue.svg?style=flat-square"/>
  </a>
  <a href="https://twitter.com/ingressit">
    
  </a>
</p>

<p align="center"><code>generator</code> is a library for generating and parsing data.</p>

## Requirements

* PHP >= 5.4
* OpenSSL

## Generating Key Pair

Make sure OpenSSL is configured on your machine.

1. Generate the Private key file by running the following command:
   ```bash
   openssl genpkey -algorithm RSA -out private_key.pem -pkeyopt rsa_keygen_bits:2048
   ```

2. Run the following command to generate public key:
   ```bash
   openssl rsa -pubout -in private_key.pem -out public_key.pem
   ```

## Installation

```bash
composer require ingress-it-solutions/generator
```

## Usage

Before running the following code make sure you have the `public_key` and `private_key` files.

### Generating

Use the following code to generate the license key:

```php
<?php

use IngressITSoltuions\Generator\Generator;

$data = [
  "firstName" => "John",
  "lastName"  => "Doe",
  "email"     => "john_doe@email.com",
];

$privateKey = file_get_contents('path/to/private_key.pem');
$license    = Generator::generate($data, $privateKey);

var_dump($license);
```

The above code will output the following result:

```json
agW4Riht6xHEfbpDaZUcTCmZVHgGgCnzXc0+nqLAMjuS6ouuGQVv/JqjAuo89tUgTu3F7Q+WProPcNm1aXdavxj3xOxTJ3e2w0NSS09sBZONxG9MzzofqvYPCnu/I1WMLgaRXiiNJcz5WtqFLFSdTgehqU5VLO+eDhfWUeZ0EJlCtCLPu19hP56/+24+/tmnh4ySLc9tV+YGLYtpmt7Gyf+h3sbMO0SJMwe+XSuuTcUsIUDg3AQUlj7c4ctwhkdYkRyyjj27U09CgpWWgU5b3sXSqZ3DFdTNaP8sIVH3Y39b7/o+Gx7WIHzngCnczK58L81LTVwnkyzSBqKUT5oq4A==
```

### Parsing

Use the following code to parse the license key:

```php
<?php

use IngressITSoltuions\Generator\Generator;

$license = 'agW4Riht6xHEfbpDaZUcTCmZVHgGgCnzXc0+nqLAMjuS6ouuGQVv/JqjAuo89tUgTu3F7Q+WProPcNm1aXdavxj3xOxTJ3e2w0NSS09sBZONxG9MzzofqvYPCnu/I1WMLgaRXiiNJcz5WtqFLFSdTgehqU5VLO+eDhfWUeZ0EJlCtCLPu19hP56/+24+/tmnh4ySLc9tV+YGLYtpmt7Gyf+h3sbMO0SJMwe+XSuuTcUsIUDg3AQUlj7c4ctwhkdYkRyyjj27U09CgpWWgU5b3sXSqZ3DFdTNaP8sIVH3Y39b7/o+Gx7WIHzngCnczK58L81LTVwnkyzSBqKUT5oq4A==';

$publicKey     = file_get_contents('path/to/public_key.pem');
$parsedLicense = Generator::parse($license, $publicKey);

var_dump($parsedLicense);
```

The above code will output the following result:

```json
{
    "firstName": "John",
    "lastName": "Doe",
    "email": "john_doe@email.com"
}
```

## Contributions

Feel free to submit pull requests, create issues or spread the word.

## License

MIT &copy; [Ingress IT Solutions](https://twitter.com/ingressit)
