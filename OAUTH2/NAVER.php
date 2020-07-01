<?php
/**
 * Project: oops\OAUTH2\NAVER :: 네이버 로그인(oauth2) pear package<br>
 * File:    NAVER.php<br>
 * Dependency:
 *   - {@link http://pear.oops.org/docs/li_HTTPRelay.html oops/HTTPRelay}
 *   - {@link http://pear.oops.org/docs/li_myException.html oops/myException}
 *
 * oops\OAUTH2\NAVER pear package는
 * {@link http://developer.naver.com/wiki/pages/NaverLogin 네이버 아이디로 로그인}
 * 을 pear package로 구현을 한 것이다.
 *
 * {@link http://developer.naver.com/wiki/pages/NaverLogin 네이버 아이디로 로그인}은
 * {@link http://developer.naver.com/wiki/pages/OAuth2 네이버 OAuth}와는 다른 API로서
 * 로그인 사용자의 개인 식별 정보를 활용할 수 있으며, OAuth2 방식으로
 * 구현이 되어있다.
 *
 * 이 package를 사용하기 위해서는 먼저 Naver에서 Application ID와 Application
 * Secret을 발급받아야 한다. http://developer.naver.com/wiki/pages/NaverLogin 를
 * 참고하라.
 *
 *
 * @category  HTTP
 * @package   oops\OAUTH2
 * @subpackage oops\OAUTH2\NAVER
 * @author    JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2020, OOPS.org
 * @license   BSD License
 * @example   OAUTH2/tests/naver.php NAVER pear package 예제 코드
 * @filesource
 */

namespace oops\OAUTH2;

/**
 * import HTTPRelay class
 */
require_once 'HTTPRelay.php';


/**
 * Naver pear pcakge의 main class
 *
 * OAuth2를 이용하여 네이버 로그인을 진행하고, 로그인된 사용자의
 * 정보를 얻어온다.
 *
 * @package oops\OAUTH2
 * @subpackage oops\OAUTH2\NAVER
 * @author    JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2020, OOPS.org
 * @license   BSD License
 * @example   OAUTH2/tests/naver.php NAVER pear 예제 코드
 */
