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
  | Authors: Stig S. Bakken <ssb@fast.no>                                |
  |          Tomas V.V.Cox <cox@php.net>                                 |
  |          Richard Heyes <richard@php.net>                             |
  |          Martin Jansen <mj@php.net>                                  |
  |          Wez Furlong <wez@php.net>                                   |
  |          Greg Beaver <cellog@php.net>                                |
  |          Ferenc Kovacs <tyrael@php.net>                              |
  |          Pierre Joye <pierre@php.net>                                |
  |          Rasmus Lerdorf <rasmus@php.net>                             |
  |          Peter Kokot <petk@php.net>                                  |
  +----------------------------------------------------------------------+
*/

use App\Entity\User as UserEntity;
use App\Karma;

function auth_reject($message = null)
{
    if ($message === null) {
        $message = "Please enter your username and password:";
    }

    response_header('Login');

    $GLOBALS['ONLOAD'] = "document.login.PECL_USER.focus();";

    if ($message) {
        report_error($message);
    }

    print "<form name=\"login\" action=\"/login.php\" method=\"post\">\n";
    print '<table class="form-holder" cellspacing="1">' . "\n";
    print " <tr>\n";
    print '  <th class="form-label_left">';
    print 'Use<span class="accesskey">r</span>name:</th>' . "\n";
    print '  <td class="form-input">';
    print '<input size="20" name="PECL_USER" accesskey="r" /></td>' . "\n";
    print " </tr>\n";
    print " <tr>\n";
    print '  <th class="form-label_left">Password:</th>' . "\n";
    print '  <td class="form-input">';
    print '<input size="20" name="PECL_PW" type="password" /></td>' . "\n";
    print " </tr>\n";
    print " <tr>\n";
    print '  <th class="form-label_left">&nbsp;</th>' . "\n";
    print '  <td class="form-input" style="white-space: nowrap">';
    print '<input type="checkbox" name="PECL_PERSIST" value="on" id="pecl_persist_chckbx" '.((!empty($_COOKIE['REMEMBER_ME']) || !empty($_POST['PECL_PERSIST']))?'checked="checked " ':'').'/> ';
    print '<label for="pecl_persist_chckbx">Remember username and password.</label></td>' . "\n";
    print " </tr>\n";
    print " <tr>\n";
    print '  <th class="form-label_left">&nbsp;</td>' . "\n";
    print '  <td class="form-input"><input type="submit" value="Log in!" /></td>' . "\n";
    print " </tr>\n";
    print "</table>\n";
    print '<input type="hidden" name="redirect_to" value="';
    if (isset($_POST['redirect_to'])) {
        print htmlspecialchars($_POST['redirect_to'], ENT_QUOTES);
    } elseif (isset($_SERVER['REQUEST_URI'])) {
        print htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES);
    } else {
        print 'login.php';
    }
    print "\" />\n";
    print "</form>\n";
    print '<hr>';
    print "<p><strong>Note:</strong> If you just want to browse the website, ";
    print "you will not need to log in. For all tasks that require ";
    print "authentication, you will be redirected to this form ";
    print "automatically. You can sign up for an account ";
    print "<a href=\"/account-request.php\">over here</a>.</p>";

    response_footer();
    exit;
}

/**
 * Verify user + pass against the database
 */
function auth_verify($user, $passwd)
{
    global $database, $auth_user, $config;

    if (empty($auth_user)) {
        $auth_user = new UserEntity($database, $user);
    }

    $error = '';
    $ok = false;

    switch (strlen($auth_user->get('password'))) {
        // Handle old-style DES-encrypted passwords
        case 13:
            $seed = substr($auth_user->get('password'), 0, 2);
            $crypted = crypt($passwd, $seed);

            if ($crypted === $auth_user->get('password')) {
                $ok = true;
            } else {
                $error = "pear-auth: user `$user': invalid password (des)";
            }

            break;

        // Handle old MD5-hashed passwords and update them to password_hash()
        case 32:
            $crypted = md5($passwd);

            if ($crypted === $auth_user->get('password')) {
                $sql = "UPDATE users SET password = ? WHERE handle = ?";
                $arguments = [password_hash($passwd, PASSWORD_DEFAULT), $user];
                $database->run($sql, $arguments);

                $ok = true;
            } else {
                $error = "pear-auth: user `$user': invalid password (md5)";
            }

            break;

        default:
            if (password_verify($passwd, $auth_user->get('password'))) {
                $ok = true;
            } else {
                $error = "pear-auth: user `$user': invalid password (password_verify)";
            }

            break;
    }

    if (empty($auth_user->get('registered'))) {
        if ($user) {
            $error = "pear-auth: user `$user' not registered";
        }

        $ok = false;
    }

    if ($ok) {
        $auth_user->_readonly = true;

        return auth_check("pear.user");
    }

    if ($error) {
        error_log("$error\n", 3, $config->get('tmp_dir').'/pear-errors.log');
    }

    $auth_user = null;

    return false;
}

/**
 * ACL check for the given $atom, where true means pear.admin, false pear.dev
 */
function auth_check($atom)
{
    global $database;
    static $karma;

    global $auth_user;

    // Admins are almighty
    if ($auth_user->isAdmin()) {
        return true;
    }

    // Check for backwards compatibility
    if (is_bool($atom)) {
        if ($atom == true) {
            $atom = "pear.admin";
        } else {
            $atom = "pear.dev";
        }
    }

    // Every authenticated user has the pear.user and pear.dev karma
    if (in_array($atom, ["pear.user", "pear.dev"])) {
        return true;
    }

    if (!isset($karma)) {
        $karma = new Karma($database);
    }

    return $karma->has($auth_user->handle, $atom);
}

function auth_require($admin = false)
{
    global $auth_user;
    $res = true;

    if (!is_logged_in()) {
        // Exits
        auth_reject();
    }

    $num = func_num_args();
    for ($i = 0; $i < $num; $i++) {
        $arg = func_get_arg($i);
        $res = auth_check($arg);
        if ($res == true) {
            return true;
        }
    }

    if ($res == false) {
        response_header("Insufficient Privileges");
        report_error("Insufficient Privileges");
        response_footer();
        exit;
    }

    return true;
}

/**
 * Perform logout for the current user.
 */
function auth_logout()
{
    session_unset();
    if ($_SERVER['QUERY_STRING'] == 'logout=1') {
        localRedirect($_SERVER['PHP_SELF']);
    } else {
        localRedirect($_SERVER['PHP_SELF'] . '?' .
                   preg_replace('/logout=1/',
                                '', $_SERVER['QUERY_STRING']));
    }
}

/**
 * Check if the user is logged in.
 */
function is_logged_in()
{
    global $auth_user;

    if (!$auth_user || !$auth_user->get('registered')) {
        return false;
    } else {
        return true;
    }
}

/**
 * Setup the $auth_user object.
 */
function init_auth_user()
{
    global $database, $auth_user;

    if (empty($_SESSION['PECL_USER'])) {
        $auth_user = null;

        return false;
    }

    if (!empty($auth_user)) {
        return true;
    }

    $auth_user = new UserEntity($database, $_SESSION['PECL_USER']);

    if (is_logged_in()) {
        return true;
    }

    $auth_user = null;

    return false;
}
