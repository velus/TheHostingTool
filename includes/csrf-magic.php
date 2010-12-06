<?php

// Check if called by script
if(THT != 1){die();}

/*
 * We had to modified CSRF-Magic a bit to work properly with THT.
 * We highly reccomend that you refrain from changing the contents
 * of this file in any way unless you really know what you're doing.
 */

/**
 * @file
 *
 * csrf-magic is a PHP library that makes adding CSRF-protection to your
 * web applications a snap. No need to modify every form or create a database
 * of valid nonces; just include this file at the top of every
 * web-accessible page (or even better, your common include file included
 * in every page), and forget about it! (There are, of course, configuration
 * options for advanced users).
 *
 * This library is PHP4 and PHP5 compatible.
 */

// CONFIGURATION:

/**
 * By default, when you include this file csrf-magic will automatically check
 * and exit if the CSRF token is invalid. This will defer executing
 * csrf_check() until you're ready.  You can also pass false as a parameter to
 * that function, in which case the function will not exit but instead return
 * a boolean false if the CSRF check failed. This allows for tighter integration
 * with your system.
 */
$GLOBALS['csrf']['defer'] = false;

/**
 * This is the amount of seconds you wish to allow before any token becomes
 * invalid; the default is two hours, which should be more than enough for
 * most websites.
 */
$GLOBALS['csrf']['expires'] = 7200;

/**
 * Callback function to execute when there's the CSRF check fails and
 * $fatal == true (see csrf_check). This will usually output an error message
 * about the failure.
 */
$GLOBALS['csrf']['callback'] = 'csrf_callback';

/**
 * Whether or not to include our JavaScript library which also rewrites
 * AJAX requests on this domain. Set this to the web path. This setting only works
 * with supported JavaScript libraries in Internet Explorer; see README.txt for
 * a list of supported libraries.
 */
$GLOBALS['csrf']['rewrite-js'] = true;

/**
 * A secret key used when hashing items. Please generate a random string and
 * place it here. If you change this value, all previously generated tokens
 * will become invalid.
 */
$GLOBALS['csrf']['secret'] = csrf_get_secret();

/**
 * Set this to false to disable csrf-magic's output handler, and therefore,
 * its rewriting capabilities. If you're serving non HTML content, you should
 * definitely set this false.
 */
$GLOBALS['csrf']['rewrite'] = true;

/**
 * Whether or not to use IP addresses when binding a user to a token. This is
 * less reliable and less secure than sessions, but is useful when you need
 * to give facilities to anonymous users and do not wish to maintain a database
 * of valid keys.
 */
$GLOBALS['csrf']['allow-ip'] = true;

/**
 * If this information is available, use the cookie by this name to determine
 * whether or not to allow the request. This is a shortcut implementation
 * very similar to 'key', but we randomly set the cookie ourselves.
 */
$GLOBALS['csrf']['cookie'] = '__tht_csrf_cookie';

/**
 * If this information is available, set this to a unique identifier (it
 * can be an integer or a unique username) for the current "user" of this
 * application. The token will then be globally valid for all of that user's
 * operations, but no one else. This requires that 'secret' be set.
 */
$GLOBALS['csrf']['user'] = false;

/**
 * This is an arbitrary secret value associated with the user's session. This
 * will most probably be the contents of a cookie, as an attacker cannot easily
 * determine this information. Warning: If the attacker knows this value, they
 * can easily spoof a token. This is a generic implementation; sessions should
 * work in most cases.
 *
 * Why would you want to use this? Lets suppose you have a squid cache for your
 * website, and the presence of a session cookie bypasses it. Let's also say
 * you allow anonymous users to interact with the website; submitting forms
 * and AJAX. Previously, you didn't have any CSRF protection for anonymous users
 * and so they never got sessions; you don't want to start using sessions either,
 * otherwise you'll bypass the Squid cache. Setup a different cookie for CSRF
 * tokens, and have Squid ignore that cookie for get requests, for anonymous
 * users. (If you haven't guessed, this scheme was(?) used for MediaWiki).
 */
$GLOBALS['csrf']['key'] = false;

/**
 * The name of the magic CSRF token that will be placed in all forms, i.e.
 * the contents of <input type="hidden" name="$name" value="CSRF-TOKEN" />
 */
$GLOBALS['csrf']['input-name'] = '__tht_csrf_magic';

/**
 * Set this to false if your site must work inside of frame/iframe elements,
 * but do so at your own risk: this configuration protects you against CSS
 * overlay attacks that defeat tokens.
 */
