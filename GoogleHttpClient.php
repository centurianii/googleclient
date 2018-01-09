<?php
require_once dirname(__FILE__) . '/Utils.php';
require_once dirname(__FILE__) . '/GoogleToken.php';

namespace auth;

use g3\Utils;

/**
 * The GoogleHttpClient is a helper class that is called from @see GoogleCommand.
 * 
 * As this class is called between different requests, it needs access to a state
 * storage mechanism like sessions.
 * Even though the methods try to abstract away the complexity of the calls by 
 * a logical organisation of the actions involved, you are responsible for the 
 * order or context under which they are called.
 */
class GoogleHttpClient {
   /** @var GoogleToken $token The GoogleToken class object */
   protected $token;
   
   /** 
    * @var string[] $auth The authorization data returned by Google in the form:
    * Array(
    *    [access_token] => xxx
    *    [expires_in] => [unix timestamp]
    *    [id_token] => xxx.xxx.xxx
    *    [refresh_token] => xxx
    *    [token_type] => Bearer
    * )
    * 
    * This property should be stored in session (per user) in case we need a 
    * verification or refresh.
    */
   protected $auth;
   
   /** 
    * @var string[] $verify The response data returned by Google in the form:
    * Array(
    *    [issued_to] => xxx.apps.googleusercontent.com (@see GoogleToken['values']['client_id'])
    *    [audience] => xxx.apps.googleusercontent.com (@see GoogleToken['values']['client_id'])
    *    [user_id] => xxx
    *    [scope] => xxx (@see createAuthUrl())
    *    [expires_in] => xxx (<= 3600)
    *    [email] => xxx
    *    [verified_email] => 1
    *    [access_type] => xxx (@see createAuthUrl())
    * )
    * 
    * You don't have to store this property in session.
    */
   protected $verify;
   
   /** 
    * @var string[] $user The user data returned by Google in the form:
    * Array(
    *    [id] => xxx
    *    [email] => xxx@gmail.com
    *    [email_verified] => 1
    *    [name] => xxx xxx (given_name family_name)
    *    [given_name] => xxx
    *    [family_name] => xxx
    *    [picture] => https://lh6.googleusercontent.com/.../photo.jpg?sz=50
    *    [locale] => en
    * )
    * 
    * You should verify this property with your native authorization system and
    * integrate with it finally.
    */
   protected $user;
   
   /** 
    * @var boolean $log If you should log errors and assigned properties for debuging
    */
   protected $log;
   
   /**
    * Constructor.
    * 
    * preconditions: a GoogleToken should be passed.
    *
    * postconditions: 
    * 
    * @param GoogleToken $token The GoogleToken that contains initialization values
    * @param boolean $log True, if we want to log errors and assigned properties
    * 
    * @return GoogleHttpClient A GoogleHttpClient object
    */
   public function __construct(GoogleToken $token, $log = false) {
      $this->token = $token;
      $this->log = $log;
   }
   
   /**
    * It makes a authentication request to Google.
    * 
    * preconditions: after the call to Google at this link, @see createAuthUrl(), 
    *    Google responds by making a call back with a unique id that should be
    *    passed as argument.
    *
    * postconditions: it sets properties $auth, $user and $verify. 
    *    On error, it returns false and some properties might have been set, 
    *    @see verify(), @see validate().
    *    If the authentication succeeds then, it returns true.
    * 
    * @param string $code A unique request id issued by Google
    * 
    * @return boolean True on success, false on failure to authenticate
    */
   public function authenticate($code = null) {
      if (!$code) {
         if ($this->log)
            error_log(__CLASS__ . '::authenticate() error: $code is null.');
         return false;
      }
      $client_id = $this->token->get('client_id');
      $client_secret  = $this->token->get('client_secret');
      $redirect_uri = $this->token->get('redirect_uri');
      $url = $this->token->get('token_endpoint');
      $this->token->set('code', $code); // see getFields()
      $curlPost = $this->getFields($this->token->get('token_fields'));
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_CAINFO, $this->token->get('cert_path'));
      //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
      curl_setopt($ch, CURLOPT_RESOLVE, [$this->token->get('accounts.google.com')]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
      if ($this->log) {
         curl_setopt($ch, CURLOPT_VERBOSE, true);
         $fh = \fopen($this->token->get('curl_log'), 'w+');
         curl_setopt($ch, CURLOPT_STDERR, $fh);
      }
      $buffer = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($this->log) {
         \fclose($fh);
      }
      if ($http_code != 200) {
         $log = __CLASS__ . '::authenticate() error: http code not 200. Responded: '.print_r($buffer, true);
         $return = false;
      } else {
         $data = \json_decode($buffer, true);
         $this->auth = $data;
         $return = true;
         $log = __CLASS__ . '::authenticate() returns '.$return.' and sets this->auth='.print_r($data, true);
      }
      if ($this->log)
         error_log($log);
      return $return;
   }
   
