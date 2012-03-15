<?php
class GoogleOauth2 extends ApiSettings {
	public $refreshtoken;

	function __construct($apiSettings){
		parent::__construct($apiSettings);
	}
	//returns session token for calls to API using oauth 2.0
	function getOauth2Token($code,$refreshtoken = false) {
		
		$oauth2token_url = "https://accounts.google.com/o/oauth2/token";
		$clienttoken_post = array(
		"client_id" => $this->clientid,
		"client_secret" => $this->clientsecret
		);
		if ($refreshtoken){
			$clienttoken_post["refresh_token"] = $code;
			$clienttoken_post["grant_type"] = "refresh_token";
		}else{
			$clienttoken_post["code"] = $code;	
			$clienttoken_post["redirect_uri"] = $this->redirecturi;
			$clienttoken_post["grant_type"] = "authorization_code";
		}
		
		$curl = curl_init($oauth2token_url);
	
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $clienttoken_post);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	
		$json_response = curl_exec($curl);
		curl_close($curl);
	
		$authObj = json_decode($json_response);
		
		if (isset($authObj->refresh_token)){
			$this->refreshToken = $authObj->refresh_token;
		}
				  
		$accessTokenResponse = isset($authObj->access_token) ? $authObj->access_token : "Error occured: " . json_encode($authObj);
		
		return $accessTokenResponse;
	}
	
	function dbRefreshToken($name,$scope,$refreshToken = ""){
		$serverpath = $_SERVER['DOCUMENT_ROOT'];
		$path = $serverpath."/config/token_config.php";
		include_once($path);
		$path = $serverpath."/config/db.php";
		include_once($path);
	
		if ($conn){
			if (strlen($refreshToken)){
				//if refreshToken in param list, save to db
				$query = "INSERT INTO tokens (name, scope, token) VALUES (:name, :scope, :refreshToken)";
				$result = $conn->prepare($query); 
				$result->bindValue(':name', $name, PDO::PARAM_STR);
				$result->bindValue(':scope', $scope, PDO::PARAM_STR);
				$result->bindValue(':refreshToken', $refreshToken, PDO::PARAM_STR);
				$result->execute(); 
			} else {
				//else retrieve refresh token from db and return new access token
				$query = "SELECT token from tokens where name = :name and scope = :scope";
				$result = $conn->prepare($query);
				$result->bindValue(':name',$name, PDO::PARAM_STR);
				$result->bindValue(':scope', $scope, PDO::PARAM_STR);
				$result->execute();
				$row = $result->fetch(PDO::FETCH_ASSOC);
				$accessTokenfromRefresh = $this->getOauth2Token($row["token"],true);
				return $accessTokenfromRefresh;
			}
			mysql_close($conn);
		}
	}
}
?>