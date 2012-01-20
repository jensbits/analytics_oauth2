<?php
$serverpath = $_SERVER['DOCUMENT_ROOT'];
$path = $serverpath . "/demos/analytics_oauth2/inc/vars.inc";
include_once($path);

//so session vars can be used
session_start(); 

//Oauth 2.0: exchange token for session token so multiple calls can be made to api
if(isset($_REQUEST['code'])){
	$_SESSION['analyticAccessToken'] = get_oauth2_token($_REQUEST['code']);
}

if(isset($_REQUEST['logout'])){
	//clear session vars to start fresh
	session_unset(); 
}
//set form vars
if(isset($_POST['profileid'])){	
	$start_date = $_POST['startdate'];
	$end_date = $_POST['enddate'];

	$profile_name = substr($_POST['profileid'],strpos($_POST['profileid'],"|")+1);
	$profile_id = substr($_POST['profileid'],0,strpos($_POST['profileid'],"|"));
	
	$dataExportUrl = "https://www.googleapis.com/analytics/v3/data/ga?ids=ga:".$profile_id."&";
	
	$graph_type = $_POST['graphtype'];
}
else
{
//default start and end dates (one month)
	$start_date  = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-31, date("Y")));
	$end_date  = date("Y-m-d",mktime(0, 0, 0, date("m")  , date("d")-1, date("Y")));
}	

//format date inputs for output on page
$s_date_parts = explode("-",$start_date);
$e_date_parts = explode("-",$end_date);
		
$full_start_date = date("d-F-Y",mktime(0, 0, 0, $s_date_parts[1]  , $s_date_parts[2], $s_date_parts[0]));
$ga_start_date = date("Y-m-d",mktime(0, 0, 0, $s_date_parts[1]  , $s_date_parts[2], $s_date_parts[0]));
	
$full_end_date = date("d-F-Y",mktime(0, 0, 0, $e_date_parts[1]  , $e_date_parts[2], $e_date_parts[0]));
$ga_end_date = date("Y-m-d",mktime(0, 0, 0, $e_date_parts[1]  , $e_date_parts[2], $e_date_parts[0]));

//checks for valid date
function checkdaterange($start_date,$end_date){
	$errormsg = "valid";
	if ($start_date > $end_date)
		$errormsg = "invalid";
	return $errormsg;
}

//returns session token for calls to API using oauth 2.0
function get_oauth2_token($code) {
	global $client_id;
	global $client_secret;
	global $redirect_uri;
	
	$oauth2token_url = "https://accounts.google.com/o/oauth2/token";
	$clienttoken_post = array(
	"code" => $code,
	"client_id" => $client_id,
	"client_secret" => $client_secret,
	"redirect_uri" => $redirect_uri,
	"grant_type" => "authorization_code"
	);
	
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
		global $refreshToken;
		$refreshToken = $authObj->refresh_token;
		$_SESSION['refreshToken'] = $refreshToken;
	}
			  
	$accessToken = $authObj->access_token;
	return $accessToken;
}

//returns new access token from refresh token for calls to API using oauth 2.0
function get_oauth2_token_refresh_token($rtoken) {
	global $client_id;
	global $client_secret;
	//get from db
	$stored_refresh_token = "";
	
	$oauth2token_url = "https://accounts.google.com/o/oauth2/token";
	$clienttoken_post = array(
	"client_id" => $client_id,
	"client_secret" => $client_secret,
	"refresh_token" => $rtoken,
	"grant_type" => "refresh_token"
	);

	$curl = curl_init($oauth2token_url);

	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, $clienttoken_post);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

	$json_response = curl_exec($curl);
	curl_close($curl);
	$authObj = json_decode($json_response);	  
	$accessToken = $authObj->access_token;
	return $accessToken;
}
	
//calls api and gets the data
function call_api($accessToken,$url){
	$curl = curl_init($url);
 
	curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);	
	$curlheader[0] = "Authorization: Bearer " . $accessToken;
	curl_setopt($curl, CURLOPT_HTTPHEADER, $curlheader);

	$json_response = curl_exec($curl);
	curl_close($curl);
		
	$responseObj = json_decode($json_response);
	return $responseObj;	    
}

