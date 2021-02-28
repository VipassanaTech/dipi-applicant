<?php

include_once("constants.inc");

$err = 0;
$err_msg = '';
$logged_in = 0;
if ( isset($_POST['stage']) )
{
   if (! mysql_connect($DB_HOST, $DB_USER, $DB_PASS))
   {
      $err = 1;
      $err_msg = "Connect Failed!";
   }

   if ( (!$err ) &&  (! mysql_select_db($DB_NAME)) )
   {
      $err = 1;
      $err_msg = "Select Failed!";
   }
   $login = htmlentities(addslashes($_POST['login']));
   $auth = htmlentities(addslashes($_POST['authcode']));
   $q = "select CONCAT(a_f_name, ' ', a_m_name, ' ', a_l_name) as 'Name', a_id, a_center, a_course, c_name, c_start, a_status,a_city_str from dh_applicant left join dh_course on (a_course=c_id) where a_login='$login' and a_auth_code='$auth'";
   $hand = mysql_query($q);
   if (!$hand)
   {
	$err = 1;
        $err_msg = "Query Failed!";
   }
   if ( (!$err) && (mysql_num_rows($hand) > 0) )
   {
       $row = mysql_fetch_array($hand);
       $start = strtotime("+1 day", strtotime($row['c_start'])); //strtotime($row['c_start']);
       if ( time() > $start )
       {
	    $err = 1;
	    $err_msg = "Course already started!";
       }
       else
       {
	    if ( !in_array(strtolower($row['a_status']), array('preconfirmation','reconfirmation','confirmed','expected', 'clarification')) )
	    {
		$err = 1;
		$err_msg = "Invalid Status!";
	    }
	    else
	    {
		$status = array(); 
		if (strtolower($row['a_status']) == 'preconfirmation')
		   $status['Confirmed'] = 'Confirm my Attendance';
    if (strtolower($row['a_status']) == 'reconfirmation')
       $status['Expected'] = 'Re-confirm my Attendance';     
		if (strtolower($row['a_status']) == 'clarification')
		   $status['Clarification-Response'] = 'Send Clarification Response';
		$status['Cancelled'] = 'Cancel my Application';
		$status_opt = '';
		foreach( $status as $k => $v )
		  $status_opt .= '<option value="'.$k.'">'.$v.'</option>';
	    }
        }
   }
   else
   {
	$err = 1;
	$err_msg = "Invalid Login Details!";
   }
   if ( (!$err) && ($_POST['stage'] == 1) )
   {
	$logged_in = 1;
   }
   elseif( (!$err)  && ($_POST['stage'] == 2) )
   {
	$input_status = $_POST['status'];
	$file = '';
	if ( in_array($input_status, array('Cancelled', 'Confirmed', 'Expected', 'Clarification-Response'))  )
	{
	   if ( isset($_FILES) && isset($_FILES['doc']) && ($_FILES['doc']['name'] <> '') )
	   {
		$target_dir = $UPLOAD_DIR."/".$row['a_center']."/".$row['a_course'];
		$tstamp = date("Ymd-Hi");
	 	$target_file = $target_dir . "/" .$row['a_id']."-".$tstamp.".pdf"; //basename($_FILES["doc"]["name"]);
		$file_ext = strtolower(pathinfo(basename($_FILES["doc"]["name"]),PATHINFO_EXTENSION));
		if ( !in_array($file_ext, $ALLOWED_EXT) )
		{
		   $err = 1;
		   $err_msg = "Only PDF files allowed";
		   $logged_in = 1;
		}
		if ( (!$err) && ( $_FILES["doc"]["size"] > 2000000) ) 
		{
		   $err_msg = "File size cannot be greater than 2MB";
		   $err = 1;
		   $logged_in = 1;
		}

		if (!$err)
		{
		   if ( !is_dir($target_dir) )
		      mkdir($target_dir, 0770, true);
		   move_uploaded_file($_FILES["doc"]["tmp_name"], $target_file);
		   $file = "private:///clarification/".$row['a_center']."/".$row['a_course']."/".$row['a_id']."-".$tstamp.".pdf" ;
		}
	   }
	   if ( !$err )
	   {
	      $err_msg = "Thank you, your request has been submitted";
	      $logged_in = 0;
	      chdir($APP_ROOT);
	      $app_id = $row['a_id'];
	      if ( $input_status == 'Clarification-Response' )
	      {
 	          $msg = htmlentities(addslashes($_POST['msg']));
            mysql_set_charset('utf8');
	          $q = "insert into dh_applicant_clarification( ac_app, ac_msg, ac_file) VALUES ('".$row['a_id']."', '$msg', '$file')";
 	          mysql_query($q);
	      }
	      $cmd = "/usr/bin/php status-trigger.php $app_id '$input_status'";
	      exec($cmd);
	   }
	}
   }
}

