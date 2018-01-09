<?php
namespace auth;

/**
 * Google authentication Config class
 */
class GoogleToken extends BaseToken {
   /** 
    * Constructor.
    * 
    * @param string $path  An optional string representing a relative path (not 
    *    advised) or absolute
    */
   public function __construct($path = null) {
      parent::__construct($path);
      $this->file = Registry::getInstance()->getFile(__CLASS__);
      
      // use the GoogleHttpClient instead of the API
      $this->set('curl', true);
      // log Google responses
      $this->set('log', false);
      
      /** 
       * ---------------------
       * Google endpoints file
       * ---------------------
       * stored localy, see https://developers.google.com/identity/protocols/OpenIDConnect
       * We dropped support due to Google's noances!
       */
      $this->set('google_endpoints', 'google_endpoints.json');
      
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
       * ------------
       * Google Hosts
       * ------------
       */
      $this->set('accounts.google.com', "accounts.google.com:443:216.58.198.13");
      $this->set('www.googleapis.com', "www.googleapis.com:443:216.58.205.74");
      
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
      /**
       * -------------
       * Authorization
       * -------------
       */
      $this->set('token_endpoint', 'https://accounts.google.com/o/oauth2/token');
      $this->set('token_fields', 'client_id=&client_secret=&redirect_uri=&code=&grant_type=authorization_code');
      $this->set('code', '');
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
}