//returns profile list as array	
function parse_profile_list($accountObj){
	$i = 0;
	$profiles = array();
		
	$profilesObj = call_api($_SESSION['analyticAccessToken'],"https://www.googleapis.com/analytics/v3/management/accounts/~all/webproperties/~all/profiles");
		
	foreach($profilesObj->items as $profile)
		{
			$profiles[$i] = array();
			$profiles[$i]["name"] = $profile->name;
			$profiles[$i]["profileid"] = $profile->id;
			$i++;
		}
		
	return $profiles;	
}

//returns data as array	
function parse_data($requestUrl,$accessToken){
	$dataObj = call_api($accessToken,$requestUrl);

	$r = 0;
	$results = array();
		
	foreach($dataObj->rows as $row)
	{
		$results[$r] = array();
		$h = 0;
		foreach($dataObj->columnHeaders as $columnHeader)
		{
			$results[$r][ltrim($columnHeader->name,"ga:")] = $row[$h];
			$h++;
		}
		$r++;
	}	
	return $results;
}

function dbRefreshToken($name,$scope,$refreshToken = ""){
	global $serverpath;
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
			$accessTokenfromRefresh = get_oauth2_token_refresh_token($row["token"]);
			return $accessTokenfromRefresh;
		}
		mysql_close($conn);
	}
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title>Google Analytics with OAuth 2 and Google Charts - PHP</title>

<script src="//www.google.com/jsapi"></script>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1/jquery-ui.min.js"></script>

<link rel="stylesheet" type="text/css" href="//ajax.googleapis.com/ajax/libs/jqueryui/1/themes/redmond/jquery-ui.css" media="screen" />
<link rel="stylesheet" type="text/css" href="css/960.css" media="screen" />
<link rel="stylesheet" type="text/css" href="css/style.css" media="screen" />

<script>
$(function() {
	$("#startdate, #enddate").datepicker({showOn: 'button', buttonImage: 'SmallCalendar.gif', buttonImageOnly: true, dateFormat: 'dd-MM-yy', altFormat: 'yy-mm-dd', maxDate: -1});
		
	$("#startdate").datepicker("option",{altField: '#start_alternate', minDate: new Date(2009, 8 - 1, 1)});
	$("#enddate").datepicker("option", {altField: '#end_alternate', minDate: new Date(2009, 8 - 1, 2)});		
});
</script>
<script>
 // remove my GA tracking if you copy from source. thanks. 
var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-4945154-2']);
  _gaq.push(['_trackPageview']);
  _gaq.push(['_trackEvent', 'Demo', 'View', '/demos/analytics_oauth2/index.php' ]);
  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    (document.getElementsByTagName('head')[0] || document.getElementsByTagName('body')[0]).appendChild(ga);
  })();

</script>
</head>
<body>
<div id="wrapper">
	
	<div id="header" class="container_16">
		<h1><span>Google Analytics</span> with OAuth 2 &amp; Google Charts <span>PHP</span></h1>
	</div>
    
<div id="content-wrap" class="container_16">   

<?php

