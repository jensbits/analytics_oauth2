<?php
session_start();
/*** nullify any existing autoloads http://www.phpro.org/tutorials/SPL-Autoload.html ***/
spl_autoload_register(null, false);
/*** specify extensions that may be loaded ***/
spl_autoload_extensions('.php, .class.php');
/*** class Loader ***/
function classLoader($class)
	{
        $filename = strtolower($class) . '.class.php';
        $file ='classes/' . $filename;
        if (!file_exists($file))
        {
            return false;
        }
        include $file;
    }
/*** register the loader functions ***/
spl_autoload_register('classLoader');

// default vars inc. start and end dates (one month)
$gaExportUrlPrefix = "https://www.googleapis.com/analytics/v3/data/ga?";
$errors = array();

$start_date  = date("d-F-Y",strtotime("-31 day"));
$end_date  = date("d-F-Y",strtotime("-1 day"));
$graph_type = "month";

$gaApiSettings = array(
	"clientid" => "YOUR-CLIENT-ID.apps.googleusercontent.com",
    "clientsecret" => "YOUR-CLIENT-SECRET",
	"redirecturi" => "YOUR-REDIRECT-URI",
	"scope" => "https://www.googleapis.com/auth/analytics.readonly",
	"accesstype" => "online"
	);

$auth = new GoogleOauth2($gaApiSettings);

foreach($_GET as $key => $value){
	switch($key){
		case "error":
			//If user refuses to grant access to app, url param of "error" is returned by Google
			$errors["Access Error"] = $value;
			session_unset();
			break;
		case "logout":
			session_unset();
			break;
		case "code":
			//Oauth2 code for access token so multiple calls can be made to api
			$accessTokenResponse = $auth->getOauth2Token($_GET["code"]); 
			if(strstr($accessTokenResponse,"Error")){
				$errors["Access Error"] = $accessTokenResponse;
				session_unset();
			}else{
				$_SESSION['analyticAccessToken'] = $accessTokenResponse;
				//reload for 'clean' url
				header("location:".$_SERVER["PHP_SELF"]);
			}
			break;
	}
}

if (isset($_SESSION['analyticAccessToken'])){
	// create google analytics data object
	$gaData = new Gadata($_SESSION['analyticAccessToken']);
	// hold in session to prevent additional requests for profiles (they don't change too often)
	if (!isset($_SESSION['profiles'])){
		$_SESSION['profiles'] = $gaData->parseProfileList();
	}
	$profiles = $_SESSION['profiles'];
}

