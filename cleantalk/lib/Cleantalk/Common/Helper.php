<?php

namespace Cleantalk\Common;

use Cleantalk\Variables\Server;

/**
 * CleanTalk Helper class.
 * Compatible with any CMS.
 *
 * @package       PHP Antispam by CleanTalk
 * @subpackage    Helper
 * @Version       3.2
 * @author        Cleantalk team (welcome@cleantalk.org)
 * @copyright (C) 2014 CleanTalk team (http://cleantalk.org)
 * @license       GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 * @see           https://github.com/CleanTalk/php-antispam
 */
class Helper{

	use \Cleantalk\Templates\Singleton;

	/**
	 * Default user agent for HTTP requests
	 */
	const DEFAULT_USER_AGENT = 'Cleantalk-Helper/3.2';

	/**
	 * @var array Set of private networks IPv4 and IPv6
	 */
	public static $private_networks = array(
		'v4' => array(
			'10.0.0.0/8',
			'100.64.0.0/10',
			'172.16.0.0/12',
			'192.168.0.0/16',
			'127.0.0.1/32',
		),
		'v6' => array(
			'0:0:0:0:0:0:0:1/128', // localhost
			'0:0:0:0:0:0:a:1/128', // ::ffff:127.0.0.1
		),
	);

	/**
	 * @var array Set of CleanTalk servers
	 */
	public static $cleantalks_servers = array(
		// MODERATE
		'moderate1.cleantalk.org' => '162.243.144.175',
		'moderate2.cleantalk.org' => '159.203.121.181',
		'moderate3.cleantalk.org' => '88.198.153.60',
		'moderate4.cleantalk.org' => '159.69.51.30',
		'moderate5.cleantalk.org' => '95.216.200.119',
		'moderate6.cleantalk.org' => '138.68.234.8',
		// APIX
		'apix1.cleantalk.org' => '35.158.52.161',
		'apix2.cleantalk.org' => '18.206.49.217',
		'apix3.cleantalk.org' => '3.18.23.246',
		//ns
		'netserv2.cleantalk.org' => '178.63.60.214',
		'netserv3.cleantalk.org' => '188.40.14.173',
	);

