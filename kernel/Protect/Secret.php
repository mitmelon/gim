<?php
namespace Manomite\Protect;

use \ParagonIE\Halite\HiddenString;
use \ParagonIE\Halite\KeyFactory;
use \ParagonIE\Halite\Symmetric\Crypto as Symmetric;
use \ParagonIE\ConstantTime\Encoding;
use \ParagonIE\Halite\File;
use Manomite\Exception\ManomiteException as ex;
use Manomite\Engine\Fingerprint;
use Manomite\Engine\Network;
use \ParagonIE\Halite\Asymmetric\{
    Crypto,
    EncryptionSecretKey,
    EncryptionPublicKey
};

require_once __DIR__ . "/../../autoload.php";
class Secret
{
    protected $ext;
    protected $secretKey;
    private $masterKey;
    public $randomKey;
    private $folder;
    private $data;
    private $filter;

    public function __construct($data = null, $key = 'master_key')
    {

        $this->ext = '.mkey';
        $this->data = $data;
        $this->folder = SYSTEM_DIR . '/crypt_keys';
        if (!is_dir($this->folder)) {
            mkdir($this->folder, 0444, true);
        }
        $this->folder = $this->folder . '/' . $key . $this->ext;
        if (!file_exists($this->folder)) {
            KeyFactory::save(KeyFactory::generateEncryptionKey(), $this->folder);
            chmod($this->folder, 0444);
        }
        $this->secretKey = KeyFactory::loadEncryptionKey($this->folder);
        $this->randomKey = Encoding::hexEncode(random_bytes(16)); // Bit Generations
        $this->filter = new PostFilter;
    }

    public function create_key_pair()
    {
        $keypair = KeyFactory::generateEncryptionKeyPair();
        $secret = $keypair->getSecretKey();
        $public = $keypair->getPublicKey();
        return array('secret' => sodium_bin2hex($secret->getRawKeyMaterial()), 'public' => sodium_bin2hex($public->getRawKeyMaterial()));
    }