   /**
    * It requests for a verification token from Google.
    * 
    * preconditions: it is called from @see authenticate().
    *    Calling this method from redirects means all properties are null and we 
    *    pass session data.
    *
    * postconditions: it sets property $verify. On error, it returns false.
    * 
    * @param string $access_token Google's authorization response under key 'access_token'
    * 
    * @return boolean True on success, false on failure to accept verification token
    */
   public function verify($access_token = null) {
      if (!$access_token) {
         if ($this->log)
            error_log(__CLASS__ . '::verify() error: $access_token is null.');
         return false;
      }
      $url = $this->token->get('verify_endpoint');
      $curlPost = 'access_token='. $access_token;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_URL, $url.'?'.$curlPost);
      curl_setopt($ch, CURLOPT_CAINFO, $this->token->get('cert_path'));
      //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
      curl_setopt($ch, CURLOPT_RESOLVE, [$this->token->get('www.googleapis.com')]);
      if ($this->log) {
         curl_setopt($ch, CURLOPT_VERBOSE, true);
         $fh = \fopen($this->token->get('curl_log'), 'a+');
         curl_setopt($ch, CURLOPT_STDERR, $fh);
      }
      $buffer = curl_exec($ch);
      $info = curl_getinfo($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($this->log) {
         \fclose($fh);
      }
      if ($http_code != 200) {
         $log = __CLASS__ . '::verify() error: http code not 200. Responded: '.print_r($buffer, true);
         $return = false;
      } else {
         $buffer = (isset($info["header_size"]))? \substr($buffer, $info["header_size"]) : "";
         $data = \json_decode($buffer, true);
         $this->verify = $data;
         $log = __CLASS__ . '::verify() sets this->verify='.print_r($data, true);
         $return = true;
      }
      if ($this->log)
         error_log($log);
      return $return;
   }
   
   /**
    * It compares properties $verify with $user and GoogleToken.
    * 
    * preconditions: it is called from @see authenticate().
    *    Calling this method from redirects means all properties are null and we 
    *    pass session data.
    *
    * postconditions: on error, it returns false.
    * 
    * @param string[] $verify_token Google's verification response token
    * @param string[] $user Google's user info response
    * 
    * @return boolean True on success, false on failure to validate
    */
   public function validate($verify_token = null, $user = null) {
      // 1. Check arguments
      $arr = array();
      $return = true;
      if (!$verify_token) {
         $arr[] = 'argument $verify_token is null';
      }
      if (!$user) {
         $arr[] = 'argument $user is null';
      }
      if ($verify_token && !isset($verify_token['user_id']) && !isset($verify_token['sub'])) {
         $arr[] = 'key \'user_id\' or \'sub\' at argument $verify_token does not exist';
      } elseif (isset($verify_token['sub'])) {
         $verify_token['user_id'] = $verify_token['sub'];
      }
      if ($user && !isset($user['id'])) {
         $arr[] = 'key \'id\' at argument $user does not exist';
      }
      if ($verify_token && !isset($verify_token['email'])) {
         $arr[] = 'key \'email\' at argument $verify_token does not exist';
      }
      if ($user && !isset($user['email'])) {
         $arr[] = 'key \'email\' at argument $user does not exist';
      }
      if ($verify_token && !isset($verify_token['issued_to']) && !isset($verify_token['aud'])) {
         $arr[] = 'key \'issued_to\' or \'aud\' at argument $verify_token does not exist';
      } elseif (isset($verify_token['aud'])) {
         $verify_token['issued_to'] = $verify_token['aud'];
      }
      if(\count($arr)) {
         $log = __CLASS__ . '::validate() ' . \implode(', ', $arr);
         $return = false;
      }
      // 2. Validate
      if ($return) {
         if ($verify_token['user_id'] != $user['id']) {
            $arr[] = 'user\'s id mismatch!';
         }
         if ($verify_token['email'] != $user['email']) {
            $arr[] = 'user\'s email mismatch!';
         }
         if ($verify_token['issued_to'] != $this->token->get('client_id')) {
            $arr[] = 'application id mismatch!';
         }
         if(\count($arr)) {
            $log = __CLASS__ . '::validate() ' . \implode(', ', $arr);
            $return = false;
         } else {
            $log = __CLASS__ . '::validate() is true';
         }
      }
      if ($this->log)
         error_log($log);
      return $return;
   }
   