	/**
	 * Getting arrays of IP (REMOTE_ADDR, X-Forwarded-For, X-Real-Ip, Cf_Connecting_Ip)
	 *
	 * @param array $ip_types Type of IP you want to receive
	 * @param bool  $v4_only
	 * @return array|mixed|null
	 */
	static public function ip__get($ip_types = array('real', 'remote_addr', 'x_forwarded_for', 'x_real_ip', 'cloud_flare'), $v4_only = true)
	{
		$ips = array_flip($ip_types); // Result array with IPs
		$headers = self::http__get_headers();

		// REMOTE_ADDR
		if(isset($ips['remote_addr'])){
			$ip_type = self::ip__validate(Server::get( 'REMOTE_ADDR' ));
			if($ip_type){
				$ips['remote_addr'] = $ip_type == 'v6' ? self::ip__v6_normalize(Server::get( 'REMOTE_ADDR' )) : Server::get( 'REMOTE_ADDR' );
			}
		}

		// X-Forwarded-For
		if(isset($ips['x_forwarded_for'])){
			if(isset($headers['X-Forwarded-For'])){
				$tmp = explode(",", trim($headers['X-Forwarded-For']));
				$tmp = trim($tmp[0]);
				$ip_type = self::ip__validate($tmp);
				if($ip_type){
					$ips['x_forwarded_for'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;
				}
			}
		}

		// X-Real-Ip
		if(isset($ips['x_real_ip'])){
			if(isset($headers['X-Real-Ip'])){
				$tmp = explode(",", trim($headers['X-Real-Ip']));
				$tmp = trim($tmp[0]);
				$ip_type = self::ip__validate($tmp);
				if($ip_type){
					$ips['x_forwarded_for'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;
				}
			}
		}

		// Cloud Flare
		if(isset($ips['cloud_flare'])){
			if(isset($headers['CF-Connecting-IP'], $headers['CF-IPCountry'], $headers['CF-RAY']) || isset($headers['Cf-Connecting-Ip'], $headers['Cf-Ipcountry'], $headers['Cf-Ray'])){
				$tmp = isset($headers['CF-Connecting-IP']) ? $headers['CF-Connecting-IP'] : $headers['Cf-Connecting-Ip'];
				$tmp = strpos($tmp, ',') !== false ? explode(',', $tmp) : (array)$tmp;
				$ip_type = self::ip__validate(trim($tmp[0]));
				if($ip_type){
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize(trim($tmp[0])) : trim($tmp[0]);
				}
			}
		}

		// Getting real IP from REMOTE_ADDR or Cf_Connecting_Ip if set or from (X-Forwarded-For, X-Real-Ip) if REMOTE_ADDR is local.
		if(isset($ips['real'])){

			// Detect IP type
			$ip_type = self::ip__validate(Server::get( 'REMOTE_ADDR' ) );
			if($ip_type)
				$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize(Server::get( 'REMOTE_ADDR' )) : Server::get( 'REMOTE_ADDR' );

			// Cloud Flare
			if(isset($headers['CF-Connecting-IP'], $headers['CF-IPCountry'], $headers['CF-RAY']) || isset($headers['Cf-Connecting-Ip'], $headers['Cf-Ipcountry'], $headers['Cf-Ray'])){
				$tmp = isset($headers['CF-Connecting-IP']) ? $headers['CF-Connecting-IP'] : $headers['Cf-Connecting-Ip'];
				$tmp = strpos($tmp, ',') !== false ? explode(',', $tmp) : (array)$tmp;
				$ip_type = self::ip__validate(trim($tmp[0]));
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize(trim($tmp[0])) : trim($tmp[0]);

				// Sucury
			}elseif(isset($headers['X-Sucuri-Clientip'], $headers['X-Sucuri-Country'])){
				$ip_type = self::ip__validate($headers['X-Sucuri-Clientip']);
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($headers['X-Sucuri-Clientip']) : $headers['X-Sucuri-Clientip'];

				// OVH
			}elseif(isset($headers['X-Cdn-Any-Ip'], $headers['Remote-Ip'])){
				$ip_type = self::ip__validate($headers['X-Cdn-Any-Ip']);
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($headers['X-Cdn-Any-Ip']) : $headers['X-Cdn-Any-Ip'];

				// Incapsula proxy
			}elseif(isset($headers['Incap-Client-Ip'])){
				$ip_type = self::ip__validate($headers['Incap-Client-Ip']);
				if($ip_type)
					$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($headers['Incap-Client-Ip']) : $headers['Incap-Client-Ip'];
			}

			// Is private network
            if($ip_type === false || (
                    $ip_type &&
                    (
                        self::ip__is_private_network($ips['real'], $ip_type) ||
                        (
                            $ip_type === self::ip__validate(filter_input(INPUT_SERVER, 'SERVER_ADDR')) &&
                            self::ip__mask_match(
                                $ips['real'],
                                filter_input(INPUT_SERVER, 'SERVER_ADDR') . '/24',
                                $ip_type)
                        )
                    )
                )
            )
            {
				// X-Forwarded-For
				if(isset($headers['X-Forwarded-For'])){
					$tmp = explode(',', trim($headers['X-Forwarded-For']));
					$tmp = trim($tmp[0]);
					$ip_type = self::ip__validate($tmp);
					if($ip_type)
						$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;

					// X-Real-Ip
				}elseif(isset($headers['X-Real-Ip'])){
					$tmp = explode(',', trim($headers['X-Real-Ip']));
					$tmp = trim($tmp[0]);
					$ip_type = self::ip__validate($tmp);
					if($ip_type)
						$ips['real'] = $ip_type == 'v6' ? self::ip__v6_normalize($tmp) : $tmp;
				}
			}
		}

		// Validating IPs
		$result = array();
		foreach($ips as $key => $ip){
			$ip_version = self::ip__validate($ip);
			if($ip && (($v4_only && $ip_version == 'v4') || !$v4_only)){
				$result[$key] = $ip;
			}
		}

		$result = array_unique($result);
		return count($result) > 1
			? $result
			: (reset($result) !== false
				? reset($result)
				: null);
	}

	/**
	 * Checks if the IP is in private range
	 *
	 * @param string $ip
	 * @param string $ip_type
	 *
	 * @return bool
	 */
	static function ip__is_private_network($ip, $ip_type = 'v4')
	{
		return self::ip__mask_match($ip, self::$private_networks[$ip_type], $ip_type);
	}

	/**
	 * Check if the IP belong to mask.  Recursive.
	 * Octet by octet for IPv4
	 * Hextet by hextet for IPv6
	 *
	 * @param string $ip
	 * @param string $cidr       work to compare with
	 * @param string $ip_type    IPv6 or IPv4
	 * @param int    $xtet_count Recursive counter. Determs current part of address to check.
	 *
	 * @return bool
	 */
	static public function ip__mask_match($ip, $cidr, $ip_type = 'v4', $xtet_count = 0)
	{
		if(is_array($cidr)){
			foreach($cidr as $curr_mask){
				if(self::ip__mask_match($ip, $curr_mask, $ip_type)){
					return true;
				}
			}
			unset($curr_mask);
			return false;
		}

		$xtet_base = ($ip_type == 'v4') ? 8 : 16;

		// Calculate mask
		$exploded = explode('/', $cidr);
		$net_ip = $exploded[0];
		$mask = $exploded[1];

		// Exit condition
		$xtet_end = ceil($mask / $xtet_base);
		if($xtet_count == $xtet_end)
			return true;

		// Lenght of bits for comparsion
		$mask = $mask - $xtet_base * $xtet_count >= $xtet_base ? $xtet_base : $mask - $xtet_base * $xtet_count;

		// Explode by octets/hextets from IP and Net
		$net_ip_xtets = explode($ip_type == 'v4' ? '.' : ':', $net_ip);
		$ip_xtets = explode($ip_type == 'v4' ? '.' : ':', $ip);

		// Standartizing. Getting current octets/hextets. Adding leading zeros.
		$net_xtet = str_pad(
            decbin(
                ($ip_type === 'v4' && (int)$net_ip_xtets[$xtet_count]) ? $net_ip_xtets[$xtet_count] : @hexdec(
                    $net_ip_xtets[$xtet_count]
                )
            ),
            $xtet_base,
            0,
            STR_PAD_LEFT
        );
        $ip_xtet  = str_pad(
            decbin(
                ($ip_type === 'v4' && (int)$ip_xtets[$xtet_count]) ? $ip_xtets[$xtet_count] : @hexdec(
                    $ip_xtets[$xtet_count]
                )
            ),
            $xtet_base,
            0,
            STR_PAD_LEFT
        );

		// Comparing bit by bit
		for($i = 0, $result = true; $mask != 0; $mask--, $i++){
			if($ip_xtet[$i] != $net_xtet[$i]){
				$result = false;
				break;
			}
		}

		// Recursing. Moving to next octet/hextet.
		if($result)
			$result = self::ip__mask_match($ip, $cidr, $ip_type, $xtet_count + 1);

		return $result;

	}

	/**
	 * Converts long mask like 4294967295 to number like 32
	 *
	 * @param int $long_mask
	 *
	 * @return int
	 */
	static function ip__mask__long_to_number($long_mask)
	{
		$num_mask = strpos((string)decbin($long_mask), '0');
		return $num_mask === false ? 32 : $num_mask;
	}

	/**
	 * Validating IPv4, IPv6
	 *
	 * @param string $ip
	 *
	 * @return string|bool
	 */
	static public function ip__validate($ip)
	{
		if(!$ip) return false; // NULL || FALSE || '' || so on...
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $ip != '0.0.0.0') return 'v4';  // IPv4
		if(filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && self::ip__v6_reduce($ip) != '0::0') return 'v6';  // IPv6
		return false; // Unknown
	}

	/**
	 * Expand IPv6
	 *
	 * @param string $ip
	 *
	 * @return string IPv6
	 */
	static public function ip__v6_normalize($ip)
	{
		$ip = trim($ip);
		// Searching for ::ffff:xx.xx.xx.xx patterns and turn it to IPv6
		if(preg_match('/^::ffff:([0-9]{1,3}\.?){4}$/', $ip)){
			$ip = dechex(sprintf("%u", ip2long(substr($ip, 7))));
			$ip = '0:0:0:0:0:0:' . (strlen($ip) > 4 ? substr('abcde', 0, -4) : '0') . ':' . substr($ip, -4, 4);
			// Normalizing hextets number
		}elseif(strpos($ip, '::') !== false){
			$ip = str_replace('::', str_repeat(':0', 8 - substr_count($ip, ':')) . ':', $ip);
			$ip = strpos($ip, ':') === 0 ? '0' . $ip : $ip;
			$ip = strpos(strrev($ip), ':') === 0 ? $ip . '0' : $ip;
		}
		// Simplifyng hextets
		if(preg_match('/:0(?=[a-z0-9]+)/', $ip)){
			$ip = preg_replace('/:0(?=[a-z0-9]+)/', ':', strtolower($ip));
			$ip = self::ip__v6_normalize($ip);
		}
		return $ip;
	}

	/**
	 * Reduce IPv6
	 *
	 * @param string $ip
	 *
	 * @return string IPv6
	 */
	static public function ip__v6_reduce($ip)
	{
		if(strpos($ip, ':') !== false){
			$ip = preg_replace('/:0{1,4}/', ':', $ip);
			$ip = preg_replace('/:{2,}/', '::', $ip);
			$ip = strpos($ip, '0') === 0 ? substr($ip, 1) : $ip;
		}
		return $ip;
	}

	/**
	 * Get URL form IP. Check if it's belong to cleantalk.
	 *
	 * @param string $ip
	 *
	 * @return false|int|string
	 */
	static public function ip__is_cleantalks($ip)
	{
		if(self::ip__validate($ip)){
			$url = array_search($ip, self::$cleantalks_servers);
			return $url
				? true
				: false;
		}else
			return false;
	}

	/**
	 * Get URL form IP. Check if it's belong to cleantalk.
	 *
	 * @param $ip
	 *
	 * @return false|int|string
	 */
	static public function ip__resolve__cleantalks($ip)
	{
		if(self::ip__validate($ip)){
			$url = array_search($ip, self::$cleantalks_servers);
			return $url
				? $url
				: self::ip__resolve($ip);
		}else
			return $ip;
	}

	/**
	 * Get URL form IP
	 *
	 * @param $ip
	 *
	 * @return string
	 */
	static public function ip__resolve($ip)
	{
		if(self::ip__validate($ip)){
			$url = gethostbyaddr($ip);
			if($url)
				return $url;
		}
		return $ip;
	}

	/**
	 * Resolve DNS to IP
	 *
	 * @param      $host
	 * @param bool $out
	 *
	 * @return bool
	 */
	static public function dns__resolve($host, $out = false)
	{

		// Get DNS records about URL
		if(function_exists('dns_get_record')){
			$records = dns_get_record($host, DNS_A);
			if($records !== false){
				$out = $records[0]['ip'];
			}
		}

		// Another try if first failed
		if(!$out && function_exists('gethostbynamel')){
			$records = gethostbynamel($host);
			if($records !== false){
				$out = $records[0];
			}
		}

		return $out;

	}

	static public function http__user_agent(){
		return defined( 'CLEANTALK_USER_AGENT' ) ? CLEANTALK_USER_AGENT : static::DEFAULT_USER_AGENT;
	}

	/**
	 * Function sends raw http request
	 *
	 * May use 4 presets(combining possible):
	 * get_code - getting only HTTP response code
	 * async    - async requests
	 * get      - GET-request
	 * ssl      - use SSL
	 *
	 * @param string       $url     URL
	 * @param array        $data    POST|GET indexed array with data to send
	 * @param string|array $presets String or Array with presets: get_code, async, get, ssl, dont_split_to_array
	 * @param array        $opts    Optional option for CURL connection
	 *
	 * @return array|bool (array || array('error' => true))
	 */
	static public function http__request($url, $data = array(), $presets = null, $opts = array())
	{
		// For debug purposes
		if( defined( 'CLEANTALK_DEBUG' ) && CLEANTALK_DEBUG ){
			global $apbct_debug;
			$apbct_debug['data'] = $data;
		}

		// Preparing presets
		$presets = is_array($presets) ? $presets : explode(' ', $presets);
		$curl_only = in_array( 'async', $presets ) ||
		             in_array( 'dont_follow_redirects', $presets ) ||
		             in_array( 'ssl', $presets ) ||
		             in_array( 'split_to_array', $presets )
			? true : false;

		if(function_exists('curl_init')){

			$ch = curl_init();

			// Set data if it's not empty
			if(!empty($data)){
				// If $data scalar converting it to array
				$opts[CURLOPT_POSTFIELDS] = $data;
			}
			$opts = static::array_merge__save_numeric_keys(
				array(
					CURLOPT_URL => $url,
					CURLOPT_TIMEOUT => 5,
					CURLOPT_FORBID_REUSE => true,
					CURLOPT_POST => true,
					CURLOPT_SSL_VERIFYPEER => false,
					CURLOPT_SSL_VERIFYHOST => 0,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS => 5,
					CURLOPT_USERAGENT => static::http__user_agent() . '; ' . ( ! empty( Server::get( 'SERVER_NAME' ) ) ? Server::get( 'SERVER_NAME' ) : 'UNKNOWN_HOST' ),
					CURLOPT_RETURNTRANSFER => true, // receive server response ...
					CURLOPT_HTTPHEADER => array('Expect:'), // Fix for large data and old servers http://php.net/manual/ru/function.curl-setopt.php#82418
				),
				$opts
			);

			foreach($presets as $preset){

				switch($preset){

					// Do not follow redirects
					case 'dont_follow_redirects':
						$opts[CURLOPT_FOLLOWLOCATION] = false;
						$opts[CURLOPT_MAXREDIRS] = 0;
						break;

					// Get headers only
					case 'get_code':
						$opts[CURLOPT_HEADER] = true;
						$opts[CURLOPT_NOBODY] = true;
						break;

					// Make a request, don't wait for an answer
					case 'async':
						$opts[CURLOPT_CONNECTTIMEOUT_MS] = 1000;
						$opts[CURLOPT_TIMEOUT_MS] = 500;
						break;

					case 'get':
                        try {
                            $data = is_string($data) ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : $data;
                        } catch (\JsonException) {
                            $data = false;
                        }
                        if (is_array($data)) {
                            $opts[ CURLOPT_URL ] .= $data ? '?' . str_replace( "&amp;", "&", http_build_query( $data ) ) : '';
                            $opts[CURLOPT_POST] = false;
                            $opts[CURLOPT_POSTFIELDS] = null;
                        } else {
                            return array('error' => 'CURL. Preparing GET request: data param is not valid.');
                        }
						break;
					case 'ssl':
						$opts[CURLOPT_SSL_VERIFYPEER] = true;
						$opts[CURLOPT_SSL_VERIFYHOST] = 2;
						if(defined('CLEANTALK_CASERT_PATH') && CLEANTALK_CASERT_PATH)
							$opts[CURLOPT_CAINFO] = CLEANTALK_CASERT_PATH;
						break;

                    case 'get_file':
                    case 'github_api':
                        $opts[CURLOPT_CUSTOMREQUEST] = 'GET';
                        $opts[CURLOPT_POST] = false;
                        $opts[CURLOPT_POSTFIELDS] = null;
                        $opts[CURLOPT_HEADER] = false;
                        break;


					default:

						break;
				}
			}

			curl_setopt_array($ch, $opts);
			$result = curl_exec($ch);

			// RETURN if async request
			if(in_array('async', $presets))
				return true;

			if($result){

				// Split to array by lines if such preset given
				if( strpos( $result, PHP_EOL ) !== false && in_array( 'split_to_array', $presets ) )
					$result = explode(PHP_EOL, $result);

				// Get code crossPHP method
				if(in_array('get_code', $presets)){
					$curl_info = curl_getinfo($ch);
					$result = $curl_info['http_code'];
				}

				$out = $result;

			}else
				$out = array('error' => curl_error($ch));

			curl_close($ch);

		// Curl not installed. Trying file_get_contents()
		}elseif( ini_get( 'allow_url_fopen' ) && ! $curl_only ){

			// Trying to get code via get_headers()
			if( in_array( 'get_code', $presets ) ){
				$headers = get_headers( $url );
				$result  = (int) preg_replace( '/.*(\d{3}).*/', '$1', $headers[0] );

			// Making common request
			} else {
                $method = in_array( 'get', $presets ) ? 'GET' : 'POST';
                if ($method === 'GET') {
                    try {
                        $data = is_string($data) ? json_decode($data, true, 512, JSON_THROW_ON_ERROR) : $data;
                        $data = str_replace( "&amp;", "&", http_build_query( $data ) );
                    } catch (\JsonException) {
                        $data = false;
                    }
                }

                if (is_string($data)) {
                    $opts    = array(
                        'http' => array(
                            'method'  => $method,
                            'timeout' => 5,
                            'content' => $data,
                        ),
                    );
                    $context = stream_context_create( $opts );
                    $result  = @file_get_contents( $url, 0, $context );
                } else {
                    return array('error' => 'No CURL. Preparing GET request: data param is not valid.');
                }
			}

			$out = $result === false
				? array('error' => 'FAILED_TO_USE_FILE_GET_CONTENTS')
				: $result;

		}else
			$out = array('error' => 'CURL not installed and allow_url_fopen is disabled');

		return $out;
	}

    /**
     * Wrapper for http_request
     * Requesting data via HTTP request with GET method
     *
     * @param string $url
     *
     * @return array|mixed|string
     */
    static public function http__request__get_content( $url ){
        return static::http__request( $url, array(), 'get dont_split_to_array');
    }

	/**
	 * Gets every HTTP_ headers from $_SERVER
	 *
	 * If Apache web server is missing then making
	 * Patch for apache_request_headers()
	 *
	 * returns array
	 */
	static public function http__get_headers(){

		$headers = array();
        foreach($_SERVER as $key => $val){
            if(preg_match('/\AHTTP_/', $key)){
                $server_key = preg_replace('/\AHTTP_/', '', $key);
                if ( is_string($server_key) ) {
                    $key_parts = explode('_', $server_key);
                    if( count($key_parts) > 0 && strlen($server_key) > 2 ) {
                        foreach($key_parts as $part_index => $part){
                            $key_parts[$part_index] = function_exists('mb_strtolower') ? mb_strtolower($part) : strtolower($part);
                            $key_parts[$part_index][0] = strtoupper($key_parts[$part_index][0]);
                        }
                        $server_key = implode('-', $key_parts);
                    }
                }
                $headers[$server_key] = $val;
            }
        }
		return $headers;
	}


	/**
	 * Merging arrays without reseting numeric keys
	 *
	 * @param array $arr1 One-dimentional array
	 * @param array $arr2 One-dimentional array
	 *
	 * @return array Merged array
	 */
	public static function array_merge__save_numeric_keys($arr1, $arr2)
	{
		foreach($arr2 as $key => $val){
			$arr1[$key] = $val;
		}
		return $arr1;
	}

	/**
	 * Merging arrays without reseting numeric keys recursive
	 *
	 * @param array $arr1 One-dimentional array
	 * @param array $arr2 One-dimentional array
	 *
	 * @return array Merged array
	 */
	public static function array_merge__save_numeric_keys__recursive($arr1, $arr2)
	{
		foreach($arr2 as $key => $val){

			// Array | array => array
			if(isset($arr1[$key]) && is_array($arr1[$key]) && is_array($val)){
				$arr1[$key] = self::array_merge__save_numeric_keys__recursive($arr1[$key], $val);

			// Scalar | array => array
			}elseif(isset($arr1[$key]) && !is_array($arr1[$key]) && is_array($val)){
				$tmp = $arr1[$key] =
				$arr1[$key] = $val;
				$arr1[$key][] = $tmp;

			// array  | scalar => array
			}elseif(isset($arr1[$key]) && is_array($arr1[$key]) && !is_array($val)){
				$arr1[$key][] = $val;

			// scalar | scalar => scalar
			}else{
				$arr1[$key] = $val;
			}
		}
		return $arr1;
	}

	/**
	 * Function removing non UTF8 characters from array|string|object
	 *
	 * @param array|object|string $data
	 *
	 * @return array|object|string
	 */
	public static function removeNonUTF8($data)
	{
		// Array || object
		if(is_array($data) || is_object($data)){
			foreach($data as $key => &$val){
				$val = self::removeNonUTF8($val);
			}
			unset($key, $val);

			//String
		}else{
			if(!preg_match('//u', $data))
				$data = 'Nulled. Not UTF8 encoded or malformed.';
		}
		return $data;
	}

	/**
	 * Function convert anything to UTF8 and removes non UTF8 characters
	 *
	 * @param array|object|string $obj
	 * @param string              $data_codepage
	 *
	 * @return mixed(array|object|string)
	 */
    public static function toUTF8($obj, $data_codepage = null)
    {
        // Array || object
        if ( is_array($obj) || is_object($obj) ){
            foreach($obj as $key => &$val){
                $val = self::toUTF8($val, $data_codepage);
            }
            unset($key, $val);

            //String
        } else {
            $obj = is_null($obj) ? '' : $obj;
            if (
                !preg_match('//u', $obj) &&
                function_exists('mb_detect_encoding') &&
                function_exists('mb_convert_encoding')
            ) {
                $encoding = mb_detect_encoding($obj);
                $encoding = $encoding ? $encoding : $data_codepage;
                if($encoding)
                    $obj = mb_convert_encoding($obj, 'UTF-8', $encoding);
            }
        }
        return $obj;
    }

	/**
	 * Function convert from UTF8
	 *
	 * @param array|object|string $obj
	 * @param string              $data_codepage
	 *
	 * @return mixed (array|object|string)
	 */
	public static function fromUTF8($obj, $data_codepage = null)
	{
		// Array || object
		if(is_array($obj) || is_object($obj)){
			foreach($obj as $key => &$val){
				$val = self::fromUTF8($val, $data_codepage);
			}
			unset($key, $val);

			//String
		}else{
			if(preg_match('/u/', $obj) && function_exists('mb_convert_encoding') && $data_codepage !== null)
				$obj = mb_convert_encoding($obj, $data_codepage, 'UTF-8');
		}
		return $obj;
	}

	/**
	 * Checks if the string is JSON type
	 *
	 * @param string
	 *
	 * @return bool
	 */
	static public function is_json($string)
	{
		return is_string($string) && is_array(json_decode($string, true)) ? true : false;
	}

	/**
	 * Checks if given string is valid regular expression
	 *
	 * @param string $regexp
	 *
	 * @return bool
	 */
	static public function is_regexp($regexp){
		return @preg_match('/' . $regexp . '/', null) !== false;
	}

	static public function convert_to_regexp( $string ){
		$string = preg_replace( '/\$/', '\\\\$', $string );
		$string = preg_replace( '/\//', '\/', $string );
		return $string;
	}

    /**
     * Wrapper for http_request
     * Requesting HTTP response code for $url
     *
     * @param string $url
     *
     * @return array|mixed|string
     */
    static public function http__request__get_response_code( $url ){
        return static::http__request( $url, array(), 'get_code');
    }

    public static function http__download_remote_file( $url, $tmp_folder ){

        $result = self::http__request( $url, array(), 'get_file' );

        if( empty( $result['error'] ) ){

            $file_name = basename( $url );

            if( ! is_dir( $tmp_folder ) )
                mkdir( $tmp_folder );

            if( ! file_exists( $tmp_folder . $file_name ) ){
                file_put_contents( $tmp_folder . $file_name, $result );
                return $tmp_folder . $file_name;
            }else
                return array( 'error' => 'File already downloaded');
        }else
            return $result;
    }
}
