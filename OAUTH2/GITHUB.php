<?php
/**
 * Project: GITHUB:: GITHUB oauth2 pear package<br>
 * File:    GITHUB.php<br>
 * Dependency:
 *   - {@link http://pear.oops.org/docs/li_HTTPRelay.html oops/HTTPRelay}
 *   - {@link http://pear.oops.org/docs/li_myException.html oops/myException}
 *
 * oops/GITHUB pear package는 GITHUB oauth2 login을 위한 pear package이다
 *
 * 이 package를 사용하기 위해서는 먼저 GITHUB Developers Console 에서
 * Applicaion ID와 Application Secret을 발급받아야 한다.
 * https://developers.google.com/accounts/docs/OAuth2?hl=ko 를 참고하라.
 *
 *
 * @category  HTTP
 * @package   oops\OAUTH2
 * @subpackage   oops\OAUTH2\GITHUB
 * @author    JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2017 OOPS.org
 * @license   BSD License
 * @version   SVN: $Id$
 * @example   pear_OAUTH2/tests/github.php GITHUB pear package 예제 코드
 * @filesource
 */

namespace oops\OAUTH2;

/**
 * import HTTPRelay class
 */
require_once 'HTTPRelay.php';


/**
 * GITHUB pear pcakge의 main class
 *
 * OAuth2를 이용하여 GITHUB 로그인을 진행하고, 로그인된 사용자의
 * 정보를 얻어온다.
 *
 * @package   oops\OAUTH2
 * @subpackage   oops\OAUTH2\GITHUB
 * @author    JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2017 OOPS.org
 * @license   BSD License
 * @version   SVN: $Id$
 * @example   pear_OAUTH2/tests/github.php GITHUB pear 예제 코드
 */
Class GITHUB {
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
	private $reqAuth  = 'https://github.com/login/oauth/authorize';
	/**
	 * token url
	 * @var string
	 */
	private $reqToken = 'https://github.com/login/oauth/access_token';
	/**
	 * revoke url
	 * @var string
	 */
	private $reqRevoke = 'https://github.com/logout';
	/**
	 * user information url
	 * @var string
	 */
	private $reqUser  = 'https://api.github.com/user';
	/**
	 * app information
	 * @var stdClass memebr는 다음과 같음
	 *   - id     : GITHUB login Application ID
	 *   - secret : GITHUB login Application Secret key
	 *   - callback : 이 class를 호출하는 페이지
	 */
	private $apps;
	/**
	 * SSL type
	 * @access private
	 * @var string
	 */
	private $proto = 'http';
	/**#@-*/
	/**
	 * GITHUB 로그인에 필요한 session 값
	 * @access public
	 * @var stdClass
	 */
	public $sess;
	// }}}

	// {{{ +-- public (void) __construct ($v)
	/**
	 * GITHUB 로그인 인증 과정을 수행한다. 인증 과정 중에
	 * 에러가 발생하면 myException 으로 에러 메시지를
	 * 보낸다.
	 *
	 * logout 시에 globale 변수 $_OAUTH2_LOGOUT_TEMPALTE_ 로 사용자 logout template
	 * 을 지정할 수 있다. template 파일은 pear/OAUTH2/login-no-auto.template 를 참조하면 된다.
	 *
	 * @access public
	 * @param stdClass $v
	 *   - id       발급받은 GITHUB login Application ID
	 *   - secret   발급받은 GITHUB login Application Scret key
	 *   - callback 이 클래스가 호출되는 url
	 * @return void
	 */
	function __construct ($v) {
		if ( ! isset ($_SESSION[$this->sessid]) ) {
			$_SESSION[$this->sessid] = new \stdClass;
			$_SESSION[$this->sessid]->appId = (object) $v;
		}
		$this->sess = &$_SESSION[$this->sessid];
		$this->apps = (object) $v;

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
		$app = &$this->apps;
		$this->sess->state = $this->mkToken ();

		if ( $_GET['code'] || isset ($this->sess->oauth)  )
			return;

		$url = sprintf (
			'%s?scope=user:email&' . 
			'client_id=%s&redirect_uri=%s&state=%s',
			$this->reqAuth, $app->id,
			rawurlencode ($app->callback), $this->sess->state
		);

		Header ('Location: ' . $url);
		exit;
	}
	// }}}

	// {{{ +-- private (void) reqAccessToken (void)
	/**
	 * Authorization code를 발급받아 session에 등록
	 *
	 * GITHUB::$sess->oauth 를 stdClass로 생성하고 다음의
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
		$app  = &$this->apps;

		if ( ! $_GET['code'] || isset ($sess->oauth) )
			return;

		$post = array (
			'code' => $_GET['code'],
			'client_id' => $app->id,
			'client_secret' => $app->secret,
			'redirect_uri' => $app->callback,
			'state' => $_GET['state']
		);

		$http = new \HTTPRelay;
		$buf = $http->fetch ($this->reqToken, 10, '', $post);

		// json으로 처리하려면 reuqest header로 "Accept: application/json"을 보내주도록 한다.
		#$r = json_decode ($buf);
		parse_str ($buf, $r);

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
	 * 로그인 과정이 완료되면 발급받은 oops\OAUTH2\GITHUB::$sess->oauth 에
	 * 등록된 키를 이용하여 로그인 사용자의 정보를 가져온다.
	 *
	 * @access public
	 * @return stdClass 다음의 object를 반환
	 *   - id     사용자 UID
	 *   - name   사용자 별칭
	 *   - email  이메일
	 *   - img    프로필 사진 URL 정보 
	 *   - r      GITHUB profile 원본 값
	 */
	public function Profile () {
		$sess = &$this->sess;

		if ( ! isset ($sess->oauth) )
			return false;

		$req = $sess->oauth->token_type . ' ' . $sess->oauth->access_token;

		$header = array ('Authorization' => $req);
		$http = new \HTTPRelay ($header);
		$buf = $http->fetch ($this->reqUser);

		$r = json_decode ($buf);

		$re = array (
			'id' => $r->id,
			'name' => $r->name,
			'email' => $r->email,
			'img'  => $r->avatar_url,
			'r' => $r
		);

		return (object) $re;
	}
	// }}}

	// {{{ +-- public (void) reqLogout (void)
	/**
	 * GITHUB 로그인의 authorization key를 만료 시키고
	 * 세션에 등록된 정보(oops\OAUT2\GITHUB::$sess)를 제거한다.
	 *
	 * @access public
	 * @return void
	 */
	public function reqLogout () {
		$sess = &$this->sess;
		$app  = &$this->apps;

		if ( ! isset ($sess->oauth) )
			return;

		if ( ! isset ($_GET['after']) ) {
			if ( trim ($_SERVER['QUERY_STRING']) )
				$qs = sprintf ('?%s&after', $_SERVER['QUERY_STRING']);
			else
				$qs = '?after';

			$redirect = $_SERVER['SCRIPT_URI'] . $qs;

			$logoutDocPath = 'OAUTH2/logout-no-auto.template';
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
				'GITHUB',
				$redirect,
				$this->reqRevoke,
				600, 400
			);
			$logoutDoc = preg_replace ($src, $dst, $logoutDoc);
			echo $logoutDoc;
			exit;
		}

		unset ($_SESSION[$this->sessid]);
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
