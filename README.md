# OAUTH2 pear package
![GitHub license](https://img.shields.io/badge/license-BSD-blue.svg)

## License

Copyright (c) 2016 JoungKyun.Kim &lt;http://oops.org&gt; All rights reserved

This program is under BSD license

## Description

This is OAUTH2 login tool and support follow vendors:
 * Google
 * Facebook
 * Github
 * Naver
 * Daum
 * Kakao

## Installation

We recommand to install with pear command cause of dependency pear packages.

### 1. use pear command

```bash
[root@host ~]$ # add pear channel 'pear.oops.org'
[root@host ~]$ pear channel-discover pear.oops.org
Adding Channel "pear.oops.org" succeeded
Discovery of channel "pear.oops.org" succeeded
[root@host ~]$ # add OAUTH2 pear package
[root@host ~]$ pear install oops/OAUTH2
downloading OAUTH2-1.0.4.tgz ...
Starting to download OAUTH2-1.0.4.tgz (10,893 bytes)
....done: 10,893 bytes
downloading HTTPRelay-1.0.5.tgz ...
Starting to download HTTPRelay-1.0.5.tgz (5,783 bytes)
...done: 5,783 bytes
downloading myException-1.0.1.tgz ...
Starting to download myException-1.0.1.tgz (3,048 bytes)
...done: 3,048 bytes
install ok: channel://pear.oops.org/myException-1.0.1
install ok: channel://pear.oops.org/HTTPRelay-1.0.5
install ok: channel://pear.oops.org/OAUTH2-1.0.4
[root@host ~]$
```

If you wnat to upgarde version:

```bash
[root@host ~]$ pear upgrade oops/OAUT2
```


### 2. install by hand

Get last release at https://github.com/OOPS-ORG-PHP/OAUTH2/releases and uncompress pakcage within PHP include_path.

You must need follow dependency pear packages:
 * myException at https://github.com/OOPS-ORG-PHP/myException/releases/
 * HTTPRelay at https://github.com/OOPS-ORG-PHP/HTTPRelay/releases/

## Usages

Refence siste: http://pear.oops.org/docs/oops-OAUTH2/OAUth2.html

reference is written by Korean. If you can't read korean, use [google translator](https://translate.google.com/translate?sl=auto&tl=en&js=y&prev=_t&hl=ko&ie=UTF-8&u=http%3A%2F%2Fpear.oops.org%2Fdocs%2Foops-OAUTH2%2FOAUth2.html&edit-text=&act=url).

```php
<?php
session_start ();

require_once 'OAUTH2.php';

set_error_handler ('myException::myErrorHandler');

// Callback URL is this page.
$callback = sprintf (
    '%s://%s%s',
    $_SERVER['HTTPS'] ? 'https' : 'http',
    $_SERVER['HTTP_HOST'],
    $_SERVER['REQUEST_URI']
);

$appId = (object) array (
    'vendor'   => 'google',
    'id'       => 'APPLICATION_ID',
    'secret'   => 'APPLICATION_SECRET_KEY',
    'callback' => $callback,
);

try {
    $oauth2 = new oops\OAUTH2 ($appId);

    // If you want to logout, give logout parameter at callback url.
    // If you need redirect after logout, give redrect parameter.
    // For example:
    //  http://callback_url?logout&redirect=http%3A%2F%2Fredirect_url
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
?>
```
