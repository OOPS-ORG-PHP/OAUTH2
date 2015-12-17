<?php
/**
 * Project: oops\OAUTH2:: OAUTH2 pear package<br>
 * File:    DAUM.php<br>
 * Dependency:
 *   - {@link http://pear.oops.org/docs/li_HTTPRelay.html oops/HTTPRelay}
 *   - {@link http://pear.oops.org/docs/li_myException.html oops/myException}
 *   - {@link http://kr1.php.net/manual/en/book.curl.php curl extension}
 *
 * oops\OAUTH2 pear package는 OAUTH2 login 및 profile 정보를
 * 다루기 위한 library이다.
 *
 * 이 package를 사용하기 위해서는 먼저 각 모듈의 벤더 페이지에서
 * Application ID와 Application Secret을 발급받아야 한다. 각 모듈의
 * 상단 주석을 참조하라.
 *
 * 현재 GOOGLE, FACEBOOK, DAUM, NAVER 를 지원한다.
 *
 * @category  HTTP
 * @package   oops\OAUTH2
 * @author    JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2015 OOPS.org
 * @license   BSD License
 * @version   SVN: $Id$
 * @link      http://pear.oops.org/package/OAUTH2
 * @example   pear_DAUM/tests/test.php OAUTH2 pear package 예제 코드
 * @filesource
 */

/**
 * Namespace oops;
 */
namespace oops;

/**
 * import myException class
 */
require_once 'myException.php';

/**
 * oops\OAUth2 pear package의 main class
 *
 * OAUTH2를 이용하여 로그인을 진행하고, 로그인된 사용자의
 * 프로필 정보를 관리한다.
 *
 * 현재 GOOGLE, FACEBOOK, DAUM, NAVER 를 지원한다.
 *
 * @package oops/OAUTH2
 * @author JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2015 JoungKyun.Kim
 * @license BSD License
 * @version SVN: $Id$
 * @example pear_OAUTH2/tests/test.php OAUTH2 pear 예제 코드
 */
Class OAUth2 {
	// {{{ properities
	/**
	 * @access public
	 * @var stdClass 벤더 정고
	 */
	public $vendor;
	/**
	 * access private
	 * @var object
	 */
	private $o;
	// }}}

	// {{{ +-- public (void) __construct ($app)
	/**
	 * OAUTH2 로그인 인증 과정을 수행한다. 인증 과정 중에 에러가
	 * 발생하면 myException으로 에러 메시지를 보낸다.
	 *
	 * @access public
	 * @param stdClass $app  로그인 정보
	 *   - vendor    OAUTH2 Service Provider (현재 google/facebook/daum/naver 지원)
	 *   - id        Service Provider 에서 발급받은 client ID
	 *   - secret    Service Provider 에서 발급받은 client secret key
	 *   - callback  이 class가 호출되는 URL (또는 provider에 등록한 callback url)
	 * @return void
	 */
	function __construct ($app) {
		$this->vendor = strtoupper ($app->vendor);

		$resource = sprintf ('OAUTH2/%s.php', $this->vendor);
		$entry = preg_split ('/:/', $this->ini_get ('include_path'));

		if ( ! $this->file_exists ($resource) )
			throw new \myException (sprintf ('Unsupport vendor "%s"', $app->vendor));

		require_once $resource;

		$className = sprintf ('oops\OAUTH2\%s', $this->vendor);
		$this->o = new $className ($app);
	}
	// }}}

	// {{{ +-- public Profile (void)
	/**
	 * 로그인이 성공 후에, 로그인 사용자의 Profile을 가져오기 위한
	 * API
	 *
	 * @access public
	 * @return stdClass 사용자 Profile
	 *   - id    사용자 UID
	 *   - name  사용자 Nickname
	 *   - email 사용자 email (Provider에 따라 없을 수도 있다.)
	 *   - img   사용자 profile image url
	 *   - r     각 provider에서 제공하는 original profile 값
	 */
	public function Profile () {
		return $this->o->Profile ();
	}
	// }}}

	// {{{ +-- static public (stdClass) image ($url, $noprint = false)
	/**
	 * 외부 이미지를 읽어와서 출력한다. HTTPS protocold을 사용할
	 * 경우 provider에서 https image를 지원하지 않을 경우 사용.
	 *
	 * @access public
	 * @param string $url 원본 image URL
	 * @param bool $noprint (optional) true로 설정하면 출력하지
	 *             않고 반환한다. (default: false)
	 * @return stdClass 2번째 인자가 true일 경우에는 void 이다.
	 *   - type  gif/jpg/png 중 하나
	 *   - data  image raw data
	 */
	static public function image ($url, $noprint = false) {
		if ( ! $url )
			return;

		if ( preg_match ('!^//!', $url) ) {
			if ( $_SERVER['HTTPS'] )
				$url = 'https:' . $url;
			else
				$url = 'http:' . $url;
		}
		if ( ! ($url = filter_var($url, FILTER_VALIDATE_URL)) )
			return;

		$c = curl_init ();
		curl_setopt ($c, CURLOPT_URL, $url);
		curl_setopt ($c, CURLOPT_TIMEOUT, 60);
		curl_setopt ($c, CURLOPT_NOPROGRESS, 1);
		curl_setopt ($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($c, CURLOPT_USERAGENT, "OAUTH2 pear package");

		curl_setopt ($c, CURLOPT_HEADER, 0);
		curl_setopt ($c, CURLOPT_NOBODY, 0);
		curl_setopt ($c, CURLOPT_FAILONERROR, 1);

		$data = curl_exec($c);
		$info = (object) curl_getinfo ($c);

		if ( $info->http_code == 302 )
			return self::image ($info->redirect_url, $noprint);

		$ctype = $info->content_type;

		if ( ! $noprint ) {
			Header ('Content-Type: ' . $ctype);
			echo $data;
		}

		curl_close ($c);

		if ( $noprint ) {
			preg_match ('!/([a-z]+)!', $ctype, $matches);
			if ( $matches[1] == 'jpeg' )
				$matches[1] = 'jpg';

			return (object) array (
				'type' => $matches[1],
				'data' => $data
			);	
		}
	}
	// }}}

	// {{{ +-- private (mixed) ini_get ($var)
	/**
	 * Gets the value of a configuration option
	 *
	 * @access private
	 * @param string $var ini option 이름
	 * @return mixed Returns the value of the configuration option as
	 *         a string on success, or an empty string for null values.
	 *         Returns FALSE if the configuration option doesn't exist.
	 */
	private function ini_get ($var) {
		$func = function_exists ('___ini_get') ? '___' : '';
		$func .= 'ini_get';
		return $func ($var);
	}
	// }}}

	// {{{ +-- private (bool) file_exists ($f)
	/**
	 * 파일이나 디렉토리가 존재하는지 여부를 판단한다. pure file_exists
	 * 와의 차이는 include_path를 지원한다.
	 *
	 * @access private
	 * @param string $f 파일 경로
	 * @return bool 지정한 파일이나 디렉토리가 있으면 true를 반환하고
	 *              없으면 false를 반환
	 */
	private function file_exists ($f) {
		if ( @file_exists ($f) )
			return true;

		$entry = preg_split ('/:/', $this->ini_get ('include_path'));
		foreach ($entry as $base) {
			if ( @file_exists ($base . '/' . $f) )
				return true;
		}

		return false;
	}
	// }}}
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: noet sw=4 ts=4 fdm=marker
 * vim<600: noet sw=4 ts=4
 */