    public function encrypt()
    {
        try {
            return Symmetric::encrypt(new HiddenString($this->data), $this->secretKey);
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }

    public function decrypt()
    {
        try {
            $r = Symmetric::decrypt($this->data, $this->secretKey);
            return $r->getString();
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }

    public function encryptFile($fileInput, $fileOutput)
    {
        try {
            return File::encrypt($fileInput, $fileOutput, $this->secretKey);
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }

    public function decryptFile($fileInput, $fileOutput)
    {
        try {
            return File::decrypt($fileInput, $fileOutput, $this->secretKey);
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }

    public function asyEncrypt($message, $publicKey)
    {
        try {

            $publicKey = new EncryptionPublicKey(new HiddenString(sodium_hex2bin($publicKey)));
            return Crypto::seal(
                new HiddenString($message),
                $publicKey
            );
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }
    public function asyDecrypt($message, $key)
    {
        try {
            $key = new EncryptionSecretKey(new HiddenString(sodium_hex2bin($key)));
            $r = Crypto::unseal(
                $message,
                $key
            );
            return $r->getString();
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }

    public function asyEncryptFile($file, $output, $publicKey)
    {
        try {

            $publicKey = new EncryptionPublicKey(new HiddenString(sodium_hex2bin($publicKey)));
            File::seal(
                $file,
                $output,
                $publicKey
            );
            return true;
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }
    public function asyDecryptFile($key, $file, $output)
    {
        try {
            $key = new EncryptionSecretKey(new HiddenString(sodium_hex2bin($key)));
            File::unseal($file, $output, $key);
            return true;
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }

    public static function hash($pass)
    {
        return sodium_crypto_pwhash_str(
            $pass,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
        );
    }

    public static function verify_hash($hash, $pass)
    {
        return sodium_crypto_pwhash_str_verify($hash, $pass);
    }

    public function randing($len)
    {
        $r = '';
        $chars = array_merge(range('0', '9'), range('A', 'Z'), range('a', 'z'));
        $max = count($chars) - 1;
        for ($i = 0; $i < $len; $i++) {
            $rand = mt_rand(0, $max);
            $r .= $chars[$rand];
        }
        return $r;
    }

    public function generateNumber($len = 9)
    {
        $rand = '';
        while (!(isset($rand[$len - 1]))) {
            $rand .= mt_rand();
        }
        return substr($rand, 0, $len);
    }

    public function mask($cc, $maskFrom = 0, $maskTo = 4, $maskChar = '*', $maskSpacer = '-')
    {
        // Clean out
        $cc = str_replace(array('-', ' '), '', $cc);
        $ccLength = strlen($cc);

        // Mask CC number
        if (empty($maskFrom) && $maskTo == $ccLength) {
            $cc = str_repeat($maskChar, $ccLength);
        } else {
            $cc = substr($cc, 0, $maskFrom) . str_repeat($maskChar, $ccLength - $maskFrom - $maskTo) . substr($cc, -1 * $maskTo);
        }

        // Format
        if ($ccLength > 4) {
            $newCreditCard = substr($cc, -4);
            for ($i = $ccLength - 5; $i >= 0; $i--) {
                // If on the fourth character add the mask char
                if ((($i + 1) - $ccLength) % 4 == 0) {
                    $newCreditCard = $maskSpacer . $newCreditCard;
                }

                // Add the current character to the new credit card
                $newCreditCard = $cc[$i] . $newCreditCard;
            }
        } else {
            $newCreditCard = $cc;
        }

        return $newCreditCard;
    }

    public function request_generator($session_name = 'request_generator', $id = APP_NAME, $date = null)
    {
        $current_date = $date ?: date('d-m-Y');
        $before_date = isset($_SESSION[$session_name . '_payload_date']) ? $_SESSION[$session_name . '_payload_date'] : null;
        if (!$this->request_verify($session_name, APP_NAME)) {
            unset($_SESSION[$session_name]);
            unset($_SESSION[$session_name . '_payload_date']);
            $payload = (new Fingerprint())->codeGenerate($id, false, $current_date);
            $_SESSION[$session_name] = hash('sha256', $payload);
            $_SESSION[$session_name . '_payload_date'] = $current_date;
        }
    }

    public function request_verify($session_name = 'request_generator', $id = APP_NAME, $date = null)
    {
        if (isset($_SERVER['HTTP_REFERER'])) {
            $get_domain = (new Network)->get_domain_from_url($_SERVER['HTTP_REFERER']);
            $val_domain = (new Network)->get_domain_from_url(APP_DOMAIN);
            //Change here in production
            if ($get_domain === $val_domain || $get_domain === '127.0.0.1' || $get_domain !== $val_domain) {
                $current_date = $date ?: date('d-m-Y');
                $payload = (new Fingerprint())->codeGenerate($id, false, $current_date);
                if (isset($_SESSION[$session_name])) {
                    $current = hash('sha256', $payload);
                    $expected = $this->filter->strip($_SESSION[$session_name]);
                    if (hash_equals($expected, $current)) {
                        return $_SESSION[$session_name];
                    } else {
                        $this->take_action_on_hacks();
                        return false;
                    }
                } else {
                    $this->take_action_on_hacks();
                    return false;
                }
            }
        }
        return false;
    }

    private function take_action_on_hacks()
    {
        //MORE SECURITY FEATURES COMING SOON HERE
    }

    public function tokenGenerator($separator, $inch, $len)
    {
        $token = array();
        for ($i = 0; $i < $len; $i++) {
            $token[] = Encoding::hexEncode(random_bytes($inch));
        }
        return implode($separator, $token);
    }

    public function fileChecksum($file)
    {
        try {
            return File::checksum($file);
        } catch (\Throwable $e) {
            new ex("secretCrptoError", 6, $e->getMessage());
            return false;
        }
    }

    public function session_setter($session_name, $code, $time = 3600)
    {
        if (!isset($_SESSION[$session_name])) {
            session_regenerate_id();
            $_SESSION[$session_name] = $code;
            $_SESSION[$session_name . '_time'] = time() + $time;
            return $this->filter->strip($_SESSION[$session_name]);
        }
        return $this->filter->strip($_SESSION[$session_name]);
    }

    public function verify_session_setter($code, $session_name, int $expire, $unset = false)
    {
        $current = $this->filter->strip($_SESSION[$session_name]);
        $time = $this->filter->strip($_SESSION[$session_name . '_time']);
        $code = $this->filter->strip($code);
        if (!$this->filter->nothing($code) and !$this->filter->nothing($time) and !$this->filter->nothing($current)) {
            $token_age = abs((int) $time - time());
            if ($expire >= $token_age) {
                if ($code === $current) {
                    // Validated, Done!
                    if ($unset === true) {
                        unset($_SESSION[$session_name]);
                        unset($_SESSION[$session_name . '_time']);
                        session_destroy();
                    }
                    return true;
                }
            } else {
                unset($_SESSION[$session_name]);
                unset($_SESSION[$session_name . '_time']);
                session_destroy();
                return false;
            }
        } else {
            return false;
        }
    }

    public function session_retrieve($session_name, int $expire, $unset = false)
    {
        if (isset($_SESSION[$session_name])) {
            $current = $this->filter->strip($_SESSION[$session_name]);
            $time = $this->filter->strip($_SESSION[$session_name . '_time']);
            if (!$this->filter->nothing($time) and !$this->filter->nothing($current)) {
                $token_age = abs((int) $time - time());
                if ($expire >= $token_age) {
                    // Validated, Done!
                    if ($unset === true) {
                        unset($_SESSION[$session_name]);
                        unset($_SESSION[$session_name . '_time']);
                        session_destroy();
                    }
                    return $_SESSION[$session_name];
                } else {
                    unset($_SESSION[$session_name]);
                    unset($_SESSION[$session_name . '_time']);
                    session_destroy();
                    return false;
                }
            } else {
                return false;
            }
        }
        return false;
    }

    public function session_destroy($session_name)
    {
        unset($_SESSION[$session_name]);
        unset($_SESSION[$session_name . '_time']);
        @session_destroy();
    }
}