?>

<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Applicant Action</title>

    <link href="bootstrap.min.css" rel="stylesheet">
    <link href="signin.css" rel="stylesheet">
    <style>
	.message{ color: #ff0000; }
    </style>
    <script src="jquery.min.js"></script>
    <script src="bootstrap.min.js"></script>
  </head>

  <body class="text-center">
    <form class="form-signin" method="POST" action="<?php echo htmlentities($_SERVER['PHP_SELF']);?>" enctype="multipart/form-data">
     <?php if (!$logged_in): ?>
      <h1 class="h3 mb-3 font-weight-normal">Please sign in</h1>
     <?php if ($err_msg): ?><h2 class="h3 mb-3 font-weight-normal message"><?php echo $err_msg; ?></h2> <?php endif; ?>
      <label for="inputEmail" class="sr-only">Login</label>
      <input type="text" name="login" id ="inputEmail" class="form-control" placeholder="Auth Login" required autofocus>
      <label for="inputPassword" class="sr-only">Auth Code</label>
      <input type="text" name="authcode" id="inputPassword" class="form-control" placeholder="Auth Code" required>
      <input type="hidden" name="stage" value="1">
      <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
     <?php else: ?>
      <input type="hidden" name="stage" value="2">
      <input type="hidden" name="login" value="<?php echo $login; ?>">
      <input type="hidden" name="authcode" value="<?php echo $auth; ?>">
      <h1 class="h3 mb-3 font-weight-normal">Course Details</h1>
     <?php if ($err_msg): ?><h2 class="h3 mb-3 font-weight-normal message"><?php echo $err_msg; ?></h2> <?php endif; ?>
  <table class="table table-hover">
    <tbody>
      <tr>
       <td>Course</td>
       <td><?php echo $row['c_name']?></td>
      </tr>
      <tr>
       <td>Name</td>
       <td><?php echo $row['Name']?></td>
      </tr>
      <tr>
       <td>Location</td>
       <td><?php echo $row['a_city_str']?></td>
      </tr>
      <tr>
	<td colspan=2><b>I would like to </b></td>
      </tr>
      <tr>
       <td colspan=2><select name="status" class="form-control"><?php echo $status_opt;?></select></td>
      </tr>
      <?php if (strtolower($row['a_status']) == 'clarification'): ?>
      <tr>
       <td colspan=2><textarea class="form-control" name="msg" rows=4 placeholder="Message" required></textarea></td>
      </tr>
      <tr>
	<td><label>Document if any (PDF only)</label></td>
       <td><input type="file" name="doc" /></td>
      </tr>
     <?php endif; ?>
      <tr>
      <td colspan=2><button class="btn btn-lg btn-primary btn-block" type="submit">Submit</button></td>
      </tr>
    </tbody>
    </table>
     <?php endif; ?>
<!--      <p class="mt-5 mb-3 text-muted">&copy; 2017-2018</p> -->
    </form>
  </body>
</html>




