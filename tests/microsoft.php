<?php
/*
 * oops\AUTH2 MICROSOFT test page
 *
 * oops\AUTH2\MICROSOFT package는 독립적으로도 사용이 가능하다.
 *
 * dependency
 *
 * pear oops/myException
 * pear oops/HTTPRelay
 */

session_start ();

$devel = false;
if ( $devel == true ) {
	$iniget = function_exists ('___ini_get') ? '___ini_get' : 'ini_get';
	$iniset = function_exists ('___ini_set') ? '___ini_set' : 'ini_set';

	$cwd = getcwd ();
	$ccwd = basename ($cwd);
	if ( $ccwd == 'tests' ) {
		$oldpath = $iniget ('include_path');
		$newpath = preg_replace ("!/{$ccwd}!", '', $cwd);
		$iniset ('include_path', $newpath . ':' . $oldpath);
	}
}

require_once 'OAUTH2/MICROSOFT.php';

set_error_handler ('myException::myErrorHandler');

$callback = sprintf (
	'%s://%s%s',
	$_SERVER['HTTPS'] ? 'https' : 'http',
	$_SERVER['HTTP_HOST'],
	$_SERVER['REQUEST_URI']
);

$logout_point = sprintf (
	'%s://%s',
	$_SERVER['HTTPS'] ? 'https' : 'http', $_SERVER['HTTP_HOST']
);

$appId = (object) array (
	'id'       => 'APPLICATION_ID',           // Application(Client) ID
	'secret'   => 'APPLICATION_SECRET_KEY',   // Client Secret
	'callback' => $callback,                  // Redirection(Callback) URI
	'tenant'   => 'common',                   // 'common', 'organizations', 'cunsumers', TENANT ID
	                                          // See also https://docs.microsoft.com/ko-kr/azure/active-directory/develop/active-directory-v2-protocols#endpoints
	'baseurl'  => $logout_point               // URL after logout (must regist as Redirection URI)
);

try {
	$oauth2 = new oops\OAUTH2\MICROSOFT ($appId);

	// logout 시에는 callback url에 logout parameter를 추가하고,
	// logout 후에 redirect가 필요하면 redirect parameter까지 추가한다.
	/*
	 * Azure Active directory 는 logout 과정(MICROSOFT::reqLogout method)에서
	 * 직접 rediretion 을 해 주기 때문에 이 과정이 필요 없다.
	if ( isset ($_GET['logout']) ) {
		unset ($_SESSION['oauth2']);

		if ( $_GET['redirect'] )
			Header ('Location: ' . $redirect);

		printf ('%s Complete logout', strtoupper ($appId->vendor));
		exit;
	}
	 */

	$user = $oauth2->Profile ();
	$uid = sprintf ('%s:%s', $appId->vendor, $user->id);
	// tenant 가 common 일 경우에는 profile image를 제공하지 않고 있다.
	$_SESSION['oauth2'] = (object) array (
		'uid' => $uid,
		'name' => $user->name,
		'email' => $user->email,
		'img' => $user->img,
		'logout' => $callback . '?logout'
	);

	print_r ($_SESS['oauth2']);
} catch ( myException $e ) {
	echo $e->Message () . "\n";
	print_r ($e->TraceAsArray);
	$e->finalize ();
}
