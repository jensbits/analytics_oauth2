<?php
// sets Google API query string parameters for OAuth2 authorization
// http://code.google.com/apis/accounts/docs/OAuth2UserAgent.html
class ApiSettings {
   public $scope;
   public $loginurl; 
   
   protected $clientid;
   protected $clientsecret;
   protected $redirecturi;
   
   private $endpoint = "https://accounts.google.com/o/oauth2/auth";
   private $accesstype;
  
   function __construct($apiSettings){
       $this->clientid = $apiSettings["clientid"];
       $this->clientsecret = $apiSettings["clientsecret"];
	   $this->redirecturi = $apiSettings["redirecturi"];
	   $this->scope = $apiSettings["scope"];
	   $this->accesstype = $apiSettings["accesstype"];
	   $this->loginurl = sprintf("%s?scope=%s&redirect_uri=%s&response_type=code&client_id=%s&access_type=%s",$this->endpoint,$this->scope,$this->redirecturi,$this->clientid,$this->accesstype); 
    }
}
?>