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

use App\BorderBox;
use App\Repository\UserRepository;
use App\Utils\PhpMasterClient;

function display_error($msg)
{
    global $errorMsg;

    $errorMsg .= "<font color=\"#CC0000\" size=\"+1\">$msg</font><br />\n";
}

$display_form = true;
$width = 60;
$errorMsg = "";
$jumpto = "handle";

$fields  = [
    'handle',
    'firstname',
    'lastname',
    'email',
    'purpose',
    'sponsor',
    'email',
    'moreinfo',
    'homepage',
    'needsvn',
    'showemail'
];

$password_fields = ['password', 'password2'];

foreach ($fields as $field) {
    $$field = isset($_POST[$field]) ? htmlspecialchars(strip_tags($_POST[$field]),ENT_QUOTES) : null;
}

foreach ($password_fields as $field) {
    $$field = isset($_POST[$field]) ? $_POST[$field] : null;
}

if (isset($_POST['submit'])) {
    do {

        $required = [
            "handle"    => "your desired username",
            "firstname" => "your first name",
            "lastname"  => "your last name",
            "email"     => "your email address",
            "purpose"   => "the purpose of your PECL account",
            "sponsor"   => "references to current users sponsoring your request",
            "language"  => "programming language being developed",
        ];

            $name = $firstname . " " . $lastname;

            foreach ($required as $field => $desc) {
                if (empty($_POST[$field])) {
                    display_error("Please enter $desc!");
                    $jumpto = $field;
                    break 2;
                }
        }

            if (strtolower(trim($_POST['language'])) !== 'php') {
                display_error('That was the wrong language choice');
                $jumpto = "language";
                break;
            }

            if (!preg_match($config->get('valid_usernames_regex'), $handle)) {
                display_error("Username must start with a letter and contain only letters and digits.");
                break;
            }

            if ($password != $password2) {
                display_error("Passwords did not match");
                $password = $password2 = "";
                $jumpto = "password";
                break;
            }

            if (!$password) {
                display_error("Empty passwords not allowed");
                $jumpto = "password";
                break;
            }

            $handle = strtolower($handle);

            $purpose .= "\n\nSponsor:\n" . $sponsor;

            $userRepository = new UserRepository($database);

            if ($userRepository->findByHandle($handle)) {
                display_error("Sorry, that username is already taken");
                $jumpto = "handle";

                break;
            }

            if ($userRepository->findByEmail($email)) {
                display_error("Sorry, that email is already registered in the database");
                $jumpto = "email";

                break;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $showemail = @(bool)$showemail;

            $needsvn = @(bool)$needsvn;

            // Hack to temporarily embed the "purpose" in the user's "userinfo"
            // column
            $userinfo = serialize([$purpose, $moreinfo]);
            $created_at = gmdate('Y-m-d H:i');
            $sql = "INSERT INTO users (
                        handle,
                        name,
                        email,
                        password,
                        registered,
                        showemail,
                        homepage,
                        userinfo,
                        from_site,
                        active,
                        created
                    ) VALUES(
                        ?, ?, ?, ?, 0, ?, ?, ?, 'pecl', 0, ?)
            ";
            $result = $database->run($sql, [$handle, $name, $email, $hash, $showemail ? 1 : 0, $homepage, $userinfo, $created_at]);

            // Now do the SVN stuff
            if ($needsvn) {
                $client = new PhpMasterClient($config->get('php_master_api_url'));

                $error = $client->post([
                    'username' => $handle,
                    'name'     => $name,
                    'email'    => $email,
                    'passwd'   => $password,
                    'note'     => $purpose,
                    'group'    => 'pecl',
                    'yesno'    => 'yes',
                ]);

                if ($error) {
                    display_error("Problem submitting the php.net account request: $error");
                    break;
                }
            }

            $display_form = false;

            $msg = "Requested from:   {$_SERVER['REMOTE_ADDR']}\n".
                   "Username:         {$handle}\n".
                   "Real Name:        {$name}\n".
                   "Email:            {$email}".
                   (@$showemail ? " (show address)" : " (hide address)") . "\n".
                   "Need php.net Account: " . (@$needsvn ? "yes" : "no") . "\n".
                   "Purpose:\n".
                   "$purpose\n\n".
                   'To handle: '.$config->get('scheme').'://'.$config->get('host')."/admin/?acreq={$handle}\n";

            if ($moreinfo) {
                $msg .= "\nMore info:\n$moreinfo\n";
            }

            $xhdr = "From: $name <$email>";
            $subject = "PECL Account Request: {$handle}";
            $ok = mail("pecl-dev@lists.php.net", $subject, $msg, $xhdr, "-f noreply@php.net");
            response_header("Account Request Submitted");
            if ($ok) {
                print "<h2>Account Request Submitted</h2>\n";
                print "Your account request has been submitted, it will ".
                      "be reviewed by a human shortly.  This may take from two ".
                      "minutes to several days, depending on how much time people ".
                      "have.  ".
                      "You will get an email when your account is open, or if ".
                      "your request was rejected for some reason.";
            } else {
                print "<h2>Possible Problem!</h2>\n";
                print "Your account request has been submitted, but there ".
                      "were problems mailing one or more administrators.  ".
                      "If you don't hear anything about your account in a few ".
                      "days, please drop a mail about it to the <i>pecl-dev</i> ".
                      "mailing list.";
            }

            print "<br />Click the top-left PECL logo to go back to the front page.\n";

    } while (0);
}
if ($display_form) {

    response_header("Request Account");

    $cs_link        = '<a href="https://git.php.net/?p=php-src.git;a=blob_plain;f=CODING_STANDARDS;hb=HEAD">PHP Coding Standards</a>';
    $lic_link_pecl  = '<a href="https://php.net/license/3_01.txt">PHP License 3.01</a>';
    $lic_link_doc   = '<a href="https://php.net/manual/en/cc.license.php">Creative Commons Attribution License</a>';
    $doc_howto_pecl = '<a href="https://wiki.php.net/doc/howto/pecldocs">PECL Docs Howto</a>';

    print "<h1>Publishing in PECL</h1>

<p>
 A few reasons why you might apply for a PECL account:
</p>
<ul>
 <li>You have written a PHP extension and would like it listed within the PECL directory</li>
 <li>You would like to use php.net for version control and hosting</li>
 <li>You would like to help maintain a current PECL extension</li>
</ul>
<p>

<p>
 You do <b>not</b> need an account if you want to download, install and/or use PECL packages.
</p>

<p>
 Before filling out this form, you must write the public <i>pecl-dev@lists.php.net</i> mailing list and:
</p>
<ul>
 <li>Introduce yourself</li>
 <li>Introduce your new extension or the extension you would like to help maintain</li>
 <li>Link to the code, if applicable</li>
</ul>

<p>
 Also, here is a list of suggestions:
</p>
<ul>
 <li>
  We strongly encourage contributors to choose the $lic_link_pecl for their extensions,
  in order to avoid possible troubles for end-users of the extension. Other solid
  options are BSD and Apache type licenses.
 </li>
 <li>
  We strongly encourage you to use the $cs_link for your code, as it will help
  the QA team (and others) help maintain the extension.
 </li>
 <li>
  We strongly encourage you to commit documentation for the extension, as it will
  make the extension more visible (in the official PHP manual) and also teach
  users how to use it. See the $doc_howto_pecl for more information.
  Submitted documentation will always be under the $lic_link_doc.
 </li>
 <li>
  Note: wrappers for GPL (all versions) or LGPLv3 libraries will not be accepted.
  Wrappers for libraries licensed under LGPLv2 are however allowed while being discouraged.
 </li>
 <li>
  Note: Wrappers for libraries with license fees or closed sources libraries without licenses fees
  are allowed.
 </li>

</ul>

<p>
 And after submitting the form:
</p>
<ul>
 <li>
  If approved, you will also need to <a href='https://php.net/git-php.php'>apply for a php.net account</a>
  in order to commit the code to the php.net SVN repository. Select 'PECL Group' within that form when applying.
 </li>
</ul>

<p>
 <strong>Please confirm the reason for this PECL account request:</strong>
</p>

<script defer=\"defer\">
    function reasonClick(option)
    {
        if (option == 'pkg') {
            enableForm(true);

            // Lose border
            if (document.getElementById) {
                document.getElementById('reason_table').style.border = '2px dashed green';
            }
        } else {
            // Gain border
            if (document.getElementById) {
                document.getElementById('reason_table').style.border = '2px dashed red';
            }

            alert('Reminder: please only request a PECL account if you will maintain a PECL extension, and have followed the guidelines above.');
            enableForm(false);
        }
    }

    function enableForm(disabled)
    {
        for (var i=0; i<document.forms['request_form'].elements.length; i++) {
            document.forms['request_form'].elements[i].disabled = !disabled;
        }
    }

    enableForm(false);
</script>

<table border=\"0\" style=\"border: 2px #ff0000 dashed; padding: 0px\" id=\"reason_table\">
    <tr>
        <td valign=\"top\"><input type=\"radio\" name=\"reason\" value=\"pkg\" id=\"reason_pkg\" onclick=\"reasonClick('pkg')\" /></td>
        <td>
            <label for=\"reason_pkg\">
                I have already discussed the topic of maintaining and/or adding a PECL extension on the
                pecl-dev@lists.php.net mailing list, and we determined it's time for me to have a PECL account.
            </label>
        </td>
    </tr>

    <tr>
        <td valign=\"top\"><input type=\"radio\" name=\"reason\" value=\"other\" id=\"reason_other\" onclick=\"reasonClick('other')\" /></td>
        <td>
            <label for=\"reason_other\">I desire this PECL account for another reason.</label>
        </td>
    </tr>
</table>
";

    if (isset($errorMsg)) {
        print "<table>\n";
        print " <tr>\n";
        print "  <td>&nbsp;</td>\n";
        print "  <td><b>$errorMsg</b></td>\n";
        print " </tr>\n";
        print "</table>\n";
    }

    print "<form action=\"" . htmlspecialchars($_SERVER['PHP_SELF']) . "\" method=\"post\" name=\"request_form\">\n";
    $bb = new BorderBox("Request a PECL account", "90%", "", 2, true);
    $bb->horizHeadRow("Username:", '<input type="text" name="handle" value="'.$handle.'" size="12" />');
    $bb->horizHeadRow("First Name:", '<input type="text" name="firstname" value="'.$firstname.'" size="20" />');
    $bb->horizHeadRow("Last Name:", '<input type="text" name="lastname" value="'.$lastname.'" size="20" />');
    $bb->horizHeadRow("Password:", '<input type="password" name="password" value="" size="10" />   Again: <input type="password" name="password2" value="" size="10" />');
    $bb->horizHeadRow("Need a php.net account?", '<input type="checkbox" name="needsvn" '.($needsvn ? 'checked="checked"' : '').' />');
    $bb->horizHeadRow("Email address:", '<input type="text" name="email" value="'.$email.'" size="20" />');
    $bb->horizHeadRow("Show email address?", '<input type="checkbox" name="showemail" '.($showemail ? 'checked="checked"' : '').' />');
    $bb->horizHeadRow("Homepage", '<input type="text" name="homepage" value="'.$homepage.'" size="20" />');
    $bb->horizHeadRow("Purpose of your PECL account<br />(No account is needed for using PECL or PECL packages):", '<textarea name="purpose" cols="40" rows="5">'.stripslashes($purpose).'</textarea>');
    $bb->horizHeadRow("Sponsoring users<br />(Current php.net users who suggested you request an account and reviewed your extension/patch):", '<textarea name="sponsor" cols="40" rows="5">'.stripslashes($sponsor).'</textarea>');
    $bb->horizHeadRow("More relevant information<br />about you (optional):", '<textarea name="moreinfo" cols="40" rows="5">'.stripslashes($moreinfo).'</textarea>');
    $bb->horizHeadRow("Which programming language is developed at php.net (spam protection):", '<input type="text" name="language" value="" size="20" />');
    $bb->horizHeadRow("Requested from IP address:", $_SERVER['REMOTE_ADDR']);
    $bb->horizHeadRow("<input type=\"submit\" name=\"submit\" value=\"Submit\" />");
    $bb->end();
    print "</form>";

    if ($jumpto) {
        print "<script>\n";
        print "if (!document.forms[1].$jumpto.disabled) document.forms[1].$jumpto.focus();\n";
        print "</script>\n";
    }
}

response_footer();