if (!isset($_SESSION['analyticAccessToken'])){

//$loginUrl = sprintf("https://accounts.google.com/o/oauth2/auth?scope=%s&state=%s&redirect_uri=%s&response_type=code&client_id=%s&access_type=%s",$scope,$state,$redirect_uri,$client_id,$access_type);
$loginUrl = sprintf("https://accounts.google.com/o/oauth2/auth?scope=%s&state=%s&redirect_uri=%s&response_type=code&client_id=%s",$scope,$state,$redirect_uri,$client_id);
?>
	<div class="grid_8 prefix_4 suffix_4"> 
		<h2>Sign In</h2>
        
        <p><a class="button" href="<?php echo $loginUrl ?>">Login with Google account that has access to analytics using OAuth 2.0</a></p>
        
        <p><a href="http://www.jensbits.com/">Return to post on jensbits.com</a></p>
	</div>
<?php
	if(isset($_REQUEST['error'])){
		echo "<div class='grid_8 prefix_4 suffix_4'><p class='errorMessage'>Error: " . $_REQUEST['error'] . "</p></div>";
		session_unset();
	}
}
else
{
	$accountObj = call_api($_SESSION['analyticAccessToken'],"https://www.googleapis.com/analytics/v3/management/accounts");
	//refresh token handling - save to db if returned with access token
	//or retrieve from db if needed to app
	if(isset($refreshToken)){
		//dbRefreshToken($accountObj->username,$scope,$refreshToken);
		dbRefreshToken('Jen Kang',$scope,$refreshToken);
	}else{
		$accessTokenFromRefresh = dbRefreshToken('Jen Kang',$scope);
	}
	// Get an object with the available accounts
	$profiles = parse_profile_list($accountObj);

		echo "<div class='grid_6 prefix_5 suffix_5'>";
		echo "<div id='logout'><a href='" . $_SERVER['PHP_SELF'] . "?logout=1'>Log out</a></div>";
		echo "<h2>Select Site and Date Range</h2>";

		echo "<form name='siteSelect' id='siteSelect' method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
		echo "<p><label for='profileid'>Select Site:</label><select name='profileid' id='profileid'>";
		foreach($profiles as $profile)
		{
			$selected = " ";
			
			if($profile["profileid"] === $profile_id){ $selected = " selected='selected'"; }
			
			echo "<option value='" . $profile["profileid"] . "|" . $profile["name"] . "'" . $selected  . ">" . $profile["name"] . "</option>";
				
		}
		echo "</select></p>";
	?>

	    <p><label for='startdate'>Start Date:</label>
	    <input id='startdate' readonly='readonly' type='text' value='<?php echo $full_start_date; ?>' />
	    <input type='hidden' name='startdate' id='start_alternate' value='<?php echo $ga_start_date; ?>' /></p>
	    
	    <p><label for='enddate'>End Date:</label>
	    <input id='enddate' readonly='readonly' type='text' value='<?php echo $full_end_date; ?>' />
	    <input type='hidden' name='enddate' id='end_alternate' value='<?php echo $ga_end_date; ?>' /></p>
	    
	    <p><label for='graphtype'>Visitor Graph Type:</label>
	    <input id="radio_month" type="radio" name="graphtype" value="month" checked="checked" />Month
	    <input id="radio_day" type="radio" name="graphtype" value="day" <?php if($graph_type == 'day') echo "checked='checked'"; ?> />Day</p>
		<p><input type='submit' value='Submit' /></p>
		</form>
        
		</div>
<?php	
	}
	
