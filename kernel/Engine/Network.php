<?php

namespace Manomite\Engine;

use Manomite\Exception\ManomiteException as ex;

class Network
{
    /**
     * Regular expression for matching and validating a MAC address
     * @var string
     */
    private static $valid_mac = "([0-9A-F]{2}[:-]){5}([0-9A-F]{2})";
    /**
     * An array of valid MAC address characters
     * @var array
     */
    private static $mac_address_vals = array(
        "0", "1", "2", "3", "4", "5", "6", "7",
        "8", "9", "A", "B", "C", "D", "E", "F"
     );

    /**
     * @return string generated MAC address
     */
    public static function generateMacAddress()
    {
        $vals = self::$mac_address_vals;
        if (count($vals) >= 1) {
            $mac = array("00"); // set first two digits manually
            while (count($mac) < 6) {
                shuffle($vals);
                $mac[] = $vals[0] . $vals[1];
            }
            $mac = implode(":", $mac);
        }
        return $mac;
    }
    /**
     * Make sure the provided MAC address is in the correct format
     * @param string $mac
     * @return bool true if valid; otherwise false
     */
    public static function validateMacAddress($mac)
    {
        return (bool) preg_match("/^" . self::$valid_mac . "$/i", $mac);
    }
    /**
     * Run the specified command and return it's output
     * @param string $command
     * @return string Output from command that was ran
     * @param string $type
     * @return string type of shell to use
     */
    protected static function runCommand($command, $type)
    {
        $command = \Manomite\Protect\PostFilter::shellFilter($command);
        $type = (new \Manomite\Protect\PostFilter())->strip($type);
        switch ($type) {
            case 'system':
                $shell = system($command);
                break;
            case 'shell_exec':
                $shell = shell_exec($command);
                break;
            case 'passthru':
                $code = passthru($command);
                break;
            default:
                $shell = exec($command);
        }
        return $shell;
    }
    /**
     * Get the android system's current MAC address
     * @param string $interface The name of the interface e.g. eth0
     * @return string|bool Systems current MAC address; otherwise false on error
     */
    public static function getAndroidMacAddress()
    {
        if (strpos(PHP_OS, 'WIN') === 1) {
            $ifconfig = self::runCommand("ip address", 'shell_exec');
            preg_match("/" . self::$valid_mac . "/i", $ifconfig, $ifconfig);
            if (isset($ifconfig[0])) {
                return trim(strtoupper($ifconfig[0]));
            }
            return false;
        }
        return false;
    }
    /**
     * Get the linus system's current MAC address
     * @param string $interface The name of the interface e.g. eth0
     * @return string|bool Systems current MAC address; otherwise false on error
     */
    public static function getLinusMacAddress($interface = 'eth0')
    {
        if (strpos(PHP_OS, 'WIN') === 1) {
            $ifconfig = self::runCommand("ifconfig {$interface}", 'shell_exec');
            preg_match("/" . self::$valid_mac . "/i", $ifconfig, $ifconfig);
            if (isset($ifconfig[0])) {
                return trim(strtoupper($ifconfig[0]));
            }
            return false;
        }
        return false;
    }
    /**
     * Get the windows system's current MAC address
     * @param string $interface The name of the interface e.g. all
     */
    public static function getWinMacAddress($interface = 'all', $position = 'Physical Address')
    {
        if (strpos(PHP_OS, 'WIN') === 0) {
            // Turn on output buffering
            ob_start();
            //Get the ipconfig details using system commond
            self::runCommand("ipconfig /{$interface}", 'system');
            // Capture the output into a variable
            $mycom = ob_get_contents();
            // Clean (erase) the output buffer
            ob_clean();
            $findme = $position;
            //List of positions [Physical Address, IPv4, Description, DHCP Server, Subnet Mask, Default Gateway, Host Name]
            //Search the "Physical" | Find the position of Physical text
            $pmac = strpos($mycom, $findme);
            // Get Physical Address
            if ($mac = substr($mycom, ($pmac + 36), 17)) {
                //Display Mac Address
                return $mac;
            }
            return false;
        }
        return false;
    }
    public static function internetStatus()
    {
        if ($sock = @fsockopen('www.google.com', 80, $num, $error, 5)) {
            return true;
        } else {
            return false;
        }
    }
    public static function getOS()
    {
        return php_uname();
    }
    public function getHost($host)
    {
        $host = strtolower(trim($host));
        $host = ltrim(str_replace("http://", "", str_replace("https://", "", $host)), "www.");
        $count = substr_count($host, '.');
        if ($count === 2) {
            if (strlen(explode('.', $host)[1]) > 3) {
                $host = explode('.', $host, 2)[1];
            }
        } elseif ($count > 2) {
            $host = $this->getHost(explode('.', $host, 2)[1]);
        }
        $host = explode('/', $host);
        return $host[0];
    }
    public static function split_url($url)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $arr);
        return $arr;
    }
    public function get_domain_from_url($url)
    {
        $pieces = parse_url($url);
        $domain = isset($pieces['host']) ? $pieces['host'] : '';
        if (preg_match('/(?P<domain>[a-z0-9][a-z0-9\-]{1,63}\.[a-z\.]{2,6})$/i', $domain, $regs)) {
            return $regs['domain'];
        }
        return false;
    }
    public function long2ip($long)
    {
        if (!function_exists("long2ip")) {
            function long2ip($long)
            {
                // Valid range: 0.0.0.0 -> 255.255.255.255
                if ($long < 0 || $long > 4294967295) {
                    return false;
                }
                $ip = "";
                for ($i=3;$i>=0;$i--) {
                    $ip .= (int)($long / pow(256, $i));
                    $long -= (int)($long / pow(256, $i))*pow(256, $i);
                    if ($i>0) {
                        $ip .= ".";
                    }
                }
                return $ip;
            }
        } else {
            return long2ip($long);
        }
    }

    public function GetCountry()
    {
        $client  = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote  = $_SERVER['REMOTE_ADDR'];

        if (filter_var($client, FILTER_VALIDATE_IP)) {
            $ip = $client;
        } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
            $ip = $forward;
        } else {
            $ip = $remote;
        }
        $query = @unserialize(file_get_contents('http://ip-api.com/php/'.$ip));
        if ($query && $query['status'] == 'success') {
            return $query['country'];
        } else {
            return 'No';
        }
    }
}
