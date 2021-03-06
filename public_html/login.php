<?php

/*
  +----------------------------------------------------------------------+
  | The PECL website                                                     |
  +----------------------------------------------------------------------+
  | Copyright (c) 1999-2018 The PHP Group                                |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.01 of the PHP license,      |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | https://php.net/license/3_01.txt                                     |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Authors:                                                             |
  +----------------------------------------------------------------------+
*/

/*
 * If the PHPSESSID cookie isn't set, the user MAY have cookies turned off.
 * To figure out cookies are REALLY off, check to see if the person came
 * from within the PECL website or just submitted the login form.
 */
if (!isset($_COOKIE[session_name()]) && isset($_POST['PECL_USER']) && isset($_POST['PECL_PW'])) {
//    $auth->reject('Cookies must be enabled to log in.');
}

// If they're already logged in, say so.
if (!empty($auth_user)) {
    response_header('Login');
    echo '<div class="warnings">You are already logged in.</div>';
    response_footer();
    exit;
}

if (isset($_POST['PECL_USER'], $_POST['PECL_PW']) && $auth->verify($_POST['PECL_USER'], $_POST['PECL_PW'])) {
    if (!empty($_POST['PECL_PERSIST'])) {
        setcookie('REMEMBER_ME', 1, 2147483647, '/');
        setcookie(session_name(), session_id(), 2147483647, '/');
    } else {
        $expire = 0;
        setcookie('REMEMBER_ME', 0, 2147483647, '/');
        setcookie(session_name(), session_id(), null, '/');
    }

    $_SESSION['PECL_USER'] = $_POST['PECL_USER'];

    // Determine URL
    if (isset($_POST['redirect_to']) &&
        basename($_POST['redirect_to']) != 'login.php')
    {
        localRedirect($_POST['redirect_to']);
    } else {
        localRedirect('index.php');
    }

    exit;
}

$msg = '';
if (isset($_POST['PECL_USER']) || isset($_POST['PECL_PW'])) {
    $msg = 'Invalid username or password.';
}

$auth->reject($msg);