if(isset($_POST['profileid'])){
	echo "<div class='grid_16' style='margin-top: 1em;'>";
	
	if (checkdaterange($start_date,$end_date) === "invalid"){
		echo "<p class='errorMessage'>Date range of " . $full_start_date . " to " . $full_end_date . " is invalid. Please reselect the dates.</p>";
		exit;
	}
	else
	{
		echo "<h1 style='text-transform:uppercase'><span style='color: #999999'>". $full_start_date . " to " . $full_end_date . "</h1>";
		
		echo "<hr />";
		$jenspageviews = parse_data("https://www.googleapis.com/analytics/v3/data/ga?ids=ga:17445729&metrics=ga:pageviews&start-date=".$start_date."&end-date=".$end_date,$accessTokenFromRefresh);
		echo "<h1 style='text-transform:uppercase'><span style='color: #999999'>jensbits.com</span> (offline access)</h1>";
		echo "<h2>Pageviews: ".number_format($jenspageviews[0]['pageviews'])."</h2>";
		$accessTokenFromRefresh = "";
		echo "<hr />";
		
		$visits_graph_type = $_POST['graphtype'];

		echo "<h1 style='text-transform:uppercase'>".$profile_name."</h1>";
	
		// For each website, get visits and visitors
		$requrlvisits = sprintf("%smetrics=ga:visits,ga:visitors&start-date=%s&end-date=%s",$dataExportUrl,$start_date,$end_date);
				
		$visits = parse_data($requrlvisits,$_SESSION['analyticAccessToken']);
		
		foreach($visits as $visit)
			{
				echo "<h2>Visits: ".number_format($visit["visits"])."</h2><h2>Visitors: ".number_format($visit["visitors"])."</h2>";
			}
		
		echo "<div id='barchart_div'></div>";
		echo "<div id='piechart_div'></div>";

		// For each website, get referrals
		$requrlreferrers = sprintf("%sdimensions=ga:source&metrics=ga:visits&filters=ga:medium==referral&start-date=%s&end-date=%s&sort=-ga:visits&max-results=10",$dataExportUrl,$start_date,$end_date);
						
		$referrers = parse_data($requrlreferrers,$_SESSION['analyticAccessToken']);	
				
		echo "<h1>Referrers</h1>";
							
		echo "<table width='75%' class='dataTable listTable'><tr class='headerRow'><th>Referrer</th><th>Visits</th></tr>";
		$table_row = 0;
		foreach($referrers as $referrer)
			{
				if ($table_row % 2){
					echo "<tr><td>";
				} else {
					echo "<tr class='oddrow'><td>";
				}
				echo $referrer["source"] . "</td><td class='visits'>"  . number_format($referrer["visits"]) . "</td.</tr>";
				$table_row++;
				
			}
		echo "</table>";	
				
		// For each website, get visits graph data
		if ($visits_graph_type === "day"){
			$requrlvisitsgraph = sprintf("%sdimensions=ga:date&metrics=ga:visits&start-date=%s&end-date=%s",$dataExportUrl,$start_date,$end_date);
		} else {
			$requrlvisitsgraph = sprintf("%sdimensions=ga:month,ga:year&metrics=ga:visits&sort=ga:year&start-date=%s&end-date=%s",$dataExportUrl,$start_date,$end_date);
		}
								
		$visitsgraph = parse_data($requrlvisitsgraph,$_SESSION['analyticAccessToken']);				
?>

<script>      
function drawPieChart() {
	var data = new google.visualization.DataTable();
    data.addColumn('string', 'Referrer');
    data.addColumn('number', 'Visits');
    data.addRows(<?php echo sizeof($referrers) ?>);
    <?php
    $row = 0;
    foreach($referrers as $referrer)
		{
	?>
	data.setValue(<?php echo $row ?>,0,'<?php echo $referrer["source"] ?>');
	data.setValue(<?php echo $row ?>,1,<?php echo $referrer["visits"] ?>);
	<?php	
	$row++;
	}
	?>

	var chart = new google.visualization.PieChart(document.getElementById('piechart_div'));
    chart.draw(data, {width: 600, height: 440, is3D: true, title: 'Referrer/Visits'});
}

function drawBarChart() {
	var data = new google.visualization.DataTable();
    data.addColumn('string', 'Day');
    data.addColumn('number', 'Visits');
    data.addRows(<?php echo sizeof($visitsgraph) ?>);
	<?php
    $row = 0;
    foreach($visitsgraph as $visits)
		{
	?>
		data.setValue(<?php echo $row ?>,0,'<?php if ($visits_graph_type === "month"){echo date("M", mktime(0, 0, 0, $visits["month"]))." ".$visits["year"];}else{echo substr($visits['date'],6,2)."-".date('M', mktime(0, 0, 0, substr($visits['date'],4,2)))."-".substr($visits['date'],0,4);} ?>');
		data.setValue(<?php echo $row ?>,1,<?php echo $visits["visits"] ?>);
		<?php 
		$row++; 
		}
		?>
    var chart = new google.visualization.ColumnChart(document.getElementById('barchart_div'));
    chart.draw(data, {'width': 700, 'height': 400, 'is3D': true, 'title': 'Visits'});
}

google.load("visualization", "1.0", {packages:["corechart"]});
google.setOnLoadCallback(drawPieChart);
google.setOnLoadCallback(drawBarChart);

</script>
<?php
	}
}	
?>
		</div>
	</div>
</div>
</body>
</html>