// get the data
if(isset($_POST['profileid'])){	
	$start = $_POST['startdate'];
	$end = $_POST['enddate'];
	if (date("Y-m-d",strtotime($start)) > date("Y-m-d",strtotime($end))){
		$errors["Date Range Error"] = "Date range of (start) " . date("d-F-Y",strtotime($start)) . " to (end) " . date("d-F-Y",strtotime($end)) . " is invalid. Please reselect the dates.";
	}else{
		$start_date = $start;
		$end_date = $end;
	}
	$profile_name = substr($_POST['profileid'],strpos($_POST['profileid'],"|")+1);
	$profile_id = substr($_POST['profileid'],0,strpos($_POST['profileid'],"|"));
	$dataExportUrl = $gaExportUrlPrefix."ids=ga:".$profile_id."&";
	$graph_type = $_POST['graphtype'];
	
	//refresh token handling - save to db if returned with access token
	//or retrieve from db if needed for app
	if(isset($auth->refreshToken)){
		//get identifying info to store refresh token with
		$accountObj = $gaData->parseData("https://www.googleapis.com/analytics/v3/management/accounts");
		$auth->dbRefreshToken($accountObj->username,$auth->scope,$auth->refreshToken);
	}else{
		$accessTokenFromRefresh = $auth->dbRefreshToken("Jen Kang",$auth->scope);
	}
	// offline data
	$gaDataRefreshToken = new Gadata($accessTokenFromRefresh);
	$jenspageviews = $gaDataRefreshToken->parseData($gaExportUrlPrefix."ids=ga:17445729&metrics=ga:pageviews",$start_date,$end_date);
	
	// get visits and visitors
	$requrlvisits = sprintf("%smetrics=ga:visits,ga:visitors",$dataExportUrl);
	$visits = $gaData->parseData($requrlvisits,$start_date,$end_date);
	
	// get visits graph (chart) data
	$requrlvisitsgraph = sprintf("%sdimensions=ga:%s,ga:year&metrics=ga:visits&sort=ga:year",$dataExportUrl,$graph_type);				
	$visitsgraph = $gaData->parseData($requrlvisitsgraph,$start_date,$end_date);

	// get referrals
	$requrlreferrers = sprintf("%sdimensions=ga:source&metrics=ga:visits&filters=ga:medium==referral&sort=-ga:visits&max-results=10",$dataExportUrl);			
	$referrers = $gaData->parseData($requrlreferrers,$start_date,$end_date);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Google Analytics with OAuth 2 and Google Charts - PHP</title>
<!-- Le HTML5 shim, for IE6-8 support of HTML elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->
<script src="//www.google.com/jsapi"></script>
<script src="//ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
<script src="//ajax.googleapis.com/ajax/libs/jqueryui/1/jquery-ui.min.js"></script>
<link rel="stylesheet" href="//ajax.googleapis.com/ajax/libs/jqueryui/1/themes/redmond/jquery-ui.css" />
<link rel="stylesheet" href="/demos/bootstrap/css/bootstrap.min.css" />
<style type="text/css">
body {padding-top: 60px;}
</style>
<link rel="stylesheet" href="/demos/bootstrap/css/bootstrap-responsive.min.css" />
<script>
$(function() {
	$("#startdate, #enddate").datepicker({showOn: 'both', buttonImage: 'SmallCalendar.gif', buttonImageOnly: true, dateFormat: 'dd-MM-yy', maxDate: -1});
	$("#startdate").datepicker("option",{ minDate: new Date(2009, 8 - 1, 1)});
	$("#enddate").datepicker("option", {minDate: new Date(2009, 8 - 1, 2)});		
});
</script>
</head>
<body>
<div class="navbar navbar-fixed-top">
	<div class="navbar-inner">
		<div class="container">
        	<a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </a>
          <a class="brand" href="index.php">Google Analytics with OAuth 2 &amp; Google Charts PHP</a>
          <div class="nav-collapse">
              <ul class="nav">
                <li class="active"><a href="http://www.jensbits.com/">Home</a></li>
              </ul>
          </div>
        </div>
    </div>
</div>
<div class="container">   
<?php
if (!isset($_SESSION['analyticAccessToken'])){
?>
<div class="hero-unit">
	<h1>Sign In</h1>
    <p>Google Analytics data displayed in Google Charts using OAuth2 authorization.<br />
    Google account must have access to analytics.</p>
    <p><a class="btn btn-primary btn-large" href="<?php echo $auth->loginurl ?>">Authorize with Google account</a></p>
    <p><a href="http://www.jensbits.com/">Return to post on jensbits.com</a></p>
</div>
<?php
	if(array_key_exists("Access Error",$errors)){
		echo "<p class='alert-message error'>Access Error: " . $errors["Access Error"] . "</p>";
	}
}
else
{
?> 
<div class="hero-unit">
     <div class="row">
        <h1 class="span8">Account Profiles</h1>
        <div class="span2"><a class="btn btn-danger" href="?logout=1">Log out</a></div>
     </div>
        <h2>Select Site and Date Range</h2>
        <form class="form-horizontal" name="siteSelect" id="siteSelect" method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>">
        <fieldset>
         <div class="control-group">
        	 <label class="control-label" for="profileid">Select Site:</label>
             <div class="controls">  
                 <select name="profileid" id="profileid">
<?php	
    foreach($profiles as $profile)
	{
		$selected = " ";
		if($profile["profileid"] === $profile_id){ $selected = " selected='selected'"; }
		echo "<option value='" . $profile["profileid"] . "|" . $profile["name"] . "'" . $selected  . ">" . $profile["name"] . "</option>";
	}
?>
            </select>
            </div>
         </div>
            <div class="control-group">
                <label class="control-label" for="startdate">Date Range:</label>
                <div class="controls">  
            		<input type="text" id="startdate" name='startdate' readonly="readonly" value="<?php echo $start_date ?>" />
                    to
          			<input type="text" id="enddate" name='enddate' readonly="readonly" value="<?php echo $end_date ?>" />
            	</div>
            </div>
            <div class="control-group">
            <label class="control-label" for="graphtype">Visitor Graph Type:</label>
            	<div class="controls">  
					<label class="radio inline">
					<input id="radio_month" type="radio" name="graphtype" value="month"  <?php if($graph_type === "month") echo "checked='checked'" ?> /><span>Month</span>
					</label>
					<label class="radio inline">
					<input id="radio_day" type="radio" name="graphtype" value="date" <?php if($graph_type === "date") echo "checked='checked'" ?> /><span>Date</span>
            		</label>
                </div>
            </div>
            <div class="form-actions">
            	<input class="btn btn-primary" type="submit" value="Submit" />
            </div>
            </fieldset>
            </form> 
</div>
<?php	
}
if(isset($_POST['profileid'])){
	echo "<div class='row'><div class='span12'>";
	if(array_key_exists("Date Range Error",$errors)){
		echo "<p class='alert alert-error'>Date Range Error: " . $errors["Date Range Error"] . "</p>";
	}
	echo "<h1>". $start_date . " to " . $end_date . "</h1>";
	echo "<hr />";
	echo "<h2>www.jensbits.com</h2><p>demo of offline access with refresh token</p>";
	echo "<h3>Pageviews: ".number_format($jenspageviews[0]['pageviews'])."</h3>";
	$accessTokenFromRefresh = "";
	echo "<hr />";
	echo "<h2>".$profile_name."</h2>";
			
	//visits output
	foreach($visits as $visit)
		{
			echo "<h3>Visits: ".number_format($visit["visits"])."</h3><h3>Visitors: ".number_format($visit["visitors"])."</h3>";
		}
	echo "<div id='barchart_div'></div>";
	
	//referrers output		
	echo "<h3>Referrers</h3>";
	echo "<div id='piechart_div'></div>";
					
	echo "<table class='table table-striped'><tr><th>Referrer</th><th>Visits</th></tr>";
	foreach($referrers as $referrer)
		{
			echo "<tr><td>" . $referrer["source"] . "</td><td>"  . number_format($referrer["visits"]) . "</td></tr>";	
		}
	echo "</table>";						
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
		data.setValue(<?php echo $row ?>,0,'<?php if ($graph_type === "month"){echo date("M", mktime(0, 0, 0, $visits["month"]))." ".$visits["year"];} else {echo substr($visits['date'],6,2)."-".date('M', mktime(0, 0, 0, substr($visits['date'],4,2)))."-".substr($visits['date'],0,4);} ?>');
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

	</div>
</div>
<?php
}	
?>
		
<footer>
	<p>&copy; jensbits.com <?php echo date("Y"); ?></p>
</footer>
</div>
<script src="/demos/bootstrap/js/bootstrap.min.js"></script>
</body>
</html>