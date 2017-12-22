<?php
require_once dirname(__FILE__) . '/GoogleHttpClient.php';

use auth\GoogleToken;
use auth\GoogleHttpClient;

$token = new GoogleToken();
$token->set('log', true);
$client = new GoogleHttpClient($token, $token->get('log'));

/* STEP 1 */
// follow next link and Google will make a redirection with code=...
echo $client->createAuthUrl();
echo '<br/><a href="'.$client->createAuthUrl().'">authenticate</a>';

// comment STEP 1
// replace variable value with the value of the url part '&code=...'
//$code = "4/e_H1Yj...meQnE";
// uncomment STEP 2

/* STEP 2 */
//$client->authenticate($code);

// comment STEP 2
// look at log file
// copy from log file the 'access_token' value and replace argument
// uncomment STEP 3

/* STEP 3 */
//$client->verify("ya29.GlsmBd3F796AR0...z0rdz");

// comment STEP 3
// copy from log file the 'access_token' value and replace argument
// uncomment STEP 4

/* STEP 4 */
//$client->userInfo("ya29.Glsg...MW9r2");