$GLOBALS['csrf']['frame-breaker'] = false;

/**
 * Whether or not CSRF Magic should be allowed to start a new session in order
 * to determine the key.
 */
$GLOBALS['csrf']['auto-session'] = true;

/**
 * Whether or not csrf-magic should produce XHTML style tags.
 */
$GLOBALS['csrf']['xhtml'] = true;

// FUNCTIONS:

// Don't edit this!
$GLOBALS['csrf']['version'] = '1.0.1';

/**
 * Rewrites <form> on the fly to add CSRF tokens to them. This can also
 * inject our JavaScript library.
 */
function csrf_ob_handler($buffer, $flags) {
    // Even though the user told us to rewrite, we should do a quick heuristic
    // to check if the page is *actually* HTML. We don't begin rewriting until
    // we hit the first <html tag.
    static $is_html = false;
    if (!$is_html) {
        // not HTML until proven otherwise
        if (stripos($buffer, '<html') !== false) {
            $is_html = true;
        } else {
            return $buffer;
        }
    }
    $tokens = csrf_get_tokens();
    $name = $GLOBALS['csrf']['input-name'];
    $endslash = $GLOBALS['csrf']['xhtml'] ? ' /' : '';
    $input = "<input type='hidden' name='$name' value=\"$tokens\"$endslash>";
    $buffer = preg_replace('#(<form[^>]*method\s*=\s*["\']post["\'][^>]*>)#i', '$1' . $input, $buffer);
    if ($GLOBALS['csrf']['frame-breaker']) {
        $buffer = str_ireplace('</head>', '<script type="text/javascript">if (top != self) {top.location.href = self.location.href;}</script></head>', $buffer);
    }
    if ($js = $GLOBALS['csrf']['rewrite-js']) {
        $buffer = str_ireplace(
            '<head>',
            '<head>'."\n".'<script type="text/javascript">'.
                'var csrfMagicToken = "'.$tokens.'";'.
                'var csrfMagicName = "'.$name.'";</script>',
            $buffer
        );
        if (!$count) {
            $buffer .= $script;
        }
    }
    return $buffer;
}

/**
 * Checks if this is a post request, and if it is, checks if the nonce is valid.
 * @param bool $fatal Whether or not to fatally error out if there is a problem.
 * @return True if check passes or is not necessary, false if failure.
 */
function csrf_check($fatal = true) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return true;
    csrf_start();
    $name = $GLOBALS['csrf']['input-name'];
    $ok = false;
    $tokens = '';
    do {
        if (!isset($_POST[$name])) break;
        // we don't regenerate a token and check it because some token creation
        // schemes are volatile.
        $tokens = $_POST[$name];
        if (!csrf_check_tokens($tokens)) break;
        $ok = true;
    } while (false);
    if ($fatal && !$ok) {
        $callback = $GLOBALS['csrf']['callback'];
        if (trim($tokens, 'A..Za..z0..9:;,') !== '') $tokens = 'hidden';
        $callback($tokens);
        exit;
    }
    return $ok;
}

/**
 * Retrieves a valid token(s) for a particular context. Tokens are separated
 * by semicolons.
 */
function csrf_get_tokens() {
    $has_cookies = !empty($_COOKIE);

    // $ip implements a composite key, which is sent if the user hasn't sent
    // any cookies. It may or may not be used, depending on whether or not
    // the cookies "stick"
    if (!$has_cookies && $secret) {
        // :TODO: Harden this against proxy-spoofing attacks
        $ip = ';ip:' . csrf_hash($_SERVER['IP_ADDRESS']);
    } else {
        $ip = '';
    }
    csrf_start();

    // These are "strong" algorithms that don't require per se a secret
    if (session_id()) return 'sid:' . csrf_hash(session_id()) . $ip;
    if ($GLOBALS['csrf']['cookie']) {
        $val = csrf_generate_secret();
        setcookie($GLOBALS['csrf']['cookie'], $val);
        return 'cookie:' . csrf_hash($val) . $ip;
    }
    if ($GLOBALS['csrf']['key']) return 'key:' . csrf_hash($GLOBALS['csrf']['key']) . $ip;
    // These further algorithms require a server-side secret
    if ($secret === '') return 'invalid';
    if ($GLOBALS['csrf']['user'] !== false) {
        return 'user:' . csrf_hash($GLOBALS['csrf']['user']);
    }
    if ($GLOBALS['csrf']['allow-ip']) {
        return ltrim($ip, ';');
    }
    return 'invalid';
}

