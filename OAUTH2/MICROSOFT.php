<?php
/**
 * Project: MICROSOFT:: Micosoft (Azure) oauth2 pear package<br>
 * File:    MICROSOFT.php<br>
 * Dependency:
 *   - {@link http://pear.oops.org/docs/li_HTTPRelay.html oops/HTTPRelay}
 *   - {@link http://pear.oops.org/docs/li_myException.html oops/myException}
 *
 * oops/MICROSOFT pear package는 Microsoft(Azuer) Directory oauth2 login을 위한 pear package이다
 *
 * 이 package를 사용하기 위해서는 먼저 Azzer portal 에서 tetant 를 생성한 후
 * app 등록을 해야 한다. (https://portal.azure.com)
 * https://docs.microsoft.com/ko-kr/azure/active-directory/develop/quickstart-register-app
 *
 * @category  HTTP
 * @package   oops\OAUTH2
 * @subpackage   oops\OAUTH2\MICROSOFT
 * @author    JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2019, OOPS.org
 * @license   BSD License
 * @example   OAUTH2/tests/microsoft.php MICROSOFT pear package 예제 코드
 * @since     1.0.9
 * @filesource
 */

namespace oops\OAUTH2;

/**
 * import HTTPRelay class
 */
require_once 'HTTPRelay.php';


/**
 * MICROSOFT pear pcakge의 main class
 *
 * OAuth2를 이용하여 MICROSOFT 로그인을 진행하고, 로그인된 사용자의
 * 정보를 얻어온다.
 *
 * @package   oops\OAUTH2
 * @subpackage   oops\OAUTH2\MICROSOFT
 * @author    JoungKyun.Kim <http://oops.org>
 * @copyright (c) 2019, OOPS.org
 * @license   BSD License
 * @since     1.0.9
 * @example   OAUTH2/tests/microsoft.php MICROSOFT pear 예제 코드
 */