   /**
    * It is called when property $auth has expired.
    * 
    * preconditions: authentication has been run, @see authenticate().
    *    Calling this method from redirects means all properties are null and we 
    *    pass session data.
    *
    * postconditions: it re-sets property $auth. On error, it returns false.
    * 
    * @param string $refresh_token Google's authorization response under key 'refresh_token'
    * 
    * @return boolean True on success, false on failure to get a new token
    */
   public function refresh($refresh_token = null) {
      if (!$refresh_token) {
         if ($this->log)
            error_log(__CLASS__ . '::refresh() error: $refresh_token is null.');
         return false;
      }
      $url = $this->token->get('refresh_endpoint');
      $this->token->set('refresh_token', $refresh_token); // see getFields()
      $curlPost = $this->getFields($this->token->get('refresh_fields'));
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_CAINFO, $this->token->get('cert_path'));
      //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
      curl_setopt($ch, CURLOPT_RESOLVE, [$this->token->get('accounts.google.com')]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
      if ($this->log) {
         curl_setopt($ch, CURLOPT_VERBOSE, true);
         $fh = \fopen($this->token->get('curl_log'), 'a+');
         curl_setopt($ch, CURLOPT_STDERR, $fh);
      }
      $buffer = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($this->log) {
         \fclose($fh);
      }
      if ($http_code != 200) {
         $log = __CLASS__ . '::refresh() error: http code not 200. Responded: '.print_r($buffer, true);
         $return = false;
      } else {
         $data = \json_decode($buffer, true);
         $this->auth = $data;
         $log = __CLASS__ . '::refresh() sets this->auth='.print_r($data, true);
         $return = true;
      }
      if ($this->log)
         error_log($log);
      return $return;
   }
   
   /**
    * It un-registers application from user's account but id does not (and cannot)
    * logout user from Google.
    * 
    * preconditions: authentication has been run, @see authenticate().
    *    Calling this method from redirects means all properties are null and we 
    *    pass session data.
    *
    * postconditions: it un-registers application from user's account and nullifies
    *    all properties. On error, it returns false.
    * 
    * @param string $access_token Google's authorization response under key 'access_token'
    * 
    * @return boolean True on success, false on failure to make a revocation
    */
   public function revokeToken($access_token = null) {
      if (!$access_token) {
         if ($this->log)
            error_log(__CLASS__ . '::revokeToken() error: $access_token is null.');
         return false;
      }
      $url = $this->token->get('revocation_endpoint');
      //$curlPost = \http_build_query(array('token' => $access_token));
      $curlPost = 'token=' . $access_token;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_CAINFO, $this->token->get('cert_path'));
      //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
      curl_setopt($ch, CURLOPT_RESOLVE, [$this->token->get('accounts.google.com')]);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
      if ($this->log) {
         curl_setopt($ch, CURLOPT_VERBOSE, true);
         $fh = \fopen($this->token->get('curl_log'), 'a+');
         curl_setopt($ch, CURLOPT_STDERR, $fh);
      }
      $buffer = curl_exec($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($this->log) {
         \fclose($fh);
      }
      if ($http_code != 200) {
         $log = __CLASS__ . '::revokeToken() error: http code not 200. Responded: '.print_r($buffer, true);
         $return = false;
      } else {
         $this->auth = null;
         $this->user = null;
         $this->verify = null;
         $log = __CLASS__ . '::revokeToken() run and erased all properties!';
         $return = true;
      }
      if ($this->log)
         error_log($log);
      return $return;
   }
   
