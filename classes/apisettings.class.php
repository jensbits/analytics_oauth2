<?php
// sets Google API query string parameters for OAuth2 authorization
// http://code.google.com/apis/accounts/docs/OAuth2UserAgent.html
class ApiSettings {
   protected $clientid;
   protected $clientsecret;
   protected $redirecturi;
   protected $endpoint = "https://accounts.google.com/o/oauth2/auth";

   public $accesstype = "online";
   public $scope;
   public $loginurl;
  
   function __construct(){
       $this->clientid = "YOUR_APP_ID.apps.googleusercontent.com";
       $this->clientsecret = "YOUR_CLIENT_SECRET";
	   $this->redirecturi = "http://YOUR_REDIRECT_URI";
	   $this->scope = "https://www.googleapis.com/auth/analytics.readonly";
	   $this->loginurl = sprintf("%s?scope=%s&redirect_uri=%s&response_type=code&client_id=%s&access_type=%s",$this->endpoint,$this->scope,$this->redirecturi,$this->clientid,$this->accesstype); 
    }
	
   public function setClientId($client_id){
   	   $this->clientid = $client_id;
   }
   public function getClientId(){
   	   return $this->clientid;
   }
   
   public function setClientSecret($client_secret){
   	   $this->clientsecret = $client_secret;
   }
   public function getClientSecret(){
   	   return $this->clientsecret;
   }
   
   public function setRedirectUri($redirect_uri){
   	   $this->redirecturi = $redirect_uri;
   }
    public function getRedirectUri(){
   	   return $this->redirecturi;
   }
   
   public function setScope($scope){
   	   $this->scope = $scope;
   }
   public function getScope(){
   	   return $this->scope;
   }
   
   public function setAccessType($access_type){
   	   $this->clientid = $access_type;
   }
   public function getAccessType(){
   	   return $this->clientid;
   }
   
   public function setAll($client_id,$client_secret,$redirect_uri,$scope,$access_type){
       $this->clientid = $client_id;
       $this->clientsecret = $client_secret;
       $this->redirecturi = $redirect_uri;
       $this->scope = $scope;
       $this->clientid = $access_type;
   }
   public function getAll(){
       return (array) $this;
   }
}
?>