Class NAVER {
	// {{{ properities
	/**#@+
	 * @access private
	 */
	/**
	 * 세션 이름
	 * @var string
	 */
	private $sessid   = '_OAUTH2_';
	/**
	 * login url
	 * @var string
	 */
	private $reqAuth  = 'https://nid.naver.com/oauth2.0/authorize';
	/**
	 * token url
	 * @var string
	 */
	private $reqToken = 'https://nid.naver.com/oauth2.0/token';
	/**
	 * user information url
	 * @var string
	 */
	private $reqUser  = 'https://apis.naver.com/nidlogin/nid/getUserProfile.xml';
	/**
	 * revoke url
	 * @var string
	 */
	private $reqRevoke = 'https://nid.naver.com/nidlogin.logout';
	/**
	 * consumer information
	 * @var stdClass memebr는 다음과 같음
	 *   - id     : Naver login CliendID key
	 *   - secret : Naver login ClientSecret key
	 *   - callback : 이 class를 호출하는 페이지
	 */
	private $consumer;
	/**
	 * SSL type
	 * @var string
	 */
	private $proto = 'http';
	/**#@-*/
	/**
	 * 네이버 로그인에 필요한 session 값
	 * @access public
	 * @var stdClass
	 */
	public $sess;
	// }}}

	// {{{ +-- public (void) __construct ($v)
	/**
	 * Naver 로그인 인증 과정을 수행한다. 인증 과정 중에
	 * 에러가 발생하면 myException 으로 에러 메시지를
	 * 보낸다.
	 *
	 * logout 시에 globale 변수 $_OAUTH2_LOGOUT_TEMPALTE_ 로 사용자 logout template
	 * 을 지정할 수 있다. template 파일은 pear/OAUTH2/login.template 를 참조하면 된다.
	 *
	 * @access public
	 * @param stdClass $v
	 *   - id       발급받은 Naver login ClientID key
	 *   - secret   발급받은 Naver login ClientScret key
	 *   - callback 이 클래스가 호출되는 url
	 * @return void
	 */
	function __construct ($v) {
		if ( ! isset ($_SESSION[$this->sessid]) ) {
			$_SESSION[$this->sessid] = new \stdClass;
			$_SESSION[$this->sessid]->appId = (object) $v;
		}
		$this->sess = &$_SESSION[$this->sessid];
		$this->consumer = (object) $v;

		if ( isset ($_SERVER['HTTPS']) )
			$this->proto .= 's';

		if ( isset ($_GET['logout']) ) {
			$this->reqLogout ();
			return;
		}

		$this->checkError ();
		$this->reqLogin ();
		$this->reqAccessToken ();
	}
	// }}}

	// {{{ +-- private (string) mkToken (void)
	/**
	 * 세션 유지를 위한 token 값
	 *
	 * @access private
	 * @return string
	 */
	private function mkToken () {
		$mt = microtime ();
		$rand = mt_rand ();
		return md5 ($mt . $rand);
	}
	// }}}

	// {{{ +-- private (void) reqLogin (void)
	/**
	 * 로그인 창으로 redirect
	 *
	 * @access private
	 * @return void
	 */
	private function reqLogin () {
		$cons = &$this->consumer;
		$this->sess->state = $this->mkToken ();

		if ( $_GET['code'] || isset ($this->sess->oauth)  )
			return;

		$url = sprintf (
			'%s?client_id=%s&response_type=code&redirect_uri=%s&state=%s',
			$this->reqAuth, $cons->id, rawurlencode ($cons->callback),
			$this->sess->state
		);

		Header ('Location: ' . $url);
		exit;
	}
	// }}}

	// {{{ +-- private (void) reqAccessToken (void)
	/**
	 * Authorization code를 발급받아 session에 등록
	 *
	 * NAVER::$sess->oauth 를 stdClass로 생성하고 다음의
	 * member를 등록한다.
	 *
	 *   - access_token:      발급받은 access token. expires_in(초) 이후 만료
	 *   - refresh_token:     access token 만료시 재발급 키 (14일 expire)
	 *   - token_type:        Bearer or MAC
	 *   - expires_in:        access token 유효시간(초)
	 *   - error:             error code
	 *   - error_description: error 상세값
	 *
	 * @access private
	 * @return void
	 */
	private function reqAccessToken () {
		$sess = &$this->sess;
		$cons = &$this->consumer;

		if ( ! $_GET['code'] || isset ($sess->oauth) )
			return;

		$url = sprintf (
			'%s?client_id=%s&client_secret=%s&grant_type=authorization_code&state=%s&code=%s',
			$this->reqToken, $cons->id, $cons->secret, $sess->state, $_GET['code']
		);

		$http = new \HTTPRelay;
		$buf = $http->fetch ($url);
		$r = json_decode ($buf);

		if ( $r->error )
			$this->error ($r->error_description);
		
		$sess->oauth = (object) $r;
	}
	// }}}

	// {{{ +-- private (void) checkError (void)
	/**
	 * 에러 코드가 존재하면 에러 처리를 한다.
	 *
	 * @access private
	 * @return void
	 */
	private function checkError () {
		$sess = &$this->sess;

		if ( $_GET['error'] )
			$this->error ($_GET['error_description']);

		if ( $_GET['state'] && $_GET['state'] != $sess->state )
			$this->error ('Invalude Session state: ' . $_GET['state']);
	}
	// }}}

	// {{{ +-- private (void) error ($msg)
	/**
	 * 에러를 Exception 처리한다.
	 *
	 * @access private
	 * @return void
	 */
	private function error ($msg) {
		$msg = $_SERVER['HTTP_REFERER'] . "\n" . $msg;
		throw new \myException ($msg, E_USER_ERROR);
	}
	// }}}

	// {{{ +-- private (string) redirectSelf (void)
	/**
	 * 현재 URL에 after argument를 set한다.
	 *
	 * @access private
	 * @return string
	 */
	private function redirectSelf () {
		if ( trim ($_SERVER['QUERY_STRING']) )
			$qs = sprintf ('?%s&after', $_SERVER['QUERY_STRING']);
		else
			$qs = '?after';

		$req = preg_replace ('/\?.*/', '', trim ($_SERVER['REQUEST_URI']));
		if ( ! $req ) $req = '/';

		return sprintf (
			'%s://%s%s%s',
			$this->proto, $_SERVER['HTTP_HOST'], $req, $qs
		);
	}
	// }}}

	// {{{ +-- public (stdClass) Profile (void)
	/**
	 * 로그인 과정이 완료되면 발급받은 NAVER::$sess->oauth 에 등록된
	 * 키를 이용하여 로그인 사용자의 정보를 가져온다.
	 *
	 * 2018-01-30 이후, 사용자 UID 만 기본 값이고, 나머지는 옵션으로 변경이 되어,
	 * 값이 없을 수 있다.
	 *
	 * @access public
	 * @return stdClass 다음의 object를 반환
	 *   - id       사용자 UID
	 *   - nickname 사용자 별칠
	 *   - realname 사용자 실명
	 *   - name     사용자 이름 (또는 사용자 별칭)
	 *   - email    이메일
	 *   - gender   성별
	 *   - age      나이대
	 *   - birth    출생년을 제외한 생일
	 *   - img      프로필 사진 URL 정보 
	 */
	public function Profile () {
		$sess = &$this->sess;

		if ( ! isset ($sess->oauth) )
			return false;

		$req = $sess->oauth->token_type . ' ' . $sess->oauth->access_token;

		$header = array ('Authorization' => $req);
		$http = new \HTTPRelay ($header);
		$buf = $http->fetch ($this->reqUser);

		if ( ! $buf )
			$this->error (sprintf ('[OAUTH2] Failed get user profile for %s', __CLASS__));

		$xml = simplexml_load_string ($buf);
		if ( $xml->result->resultcode != '00' )
			$this->error ($r->result->message);

		$xmlr = &$xml->response;

		$rname = (string) $xmlr->name{0};
		$nname = (string) $xmlr->nickname{0};
		$name = $rname ? $rname : $nname;

		$r = array (
			'id'       => (string) $xmlr->enc_id{0},
			'realname' => $rname,
			'nickname' => $nname,
			'name'     => $name,
			'email'    => (string) $xmlr->email{0},
			'gender'   => (string) $xmlr->gender{0},
			'age'      => (string) $xmlr->age{0},
			'birth'    => (string) $xmlr->birthday,
			'img'      => (string) $xmlr->profile_image
		);

		return (object) $r;
	}
	// }}}

	// {{{ +-- public (void) reqLogout (void)
	/**
	 * 네이버 로그인의 authorization key를 만료 시키고
	 * 세션에 등록된 정보(oops\OAUTH2\NAVER::$sess)를 제거한다.
	 *
	 * @access public
	 * @return void
	 */
	public function reqLogout () {
		$sess = &$this->sess;
		$cons = &$this->consumer;

		if ( ! isset ($sess->oauth) )
			return;

		if ( ! isset ($_GET['after']) ) {
			$url = sprintf (
				'%s?grant_type=delete&client_id=%s&client_secret=%s&' .
				'access_token=%s&service_provider=NAVER',
				$this->reqToken, $cons->id, $cons->secret, $sess->oauth->access_token
			);

			$http = new \HTTPRelay;
			$buf = $http->fetch ($url);

			$r = json_decode ($buf);
			if ( $r->error )
				$this->error ($r->error_description);

			unset ($_SESSION[$this->sessid]);

			if ( trim ($_SERVER['QUERY_STRING']) )
				$qs = sprintf ('?%s&after', $_SERVER['QUERY_STRING']);
			else
				$qs = '?after';

			$redirect = $_SERVER['SCRIPT_URI'] . $qs;

			$logoutDocPath = 'OAUTH2/logout.template';
			if ( $GLOBALS['_OAUTH2_LOGOUT_TEMPALTE_'] ) {
				if ( file_exists ($GLOBALS['_OAUTH2_LOGOUT_TEMPALTE_']) )
					$logoutDocPath = $GLOBALS['_OAUTH2_LOGOUT_TEMPALTE_'];
			}
			$logoutDoc = file_get_contents ($logoutDocPath, true);
			$src = array (
				'/{%VENDOR%}/',
				'/{%REDIRECT%}/',
				'/{%LOGOUT-URL%}/',
				'/{%WIN-WIDTH%}/',
				'/{%WIN-HEIGHT%}/',
			);
			$dst = array (
				'NAVER',
				$redirect,
				$this->reqRevoke,
				600, 250
			);
			$logoutDoc = preg_replace ($src, $dst, $logoutDoc);
			echo $logoutDoc;
			exit;
		}
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
?>
