<?php
require_once dirname(__FILE__) . '/GoogleHttpClient.php';

use g3\GoogleToken;
use g3\GoogleHttpClient;

$token = new GoogleToken();
$token->set('log', true);
$client = new GoogleHttpClient($token, $token->get('log'));

/* STEP 1 */
// follow next link and Google redirects with code=...
echo $client->createAuthUrl();
echo '<br/><a href="'.$client->createAuthUrl().'">authenticate</a>';

//$code = "4/e_H1Yj...meQnE";

/* STEP 2 */
// look at log file
//$client->authenticate($code);

/* STEP 3 */
// copy from log file 'access_token' value
//$client->verify("ya29.GlsmBd3F796AR0...z0rdz");

/* STEP 4 */
// copy from log file 'access_token' value
//$client->userInfo("ya29.Glsg...MW9r2");