   /**
    * It is called from @see authenticate() or you can call it independently at 
    * a later time.
    * 
    * preconditions: authentication has been run, @see authenticate().
    *    Calling this method from redirects means all properties are null and we 
    *    pass session data.
    *
    * postconditions: it sets property $user. On error, it returns false.
    * Error response: Array (
    *     [error] => Array
    *         (
    *             [errors] => Array
    *                 (
    *                     [0] => Array
    *                         (
    *                             [domain] => global
    *                             [reason] => authError
    *                             [message] => Invalid Credentials
    *                             [locationType] => header
    *                             [location] => Authorization
    *                         )
    * 
    *                 )
    *             [code] => 401
    *             [message] => Invalid Credentials
    *         )
    *     )
    * 
    * @param string $access_token Google's authorization response under key 'access_token'
    * 
    * @return string[] A hash array of user's data or false on failure to retrieve data
    */
   public function userInfo($access_token = null) {
      if (!$access_token) {
         if ($this->log)
            error_log(__CLASS__ . '::userInfo() error: $access_token is null.');
         return false;
      }
      $url = $this->token->get('userinfo_endpoint');
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);
      curl_setopt($ch, CURLOPT_CAINFO, $this->token->get('cert_path'));
      //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
      //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
      curl_setopt($ch, CURLOPT_HEADER, TRUE);
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:', 'Authorization: Bearer '. $access_token));
      curl_setopt($ch, CURLOPT_RESOLVE, [$this->token->get('www.googleapis.com')]);
      if ($this->log) {
         curl_setopt($ch, CURLOPT_VERBOSE, true);
         $fh = \fopen($this->token->get('curl_log'), 'a+');
         curl_setopt($ch, CURLOPT_STDERR, $fh);
      }
      $buffer = curl_exec($ch);
      $info = curl_getinfo($ch);
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);
      if ($this->log) {
         \fclose($fh);
      }
      if ($http_code != 200) {
         $log = __CLASS__ . '::userInfo() error: http code not 200. Responded: '.print_r($buffer, true);
         $return = false;
      } else {
         $buffer = (isset($info["header_size"]))? \substr($buffer, $info["header_size"]) : "";
         $data = \json_decode($buffer, true);
         $this->user = $data;
         $log = __CLASS__ . '::userInfo() sets this->user='.print_r($data, true);
         $return = true;
      }
      if ($this->log)
         error_log($log);
      if ($return)
         return $this->user;
      else
         return false;
   }
   
   /**
    * Creates a authentication link to Google servers.
    */
   public function createAuthUrl() {
      $url = $this->token->get('authorization_endpoint');
      $fields = $this->token->get('authorization_fields')[$this->token->get('authorization_fields_ndx')];
      return $url . '?' . $this->getFields($fields);
   }
   
   /**
    * Given a url encoded string of key-values, it replaces those values by the 
    * relevant ones at property GoogleHttpClient::token.
    * 
    * preconditions: pass a url-encoded string of key-values.
    * 
    * postconditions: if similar keys exist at the property $token then, it 
    *    replaces argument's values by property's ones.
    *    Ex.: update this "client_id=&client_secret=&refresh_token=&grant_type=refresh_token"
    *    with token's values.
    * 
    * @param string $f A url-encoded string of key-values
    * 
    * @return string The argument is populated by the urlencoded token property's 
    *    values that exist under the same argument keys
    */
   public function getFields($f) {
      if (!($arr = Utils::splitRequest($f)))
         return '';
      foreach ($arr as $k=>$v) {
         if ($v == null) {
            $arr[$k] = ($k . '=' . (($this->token->get($k) !== null)? \urlencode($this->token->get($k)): ''));
         } else {
            $arr[$k] = ($k . '=' . \urlencode($v));
         }
      }
      return \implode('&', $arr);
   }
   
   /**
    * Returns property $token
    */
   public function getToken() {
      return $this->token;
   }
   
   /**
    * Returns property $auth
    */
   public function getAuthToken() {
      return $this->auth;
   }
   
   /**
    * Sets property $auth
    */
   public function setAuthToken($auth) {
      $this->auth = $auth;
   }
   
   /**
    * Returns property $verify
    */
   public function getVerifyToken() {
      return $this->verify;
   }
   
   /**
    * Sets property $verify
    */
   public function setVerifyToken($verify) {
      $this->verify = $verify;
   }
   
   /**
    * Returns property $user
    */
   public function getUser() {
      return $this->user;
   }
   
   /**
    * Sets property $user
    */
   public function setUser($user) {
      $this->user = $user;
   }
   
   /**
    * Enables-disables logging
    */
   public function log($val) {
      $this->log = $val;
      return $val;
   }
}