Class MICROSOFT {
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
	private $reqAuth  = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize';
	/**
	 * token url
	 * @var string
	 */
	private $reqToken = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token';
	/**
	 * revoke url
	 * @var string
	 */
	private $reqRevoke = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/logout';
	/**
	 * user information url
	 * @var string
	 */
	private $reqUser  = 'https://graph.microsoft.com/v1.0/me';
	/**
	 * app information
	 * @var stdClass memebr는 다음과 같음
	 *   - id     : Google login Application ID
	 *   - secret : Google login Application Secret key
	 *   - callback : 이 class를 호출하는 페이지
	 *   - tenant : 로그인 계정의 유형.
	 *              'common', 'organizations', 'cunsumers', TENANT ID
	 *   - baseurl : reqLogout 호출 후 돌아갈 site call url
	 */
	private $apps;
	/**
	 * SSL type
	 * @var string
	 */
	private $proto = 'http';
	/**#@-*/
	/**
	 * MICROSOFT 로그인에 필요한 session 값
	 * @access public
	 * @var stdClass
	 */
	public $sess;
	/**#@-*/
	/**
	 * MICROSOFT 로그인 scope
	 * @access public
	 * @var string
	 */
	public $scope = 'openid offline_access user.read';
	// }}}

	// {{{ +-- public (void) __construct ($v)
	/**
	 * Google 로그인 인증 과정을 수행한다. 인증 과정 중에
	 * 에러가 발생하면 myException 으로 에러 메시지를
	 * 보낸다.
	 *
	 * logout 시에 globale 변수 $_OAUTH2_LOGOUT_TEMPALTE_ 로 사용자 logout template
	 * 을 지정할 수 있다. template 파일은 pear/OAUTH2/login-agree.template 를 참조하면 된다.
	 *
	 * @access public
	 * @param stdClass $v
	 *   - id       발급받은 Google login Application ID
	 *   - secret   발급받은 Google login Application Scret key
	 *              Azuer Active Directory -> App -> 인증서 및 암호의 클아이언트 암호를 설정
	 *              하여 사용한다.
	 *   - callback 이 클래스가 호출되는 url
	 *              callback url 은 Azuer Active Directory -> App -> 인증 -> 리디렉션 URI로
	 *              등록 되어 있어야 한다.
	 *   - tenant   로그인 계정의 유형을 결정.
	 *              https://docs.microsoft.com/ko-kr/azure/active-directory/develop/active-directory-v2-protocols#endpoints 참고
	 *   - baseurl  로그아웃 후 돌아갈 callback uri (또는 사이트 root 경로)
	 *              baseurl 은 Azuer Active Directory -> App -> 인증 -> 리디렉션 URI로
	 *              등록 되어 있어야 한다.
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

		$this->reqAuth = preg_replace ('/{tenant}/', $app->tenant, $this->reqAuth);

		# '%s?client_id=%s&response_type=code&redirect_uri=%s&' .
		# 'response_mode=from_post&scope=openid&state=%s&nonce=%s',
		#  $this->reqAuth, $app->id, rawurlencode ($app->callback), $this->sess->state
		$url = sprintf (
			'%s?client_id=%s&response_type=code&redirect_uri=%s&' .
			'response_mode=query&scope=%s&state=%s',
			$this->reqAuth, $app->id, rawurlencode ($app->callback),
			$this->scope,
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
	 * MICROSOFT::$sess->oauth 를 stdClass로 생성하고 다음의
	 * member를 등록한다.
	 *
	 *   - access_token:      발급받은 access token. expires_in(초) 이후 만료
	 *   - refresh_token:     access token 만료시 재발급 키 (14일 expire)
	 *   - token_type:        Bearer or MAC
	 *   - expires_in:        access token 유효시간(초)
	 *   - error:             error code
	 *   - error_description: error 상세값
	 *
	 * 등록된 권한 동의는 https://microsoft.com/consent 에서 철회가 가능하다
	 *
	 * @access private
	 * @return void
	 */
	private function reqAccessToken () {
		$sess = &$this->sess;
		$app  = &$this->apps;

		if ( ! $_GET['code'] || isset ($sess->oauth) )
			return;

		$this->reqToken = preg_replace ('/{tenant}/', $app->tenant, $this->reqToken);

		$post = array (
			'client_id' => $app->id,
			'scope' => $this->scope,
			'code' => $_GET['code'],
			'redirect_uri' => $app->callback,
			'grant_type' => 'authorization_code',
			'client_secret' => $app->secret
		);

		$http = new \HTTPRelay;
		$buf = $http->fetch ($this->reqToken, 10, '', $post);

		#if ( $http->info['http_code'] != 200 )
		if ( $buf === false )
			$this->error ($http->error);

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

		if ( $_POST['error'] )
			$this->error ($_PSOT['error_description']);

		# 복원
		#if ( $_POST['state'] && $_POST['state'] != $sess->state )
		#	$this->error ('Invalude Session state: ' . $_POST['state']);
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
	 * 로그인 과정이 완료되면 발급받은 oops\OAUTH2\MICROSOFT::$sess->oauth 에
	 * 등록된 키를 이용하여 로그인 사용자의 정보를 가져온다.
	 *
	 * @access public
	 * @return stdClass 다음의 object를 반환
	 *   - id     사용자 UID
	 *   - name   사용자 별칭
	 *   - email  이메일
	 *   - img    프로필 사진 URL 정보 (Don't support. Not yet support for personal account)
	 *   - r      MS profile 원본 값
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

		$r = json_decode ($buf);

		$re = array (
			'id' => $r->id,
			'name' => $r->displayName,
			'email' => $r->mail ? $r->mail : $r->userPrincipalName,
			'img'  => $r->picture,
			'r' => $r
		);

		/*
		 * Not yet support for personal account
		 * https://docs.microsoft.com/ko-kr/graph/api/profilephoto-get?view=graph-rest-1.0
		unset ($buf);
		$buf = $http->fetch ('https://graph.microsoft.com/v1.0/me/photo');
		echo "~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
		print_r ($http);
		print_r ($buf);
		echo "~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
		 */

		return (object) $re;
	}
	// }}}

	// {{{ +-- public (void) reqLogout (void)
	/**
	 * Microsoft 로그인의 authorization key를 만료 시키고
	 * 세션에 등록된 정보(oops\OAUT2\MICROSOFT::$sess)를 제거한다.
	 *
	 * 로그 아웃 후, MICROSOFT::$app->baseurl 로 이동을 한다.
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
			unset ($_SESSION[$this->sessid]);
			$this->reqRevoke= preg_replace ('/{tenant}/', $app->tenant, $this->reqRevoke);

			$redirect = sprintf (
				'%s?post_logout_redirect_uri=%s',
				$this->reqRevoke, rawurlencode ($app->baseurl)
			);
			Header ('Location: ' . $redirect);
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
