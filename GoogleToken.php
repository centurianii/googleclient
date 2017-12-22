<?php
namespace auth;

/**
 * Google authentication Config class
 */
class GoogleToken {
   /** @var string $values A hased array of values */
   protected $values;
   
   /** 
    * Constructor.
    */
   public function __construct() {
      // log Google responses
      $this->set('log', false);
      
      /** 
       * ------------------
       * Console parameters
       * ------------------
       * see https://console.developers.google.com/apis/credentials.
       */
      $this->set('redirect_uri', 'http://localhost/auth/google/login');
      $this->set('client_id', '');
      $this->set('client_secret', '');
      
      /** 
       * ---------------------
       * Request authorization
       * ---------------------
       * see https://developers.google.com/identity/protocols/OAuth2WebServer#creatingclient,
       * https://developers.google.com/identity/protocols/OpenIDConnect#authenticationuriparameters
       * Some explanations: 
       * -use 'access_type=online' to get the access token,
       * -use 'access_type=offline' and approval_prompt = 'force' to get a refresh 
       *  token,
       * -for 'scope' see https://developers.google.com/identity/protocols/googlescopes.
       */
      $this->set('authorization_endpoint', 'https://accounts.google.com/o/oauth2/auth');
      /** the request authorization fields: an array with possible uris
       *  -at 0: the fields do not contain initial values,
       *  -at 1: the fields contain initial values that lead to a basic access 
       *      token at Google's respone,
       *  -at 2: the fields contain initial values that lead to a access and a 
       *     refresh token at Google's respone
       */
      $this->set('authorization_fields', array(
         'scope=&redirect_uri=&response_type=code&client_id=&state=&access_type=&approval_prompt=',
         'scope=openid profile email&redirect_uri=&response_type=code&client_id=&state=&access_type=online&approval_prompt=auto',
         'scope=openid profile email&redirect_uri=&response_type=code&client_id=&state=&access_type=offline&approval_prompt=force'
         )
      );
      /** which 'request_auth_fields' to use */
      $this->set('authorization_fields_ndx', 2);
      /** used by other classes, @see AuthCommand */
      $this->set('id', 'code');
      $this->set('xss', 'state');
      /**
       * -------------
       * Authorization
       * -------------
       */
      $this->set('token_endpoint', 'https://accounts.google.com/o/oauth2/token');
      $this->set('token_fields', 'client_id=&client_secret=&redirect_uri=&code=&grant_type=authorization_code');
      /**
       * ---------
       * User Info
       * ---------
       */
      $this->set('userinfo_endpoint', 'https://www.googleapis.com/userinfo/v2/me');
      /**
       * ------------
       * Verification
       * ------------
       */
      $this->set('verify_endpoint', 'https://www.googleapis.com/oauth2/v2/tokeninfo');
      $this->set('verify_attempts', 2);
      /**
       * -------
       * Refresh
       * -------
       */
      $this->set('refresh_endpoint', 'https://accounts.google.com/o/oauth2/token');
      $this->set('refresh_fields', 'client_id=&client_secret=&refresh_token=&grant_type=refresh_token');
      /**
       * ----------
       * Revocation
       * ----------
       */
      $this->set('revocation_endpoint', 'https://accounts.google.com/o/oauth2/revoke');
      
      /** allow permissions to be added later 
       *  https://developers.google.com/identity/protocols/OAuth2WebServer#incrementalAuth
       */
      //$this->set('incremental_scopes', false);
   }
   
   /**
    * Returns a value at the given key(s) (1- or 2-level arrays).
    * 
    * preconditions: key(s) should be a non-empty string.
    * postconditions: it returns a value (if exists) or null. 
    *    Passing non-strings or empty strings, it returns the whole 
    *    GoogleToken::$values array in case of such argument in 1st position or, 
    *    GoogleToken::$values[$key1] in case of such argument in 2nd position.
    * 
    * @param string $key1 A string used as key in GoogleToken::$values array
    * @param string|null $key2 A string used as key in the array of another key 
    *    in GoogleToken::$values[$key1] array
    * 
    * @return null|mixed|mixed[] Returns the value for the key(s) or null if it 
    *    fails to find a value.
    */
   public function get($key1 = null, $key2 = null) {
      if (\is_string($key1) && ($key1 !== '')) {
         if (\is_null($key2)) {
            if(\in_array($key1, \array_keys($this->values), true))
               return $this->values[$key1];
            else
               return null;
         } elseif (\is_array($this->values[$key1])) {
            if (\is_string($key2) && ($key2 !== '')) {
               if (\in_array($key2, \array_keys($this->values[$key1]), true))
                  return $this->values[$key1][$key2];
               else
                  return null;
            } else
               return $this->values[$key1];
         } else
            return null;
      } else
         return $this->values;
   }
   
   /**
    * Sets a value at the given key(s).
    * 
    * preconditions: key(s) should be a non-empty string.
    *    In case of 3 arguments, the above rule should apply to the first 2. 
    *    All values are acceptable as third argument except the character "\0" 
    *    which serves as a control (default) character and signifies the 
    *    existence of only 2 arguments.
    * postconditions: in case of 2 arguments, if first argument is a non-empty 
    *    string, it returns true and it uses the first argument as a key and the 
    *    second as the value in GoogleToken::$values array, i.e. values[$key1] = 
    *    $key2. The same applies in case of 3 arguments where $val !== "\0", 
    *    i.e. values[$key1][$key2] = $val. In all other cases, it returns false.
    * 
    * @param string $key1 A string used as key in GoogleToken::$values array
    * @param mixed $key2 In case of 2 arguments, it's the a value stored.
    *    In case of 3 arguments, it's the 2nd key
    * @param mixed $value A value of a 2-dimensional array
    * 
    * @return boolean True on success
    */
   public function set($key1 = null, $key2 = null, $val = "\0") {
      if(\is_string($key1) && ($key1 !== '')){
         if ($val === "\0") {
            $this->values[$key1] = $key2;
            return true;
         } elseif (\is_integer($key2) || (\is_string($key2) && ($key2 !== ''))) {
            $this->values[$key1][$key2] = $val;
            return true;
         } else
            return false;
      } else
         return false;
   }
}
