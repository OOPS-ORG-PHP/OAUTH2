<?php
/*
 * oops\AUTH2 KAKAO test page
 *
 * oops\AUTH2\KAKAO package는 독립적으로도 사용이 가능하다.
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

require_once 'OAUTH2/KAKAO.php';

set_error_handler ('myException::myErrorHandler');

$callback = sprintf (
	'%s://%s%s',
	$_SERVER['HTTPS'] ? 'https' : 'http',
	$_SERVER['HTTP_HOST'],
	$_SERVER['REQUEST_URI']
);

$appId = (object) array (
	'id'       => 'APPLICATION_ID',
	'secret'   => 'APPLICATION_SECRET_KEY',
	'callback' => $callback,
);

try {
	$oauth2 = new oops\OAUTH2\KAKAO ($appId);

	// logout 시에는 callback url에 logout parameter를 추가하고,
	// logout 후에 redirect가 필요하면 redirect parameter까지 추가한다.
	if ( isset ($_GET['logout']) ) {
		unset ($_SESSION['oauth2']);

		if ( $_GET['redirect'] )
			Header ('Location: ' . $redirect);

		printf ('%s Complete logout', strtoupper ($appId->vendor));
		exit;
	}

	$user = $oauth2->Profile ();
	$uid = sprintf ('%s:%s', $appId->vendor, $user->id);
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
