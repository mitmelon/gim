<?php
namespace Manomite\Protect;

use \Egulias\EmailValidator\EmailValidator;
use \Egulias\EmailValidator\Validation\DNSCheckValidation;
use Egulias\EmailValidator\Validation\MultipleValidationWithAnd;
use \Egulias\EmailValidator\Validation\RFCValidation;
use \Spoofchecker;
use \HtaccessFirewall\Host\IP;
use \HtaccessFirewall\HtaccessFirewall;
use \Manomite\Engine\SafeBrowsing\Client as SBClient;
use \Http\Adapter\Guzzle7\Client as GuzzleClient;

class PostFilter extends \HTMLPurifier_AttrDef_URI
{
    public function validate($uri, $config, $context)
    {
        if (preg_match('/^\{[a-zA-Z0-9]+\}$/', $uri)) {
            return true;
        }

        return parent::validate($uri, $config, $context);
    }

    public function strip($value, $onlyTextAndWhiteSpace = false)
    {
        if (empty($value)) {
            return $value;
        }
        $value = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $value);
        if ($onlyTextAndWhiteSpace) {
            $value = preg_replace('/[^A-Za-z0-9\- ]/', '', $value);
        }
        $data = $this->cleanString(strip_tags($value));
        $data = filter_var($data, FILTER_SANITIZE_SPECIAL_CHARS);
        return $data;
    }

    public static function shellFilter($command)
    {
        return escapeshellcmd($command);
    }

    public function inputPost($input, $filter = true)
    {
        if ($input === null) {
            return $input;
        }
        if ($filter) {
            return $this->strip(filter_input(INPUT_POST, $input, FILTER_SANITIZE_SPECIAL_CHARS));
        } else {
            return $p = filter_input(INPUT_POST, $input);
        }
    }

    public function inputGet($input, $filter = true)
    {
        if ($input === null) {
            return $input;
        }
        if ($filter) {
            return $this->strip(filter_input(INPUT_GET, $input, FILTER_SANITIZE_SPECIAL_CHARS));
        } else {
            $p = filter_input(INPUT_POST, $input);
            return $this->filterHtml($p);
        }
    }

    public function inputPostArray($input)
    {
        if ($input === null) {
            return $input;
        }
        return filter_input(INPUT_POST, $input, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY);
    }

    public function inputGetArray($input)
    {
        if ($input === null) {
            return $input;
        }
        return filter_input(INPUT_GET, $input, FILTER_SANITIZE_SPECIAL_CHARS, FILTER_REQUIRE_ARRAY);
    }

    public function filterHtml($html)
    {
        return $this->cleanString($html);
    }
    //Deprecated since from version 1.0.0 and will be removed from later versions
    public function empty()
    {
        return '';
    }

    public function nothing($string)
    {
        return empty($string);
    }

    public function validate_name($name)
    {
        $name = trim($name);
        if (strlen($name) > 8) {
            $name = explode(' ', $name);
            if (count($name) >=  2) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function getUrl()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $link = 'https';
        } else {
            $link = 'http';
        }
        $link .= '://';
        $link .= isset($_SERVER['HTTP_HOST']) ? $this->strip($_SERVER['HTTP_HOST']) : '';
        $link .= isset($_SERVER['REQUEST_URI']) ? $this->strip($_SERVER['HTTP_HOST']) : '';
        return $this->strip($link);
    }

    public function validate_phone($phone)
    {
        $filtered_phone_number = filter_var($phone, FILTER_SANITIZE_NUMBER_INT);
        $remove_dash = str_replace('-', '', $filtered_phone_number);
        $remove_plus = str_replace('+', '', $remove_dash);
        if (strlen($remove_plus) < 12 || strlen($remove_plus) > 13) {
            return false;
        } else {
            return true;
        }
    }

    public function validate_email($email, $forceValidation = true)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            if ($forceValidation) {
                if (extension_loaded('intl')) {
                    $checker = new Spoofchecker();
                    $checker->setChecks($checker::SINGLE_SCRIPT);
                    if ($checker->isSuspicious($email)) {
                        return false;
                    }
                }
                $validator = new EmailValidator();
                $multipleValidations = new MultipleValidationWithAnd([
                    new RFCValidation(),
                    new DNSCheckValidation()
                ]);
                return $validator->isValid($email, $multipleValidations);
            }
            return true;
        } else {
            return false;
        }
    }

    public function firewall($ip)
    {
    }

    public function isBlocked($ip)
    {
        return false;
    }

    public function validate_url($url)
    {
        $bad = false;
        $badlinks = array('..', '../', '/..', chr(0), '<', '>', );
        //logic code below
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $url = filter_var($url, FILTER_SANITIZE_URL);
            foreach ($badlinks as $badlink) {
                if (stripos($url, $badlink) !== false) {
                    $bad = true;
                    break;
                }
            }
            if ($bad === false) {
                //Second layer
                $config = [
                    'api_key' => GOOGLE_API_KEY, // see API Keys section
                    'client_id' => 'Copytraps', // change to your client name
                    'client_version' => '1.0.0', // change to your client version
                ];
                $client = new GuzzleClient();
                $sbc = new SBClient($client, $config);
                $urls_need_check = [
                    $url
                ];
                $result = $sbc->lookup($urls_need_check);
                return $result->isValid($url);
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function is_valid_domain($url)
    {

        $validation = FALSE;
        /*Parse URL*/$urlparts = parse_url(filter_var($url, FILTER_SANITIZE_URL));
        /*Check host exist else path assign to host*/if (!isset($urlparts['host'])) {
            $urlparts['host'] = $urlparts['path'];
        }

        if ($urlparts['host'] != '') {
            /*Add scheme if not found*/if (!isset($urlparts['scheme'])) {
                $urlparts['scheme'] = 'http';
            }
            /*Validation*/if (checkdnsrr($urlparts['host'], 'A') && in_array($urlparts['scheme'], array('http', 'https')) && ip2long($urlparts['host']) === FALSE) {
                $urlparts['host'] = preg_replace('/^www\./', '', $urlparts['host']);
                $url = $urlparts['scheme'] . '://' . $urlparts['host'] . "/";

                if (filter_var($url, FILTER_VALIDATE_URL) !== false && @get_headers($url)) {
                    $validation = TRUE;
                }
            }
        }
        if (!$validation) {
            return false;
        } else {
            return true;
        }

    }

    public function validate_domain($domain_name)
    {
        return (preg_match("/^([a-zd](-*[a-zd])*)(.([a-zd](-*[a-zd])*))*$/i", $domain_name) //valid characters check
            && preg_match("/^.{1,253}$/", $domain_name) //overall length check
            && preg_match("/^[^.]{1,63}(.[^.]{1,63})*$/", $domain_name)); //length of every label
    }

    public function groupFilter(...$variables)
    {
        if (count($variables) < 2) {
            //Only arguments greater than 2 could be filtered
            return false;
        }
        foreach ($variables as $variable) {
            //Lets filter variable value
            $value = $this->strip($variable);
            //Store data
            $save[] = array($value);
        }
        //Covert to 1D Array for easy usage
        return (new \Manomite\Engine\ArrayAdapter)->array_flatten($save);
    }

    public function filterArray(array $array)
    {
        $var = array();
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $var[$this->strip($key)] = $this->strip($value);
            }
            //Covert to 1D Array for easy usage
            return $var;
        }
    }

    private function mbstring_binary_safe_encoding($reset = false)
    {
        static $encodings = array();
        static $overloaded = null;

        if (is_null($overloaded)) {
            $overloaded = function_exists('mb_internal_encoding') && (ini_get('mbstring.func_overload') & 2); // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.mbstring_func_overloadDeprecated
        }

        if (false === $overloaded) {
            return;
        }

        if (!$reset) {
            $encoding = mb_internal_encoding();
            array_push($encodings, $encoding);
            mb_internal_encoding('ISO-8859-1');
        }

        if ($reset && $encodings) {
            $encoding = array_pop($encodings);
            mb_internal_encoding($encoding);
        }
    }
    private function reset_mbstring_encoding()
    {
        $this->mbstring_binary_safe_encoding(true);
    }

    /**
     * Checks to see if a string is utf8 encoded.
     * NOTE: This function checks for 5-Byte sequences, UTF8 has Bytes Sequences with a maximum length of 4.
     * @param string $str The string to be checked
     * @return bool True if $str fits a UTF-8 model, false otherwise.
     */
    private function seems_utf8($str)
    {
        $this->mbstring_binary_safe_encoding();
        $length = strlen($str);
        $this->reset_mbstring_encoding();
        for ($i = 0; $i < $length; $i++) {
            $c = ord($str[$i]);
            if ($c < 0x80) {
                $n = 0;
            } // 0bbbbbbb
            elseif (($c & 0xE0) == 0xC0) {
                $n = 1;
            } // 110bbbbb
            elseif (($c & 0xF0) == 0xE0) {
                $n = 2;
            } // 1110bbbb
            elseif (($c & 0xF8) == 0xF0) {
                $n = 3;
            } // 11110bbb
            elseif (($c & 0xFC) == 0xF8) {
                $n = 4;
            } // 111110bb
            elseif (($c & 0xFE) == 0xFC) {
                $n = 5;
            } // 1111110b
            else {
                return false;
            } // Does not match any model
            for ($j = 0; $j < $n; $j++) { // n bytes matching 10bbbbbb follow ?
                if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Function to clean a string so all characters with accents are turned into ASCII characters. EG: ‡ = a
     *
     * @param str $string
     * @return str
     */
    private function cleanString(string $string)
    {
        if (!preg_match('/[\x80-\xff]/', $string)) {
            return $string;
        }

        if ($this->seems_utf8($string)) {
            $chars = array(
                // Decompositions for Latin-1 Supplement.
                'ª' => 'a',
                'º' => 'o',
                'À' => 'A',
                'Á' => 'A',
                'Â' => 'A',
                'Ã' => 'A',
                'Ä' => 'A',
                'Å' => 'A',
                'Æ' => 'AE',
                'Ç' => 'C',
                'È' => 'E',
                'É' => 'E',
                'Ê' => 'E',
                'Ë' => 'E',
                'Ì' => 'I',
                'Í' => 'I',
                'Î' => 'I',
                'Ï' => 'I',
                'Ð' => 'D',
                'Ñ' => 'N',
                'Ò' => 'O',
                'Ó' => 'O',
                'Ô' => 'O',
                'Õ' => 'O',
                'Ö' => 'O',
                'Ù' => 'U',
                'Ú' => 'U',
                'Û' => 'U',
                'Ü' => 'U',
                'Ý' => 'Y',
                'Þ' => 'TH',
                'ß' => 's',
                'à' => 'a',
                'á' => 'a',
                'â' => 'a',
                'ã' => 'a',
                'ä' => 'a',
                'å' => 'a',
                'æ' => 'ae',
                'ç' => 'c',
                'è' => 'e',
                'é' => 'e',
                'ê' => 'e',
                'ë' => 'e',
                'ì' => 'i',
                'í' => 'i',
                'î' => 'i',
                'ï' => 'i',
                'ð' => 'd',
                'ñ' => 'n',
                'ò' => 'o',
                'ó' => 'o',
                'ô' => 'o',
                'õ' => 'o',
                'ö' => 'o',
                'ø' => 'o',
                'ù' => 'u',
                'ú' => 'u',
                'û' => 'u',
                'ü' => 'u',
                'ý' => 'y',
                'þ' => 'th',
                'ÿ' => 'y',
                'Ø' => 'O',
                // Decompositions for Latin Extended-A.
                'Ā' => 'A',
                'ā' => 'a',
                'Ă' => 'A',
                'ă' => 'a',
                'Ą' => 'A',
                'ą' => 'a',
                'Ć' => 'C',
                'ć' => 'c',
                'Ĉ' => 'C',
                'ĉ' => 'c',
                'Ċ' => 'C',
                'ċ' => 'c',
                'Č' => 'C',
                'č' => 'c',
                'Ď' => 'D',
                'ď' => 'd',
                'Đ' => 'D',
                'đ' => 'd',
                'Ē' => 'E',
                'ē' => 'e',
                'Ĕ' => 'E',
                'ĕ' => 'e',
                'Ė' => 'E',
                'ė' => 'e',
                'Ę' => 'E',
                'ę' => 'e',
                'Ě' => 'E',
                'ě' => 'e',
                'Ĝ' => 'G',
                'ĝ' => 'g',
                'Ğ' => 'G',
                'ğ' => 'g',
                'Ġ' => 'G',
                'ġ' => 'g',
                'Ģ' => 'G',
                'ģ' => 'g',
                'Ĥ' => 'H',
                'ĥ' => 'h',
                'Ħ' => 'H',
                'ħ' => 'h',
                'Ĩ' => 'I',
                'ĩ' => 'i',
                'Ī' => 'I',
                'ī' => 'i',
                'Ĭ' => 'I',
                'ĭ' => 'i',
                'Į' => 'I',
                'į' => 'i',
                'İ' => 'I',
                'ı' => 'i',
                'Ĳ' => 'IJ',
                'ĳ' => 'ij',
                'Ĵ' => 'J',
                'ĵ' => 'j',
                'Ķ' => 'K',
                'ķ' => 'k',
                'ĸ' => 'k',
                'Ĺ' => 'L',
                'ĺ' => 'l',
                'Ļ' => 'L',
                'ļ' => 'l',
                'Ľ' => 'L',
                'ľ' => 'l',
                'Ŀ' => 'L',
                'ŀ' => 'l',
                'Ł' => 'L',
                'ł' => 'l',
                'Ń' => 'N',
                'ń' => 'n',
                'Ņ' => 'N',
                'ņ' => 'n',
                'Ň' => 'N',
                'ň' => 'n',
                'ŉ' => 'n',
                'Ŋ' => 'N',
                'ŋ' => 'n',
                'Ō' => 'O',
                'ō' => 'o',
                'Ŏ' => 'O',
                'ŏ' => 'o',
                'Ő' => 'O',
                'ő' => 'o',
                'Œ' => 'OE',
                'œ' => 'oe',
                'Ŕ' => 'R',
                'ŕ' => 'r',
                'Ŗ' => 'R',
                'ŗ' => 'r',
                'Ř' => 'R',
                'ř' => 'r',
                'Ś' => 'S',
                'ś' => 's',
                'Ŝ' => 'S',
                'ŝ' => 's',
                'Ş' => 'S',
                'ş' => 's',
                'Š' => 'S',
                'š' => 's',
                'Ţ' => 'T',
                'ţ' => 't',
                'Ť' => 'T',
                'ť' => 't',
                'Ŧ' => 'T',
                'ŧ' => 't',
                'Ũ' => 'U',
                'ũ' => 'u',
                'Ū' => 'U',
                'ū' => 'u',
                'Ŭ' => 'U',
                'ŭ' => 'u',
                'Ů' => 'U',
                'ů' => 'u',
                'Ű' => 'U',
                'ű' => 'u',
                'Ų' => 'U',
                'ų' => 'u',
                'Ŵ' => 'W',
                'ŵ' => 'w',
                'Ŷ' => 'Y',
                'ŷ' => 'y',
                'Ÿ' => 'Y',
                'Ź' => 'Z',
                'ź' => 'z',
                'Ż' => 'Z',
                'ż' => 'z',
                'Ž' => 'Z',
                'ž' => 'z',
                'ſ' => 's',
                // Decompositions for Latin Extended-B.
                'Ș' => 'S',
                'ș' => 's',
                'Ț' => 'T',
                'ț' => 't',
                // Euro sign.
                '€' => 'E',
                // GBP (Pound) sign.
                '£' => '',
                // Vowels with diacritic (Vietnamese).
                // Unmarked.
                'Ơ' => 'O',
                'ơ' => 'o',
                'Ư' => 'U',
                'ư' => 'u',
                // Grave accent.
                'Ầ' => 'A',
                'ầ' => 'a',
                'Ằ' => 'A',
                'ằ' => 'a',
                'Ề' => 'E',
                'ề' => 'e',
                'Ồ' => 'O',
                'ồ' => 'o',
                'Ờ' => 'O',
                'ờ' => 'o',
                'Ừ' => 'U',
                'ừ' => 'u',
                'Ỳ' => 'Y',
                'ỳ' => 'y',
                // Hook.
                'Ả' => 'A',
                'ả' => 'a',
                'Ẩ' => 'A',
                'ẩ' => 'a',
                'Ẳ' => 'A',
                'ẳ' => 'a',
                'Ẻ' => 'E',
                'ẻ' => 'e',
                'Ể' => 'E',
                'ể' => 'e',
                'Ỉ' => 'I',
                'ỉ' => 'i',
                'Ỏ' => 'O',
                'ỏ' => 'o',
                'Ổ' => 'O',
                'ổ' => 'o',
                'Ở' => 'O',
                'ở' => 'o',
                'Ủ' => 'U',
                'ủ' => 'u',
                'Ử' => 'U',
                'ử' => 'u',
                'Ỷ' => 'Y',
                'ỷ' => 'y',
                // Tilde.
                'Ẫ' => 'A',
                'ẫ' => 'a',
                'Ẵ' => 'A',
                'ẵ' => 'a',
                'Ẽ' => 'E',
                'ẽ' => 'e',
                'Ễ' => 'E',
                'ễ' => 'e',
                'Ỗ' => 'O',
                'ỗ' => 'o',
                'Ỡ' => 'O',
                'ỡ' => 'o',
                'Ữ' => 'U',
                'ữ' => 'u',
                'Ỹ' => 'Y',
                'ỹ' => 'y',
                // Acute accent.
                'Ấ' => 'A',
                'ấ' => 'a',
                'Ắ' => 'A',
                'ắ' => 'a',
                'Ế' => 'E',
                'ế' => 'e',
                'Ố' => 'O',
                'ố' => 'o',
                'Ớ' => 'O',
                'ớ' => 'o',
                'Ứ' => 'U',
                'ứ' => 'u',
                // Dot below.
                'Ạ' => 'A',
                'ạ' => 'a',
                'Ậ' => 'A',
                'ậ' => 'a',
                'Ặ' => 'A',
                'ặ' => 'a',
                'Ẹ' => 'E',
                'ẹ' => 'e',
                'Ệ' => 'E',
                'ệ' => 'e',
                'Ị' => 'I',
                'ị' => 'i',
                'Ọ' => 'O',
                'ọ' => 'o',
                'Ộ' => 'O',
                'ộ' => 'o',
                'Ợ' => 'O',
                'ợ' => 'o',
                'Ụ' => 'U',
                'ụ' => 'u',
                'Ự' => 'U',
                'ự' => 'u',
                'Ỵ' => 'Y',
                'ỵ' => 'y',
                // Vowels with diacritic (Chinese, Hanyu Pinyin).
                'ɑ' => 'a',
                // Macron.
                'Ǖ' => 'U',
                'ǖ' => 'u',
                // Acute accent.
                'Ǘ' => 'U',
                'ǘ' => 'u',
                // Caron.
                'Ǎ' => 'A',
                'ǎ' => 'a',
                'Ǐ' => 'I',
                'ǐ' => 'i',
                'Ǒ' => 'O',
                'ǒ' => 'o',
                'Ǔ' => 'U',
                'ǔ' => 'u',
                'Ǚ' => 'U',
                'ǚ' => 'u',
                // Grave accent.
                'Ǜ' => 'U',
                'ǜ' => 'u',
            );

            $string = strtr($string, $chars);
        } else {
            $chars = array();
            // Assume ISO-8859-1 if not UTF-8.
            $chars['in'] = "\x80\x83\x8a\x8e\x9a\x9e"
                . "\x9f\xa2\xa5\xb5\xc0\xc1\xc2"
                . "\xc3\xc4\xc5\xc7\xc8\xc9\xca"
                . "\xcb\xcc\xcd\xce\xcf\xd1\xd2"
                . "\xd3\xd4\xd5\xd6\xd8\xd9\xda"
                . "\xdb\xdc\xdd\xe0\xe1\xe2\xe3"
                . "\xe4\xe5\xe7\xe8\xe9\xea\xeb"
                . "\xec\xed\xee\xef\xf1\xf2\xf3"
                . "\xf4\xf5\xf6\xf8\xf9\xfa\xfb"
                . "\xfc\xfd\xff";

            $chars['out'] = 'EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy';

            $string = strtr($string, $chars['in'], $chars['out']);
            $double_chars = array();
            $double_chars['in'] = array("\x8c", "\x9c", "\xc6", "\xd0", "\xde", "\xdf", "\xe6", "\xf0", "\xfe");
            $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
            $string = str_replace($double_chars['in'], $double_chars['out'], $string);
        }
        //Additional cleaner
        return $string;
    }

    /**
     * Sanitizes a filename, replacing whitespace with dashes.
     *
     * Removes special characters that are illegal in filenames on certain
     * operating systems and special characters requiring special escaping
     * to manipulate at the command line. Replaces spaces and consecutive
     * dashes with a single dash. Trims period, dash and underscore from beginning
     * and end of filename. It is not guaranteed that this function will return a
     * filename that is allowed to be uploaded.
     *
     *
     * @param string $filename The filename to be sanitized.
     * @return string The sanitized filename.
     */
    public function sanitize_file_name(string $filename)
    {
        $filename = $this->strip($filename);

        $special_chars = array('?', '[', ']', '/', '\\', '=', '<', '>', ':', ';', ',', "'", '"', '&', '$', '#', '*', '(', ')', '|', '~', '`', '!', '{', '}', '%', '+', '’', '«', '»', '”', '“', chr(0));

        if (!$this->seems_utf8($filename)) {
            $_ext = pathinfo($filename, PATHINFO_EXTENSION);
            $_name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = $this->sanitize_title_with_dashes($_name) . '.' . $_ext;
        }

        $filename = str_replace($special_chars, '', $filename);
        $filename = str_replace(array('%20', '+'), '-', $filename);
        $filename = preg_replace('/[\r\n\t -]+/', '-', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = preg_replace(array('/ +/', '/-+/'), '-', $filename);
        $filename = preg_replace(array('/-*\.-*/', '/\.{2,}/'), '.', $filename);
        // cut to 255 characters
        $length = 255;
        $filename = extension_loaded('mbstring') ? mb_strcut($filename, 0, $length, mb_detect_encoding($filename)) : substr($filename, 0, $length);
        $filename = trim($filename, '.-_');

        return $filename;
    }

    public function utf8_uri_encode($utf8_string, $length = 0)
    {
        $unicode = '';
        $values = array();
        $num_octets = 1;
        $unicode_length = 0;

        $this->mbstring_binary_safe_encoding();
        $string_length = strlen($utf8_string);
        $this->reset_mbstring_encoding();

        for ($i = 0; $i < $string_length; $i++) {

            $value = ord($utf8_string[$i]);

            if ($value < 128) {
                if ($length && ($unicode_length >= $length)) {
                    break;
                }
                $unicode .= chr($value);
                $unicode_length++;
            } else {
                if (count($values) == 0) {
                    if ($value < 224) {
                        $num_octets = 2;
                    } elseif ($value < 240) {
                        $num_octets = 3;
                    } else {
                        $num_octets = 4;
                    }
                }

                $values[] = $value;

                if ($length && ($unicode_length + ($num_octets * 3)) > $length) {
                    break;
                }
                if (count($values) == $num_octets) {
                    for ($j = 0; $j < $num_octets; $j++) {
                        $unicode .= '%' . dechex($values[$j]);
                    }

                    $unicode_length += $num_octets * 3;

                    $values = array();
                    $num_octets = 1;
                }
            }
        }

        return $unicode;
    }

    public function block($ip, $htaccessPath = __DIR__ . '/../../.htaccess')
    {
        $firewall = new HtaccessFirewall($htaccessPath);
        $host = IP::fromString($ip);
        $firewall->deny($host);
    }

    public function unblock($ip, $htaccessPath = __DIR__ . '/../../.htaccess')
    {
        $firewall = new HtaccessFirewall($htaccessPath);
        $host = IP::fromString($ip);
        $firewall->undeny($host);
    }
}