/**
 * @param $tokens is safe for HTML consumption
 */
function csrf_callback($tokens) {
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden');
    echo "<html><head><title>Possible CSRF attack detected.</title></head><body>Possible CSRF attack detected. Please make sure cookies are enabled.</body></html>
";
}

/**
 * Checks if a composite token is valid. Outward facing code should use this
 * instead of csrf_check_token()
 */
function csrf_check_tokens($tokens) {
    if (is_string($tokens)) $tokens = explode(';', $tokens);
    foreach ($tokens as $token) {
        if (csrf_check_token($token)) return true;
    }
    return false;
}

/**
 * Checks if a token is valid.
 */
function csrf_check_token($token) {
    if (strpos($token, ':') === false) return false;
    list($type, $value) = explode(':', $token, 2);
    if (strpos($value, ',') === false) return false;
    list($x, $time) = explode(',', $token, 2);
    if ($GLOBALS['csrf']['expires']) {
        if (time() > $time + $GLOBALS['csrf']['expires']) return false;
    }
    switch ($type) {
        case 'sid':
            return $value === csrf_hash(session_id(), $time);
        case 'cookie':
            $n = $GLOBALS['csrf']['cookie'];
            if (!$n) return false;
            if (!isset($_COOKIE[$n])) return false;
            return $value === csrf_hash($_COOKIE[$n], $time);
        case 'key':
            if (!$GLOBALS['csrf']['key']) return false;
            return $value === csrf_hash($GLOBALS['csrf']['key'], $time);
        // We could disable these 'weaker' checks if 'key' was set, but
        // that doesn't make me feel good then about the cookie-based
        // implementation.
        case 'user':
            if ($GLOBALS['csrf']['secret'] === '') return false;
            if ($GLOBALS['csrf']['user'] === false) return false;
            return $value === csrf_hash($GLOBALS['csrf']['user'], $time);
        case 'ip':
            if (csrf_get_secret() === '') return false;
            // do not allow IP-based checks if the username is set, or if
            // the browser sent cookies
            if ($GLOBALS['csrf']['user'] !== false) return false;
            if (!empty($_COOKIE)) return false;
            if (!$GLOBALS['csrf']['allow-ip']) return false;
            return $value === csrf_hash($_SERVER['IP_ADDRESS'], $time);
    }
    return false;
}

/**
 * Sets a configuration value.
 */
function csrf_conf($key, $val) {
    if (!isset($GLOBALS['csrf'][$key])) {
        trigger_error('No such configuration ' . $key, E_USER_WARNING);
        return;
    }
    $GLOBALS['csrf'][$key] = $val;
}

/**
 * Starts a session if we're allowed to.
 */
function csrf_start() {
    if ($GLOBALS['csrf']['auto-session'] && !session_id()) {
        session_start();
    }
}

/**
 * Retrieves the secret, and generates one if necessary.
 */
function csrf_get_secret() {
    if ($GLOBALS['csrf']['secret']) return $GLOBALS['csrf']['secret'];
    $dir = dirname(__FILE__);
    $file = $dir . '/csrf-secret.php';
    $secret = '';
    if (file_exists($file)) {
        include $file;
        return $secret;
    }
    if (is_writable($dir)) {
        $secret = csrf_generate_secret();
        $fh = fopen($file, 'w');
        fwrite($fh, '<?php $secret = "'.$secret.'";' . PHP_EOL);
        fclose($fh);
        return $secret;
    }
    return '';
}

/**
 * Generates a random string as the hash of time, microtime, and mt_rand.
 */
function csrf_generate_secret($len = 32) {
    $secret = '';
    for ($i = 0; $i < 32; $i++) {
        $secret .= chr(mt_rand(0, 255));
    }
    $secret .= time() . microtime();
    return strtoupper(sha1($secret));
}

/**
 * Generates a hash/expiry double. If time isn't set it will be calculated
 * from the current time.
 */
function csrf_hash($value, $time = null) {
    if (!$time) $time = time();
    return sha1($secret . $value . $time) . ',' . $time;
}

// Load user configuration
if (function_exists('csrf_startup')) csrf_startup();
// Initialize our handler
if ($GLOBALS['csrf']['rewrite'])     ob_start('csrf_ob_handler');
// Perform check
if (!$GLOBALS['csrf']['defer'])      csrf_check();
