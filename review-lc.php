<?php

include_once("constants.inc");

$err = 0;
$err_msg = '';
$rtype = $_REQUEST['t'];
if ($rtype == 'r') {$auth_field = 'al_recommending_auth'; $submit_url = "r-review"; }
if ($rtype == 'a') {$auth_field = 'al_area_auth'; $submit_url = "a-review" ; }
$cat = 0;
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
   //$login = htmlentities(addslashes($_POST['login']));
   $auth = htmlentities(addslashes($_POST['authcode']));
   $q = "select CONCAT(a_f_name, ' ', a_m_name, ' ', a_l_name) as 'Name', a_id, a_center, a_course, c_name, c_start, a_status,a_city_str, a_photo,  ac.*, al.* from dh_applicant left join dh_course on (a_course=c_id) left join dh_applicant_lc al on (a_id=al_applicant) left join dh_applicant_course ac on (a_id=ac_applicant) where  $auth_field ='$auth'";
   $hand = mysql_query($q);
   if (!$hand)
   {
	$err = 1;
		$err_msg = "Query Failed!";
   }
   $at_reco = "";
   if ( (!$err) && (mysql_num_rows($hand) > 0) )
   {
	   $row = mysql_fetch_array($hand);
	  	if ($rtype == 'r')
	   		$at_reco = $row['al_recommending'];
	   	else
	   		$at_reco = $row['al_area_at'];
		$q = "select t_cat from dh_teacher where CONCAT(t_f_name, ' ', t_l_name)='".$at_reco."'";
		$hand = mysql_query($q);
		if( $hand )
		{
			if (mysql_num_rows($hand) > 0 )
			{
			$r = mysql_fetch_array($hand);
				if ($r['t_cat'])
			   $cat = 1;
			}
		}
	   $start = strtotime("+1 day", strtotime($row['c_start'])); //strtotime($row['c_start']);
	   if ( time() > $start )
	   {
			$err = 1;
			$err_msg = "Course already started!";
	   }
	   else
	   {
			if ( !in_array(strtolower($row['a_status']), array('r-atreview', 'a-atreview', 'received', 'errors')) )
			{
				$err = 1;
				$err_msg = "Invalid Status!";
			}
			else
			{
				$photo = '';
				if ($row['a_photo'])
				{
					$fname = str_replace('private://', '/dhamma/web/files/dipi/', $row['a_photo']);
					//echo "file is $fname";
					if (file_exists($fname))
					{
						 $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
						if (in_array($ext, array('jpg', 'jpeg')))
						   $mime_type = "image/jpeg";
						else
						   $mime_type = "image/png";
						$photo = base64_encode(file_get_contents($fname));
					}
				}
				$status = array(); 
				$status['Rejected'] = 'Reject Application';
				$status['Approved'] = 'Approve Application';
				$status_opt = '';
				foreach( $status as $k => $v ) {
					$status_opt .= '<td><input type="radio" name="status" value="'.$k.'">'.$v.'</td>';	
				}				  
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
		if ($cat == 0)
		{
			$q = "select CONCAT(t_f_name, ' ', t_l_name) as 'name' from dh_teacher where t_cat=1 order by t_f_name, t_l_name";
			$hand = mysql_query($q);
			$area_t = array('<option value="">Select Area Teacher</option>');
			if ($hand)
			{
			   while($r = mysql_fetch_array($hand))
				   $area_t[] = '<option value="'.$r['name'].'">'.$r['name'].'</option>';
			}
				$select_area_t = implode("", $area_t );
		}
   }
   elseif( (!$err)  && ($_POST['stage'] == 2) )
   {
		$input_status = $_POST['status'];
		$file = '';
		if ( in_array($input_status, array('Approved', 'Rejected'))  )
		{
		   if ( !$err )
		   {
			  $area_teacher = addslashes($_POST['areat']);
			  $err_msg = "Thank you, your request has been submitted";
			  $logged_in = 0;
			  chdir($APP_ROOT);
			  $app_id = $row['a_id'];
			  $new_status = '';
			  if ($rtype == 'r')
			  {
				 if ($input_status == 'Approved')
				 {
				 	$append = "";
					$new_status = 'A-ATReview';
					if ($cat == 1)
					{
						$new_status = 'Received';
						$append = ", al_area_at_approved='Approved' ";
					}
					$q = "update dh_applicant_lc set al_area_at='$area_teacher', al_recommending_approved='Approved' $append where $auth_field ='$auth'";
					//echo($q);
					mysql_query($q);
				 }
				 else
				 {
				 	$new_status = 'Rejected-R-AT'; 
				 	$q = "update dh_applicant_lc set  al_recommending_approved='Rejected' where $auth_field ='$auth'";
				 	mysql_query($q);				 	
				 }
				   
			  }
			  elseif ($rtype == 'a') 
			  {
				 if ($input_status == 'Approved')
				 {
				 	$new_status = 'Received';
					$q = "update dh_applicant_lc set  al_area_at_approved='Approved' where $auth_field ='$auth'";
					//echo($q);
					 mysql_query($q);
				 }	
				 else
				 {
				 	$new_status = 'Rejected-A-AT'; 
				 	$q = "update dh_applicant_lc set  al_area_at_approved='Rejected' where $auth_field ='$auth'";
				 	mysql_query($q);
				 }
			  }
			  $cmd = "/usr/bin/php status-trigger.php $app_id '$new_status'";
			  //echo "$cmd";
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

	<link href="/bootstrap.min.css" rel="stylesheet">
	<link href="/signin.css" rel="stylesheet">
	<style>
	.message{ color: #ff0000; }
		.course-details{ width: 100%; }
		.photo{ width: 50%; }
	</style>
	<script src="/jquery.min.js"></script>
	<script src="/bootstrap.min.js"></script>

	<!-- Show Area teacher select on approval -->
	<script>
		$(document).ready(function(){
			var val = 'Approved';
			$("input[name=status][value="+val+"]").prop('checked', true);
			
			$('input:radio[name="status"]').change(
	    function(){
	      if ($(this).is(':checked') && $(this).val() == 'Rejected') {
	    	  $(".areat-row").addClass("hidden");
					$(".areat-select").attr("required",false);
      	}
      	else {
      		$(".areat-row").removeClass("hidden");
					$(".areat-select").attr("required",true);	
      	}
	    });  		
    });
	</script>
  </head>

  <body class="text-center">
	<form class="form-signin" method="POST" action="<?php echo $submit_url;?>" enctype="multipart/form-data">
	 <?php if (!$logged_in): ?>
	  <h1 class="h3 mb-3 font-weight-normal">Please Enter AuthCode</h1>
	 <?php if ($err_msg): ?><h2 class="h3 mb-3 font-weight-normal message"><?php echo $err_msg; ?></h2> <?php endif; ?>
	  <label for="inputPassword" class="sr-only">Auth Code</label>
	  <input type="text" name="authcode" id="inputPassword" class="form-control" placeholder="Auth Code" required autofocus>
	  <input type="hidden" name="stage" value="1">
	  <button class="btn btn-lg btn-primary btn-block" type="submit">Review</button>
	 <?php else: ?>
	  <input type="hidden" name="stage" value="2">
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
	  <?php if ($photo) 
	{
	  echo '<tr><td colspan=2><img class="photo" src="data:'.$mime_type.';base64,'.$photo.'"</td></tr>';
	}
	  ?>
	  <tr>
	   <td colspan="2">
		<div>
	<table class="course-details">
	<tr><td>10d</td><td>STP</td><td>20d</td><td>30d</td><td>45d</td><td>60d</td><td>Service</td><td>10SPL</td><td>TSC</td></tr>
	<tr>
	<?php
	   $course_types = array('ac_10d', 'ac_stp', 'ac_20d', 'ac_30d', 'ac_45d', 'ac_60d', 'ac_service', 'ac_spl', 'ac_tsc');
	   foreach($course_types as $c)
		  echo "<td>".$row[$c]."</td>";
	?>
		</tr>
	</table>
	</div>
		</td>
	  </tr>	  
	  <?php
			$fields_no = array('al_committed' => 'Committed to tradition?', 'al_exclusive_2yrs' => 'Practicing Vipassana exclusively for 2 years?', 'al_5_precepts' => 'Maintained 5 precepts?', 'al_intoxicants' => 'Abstained from intoxicants?', 'al_sexual_misconduct' => 'Abstained from sexual misconduct?', 'al_spouse_approve' => 'Spouse Approves?');
			$fields_yes = array( 'al_left_course' => 'Left course before?','al_left_course_details' => 'Left course details', 'al_reduce_practice' => 'Asked to reduce practice?', 'al_personal_tragedy' => 'Recent personal tragedy');
			$i = 1;
				
			foreach( $fields_no as $f => $l )
			  if ($row[$f] == '0') {
			  	if ($i == '1') {
				  	print "<tr><td colspan='2'><b>Applicant does not fulfil the following criteria</b><br>(Please discuss with the applicant & proceed)</td></tr>";
				  	$i--;
				  }
					print "<tr><td>".$l."</td><td>No</td></tr>";
				}

			foreach( $fields_yes as $f => $l)
				   if ($row[$f] == '1')
						print "<tr><td>".$l."</td><td>Yes</td></tr>";
	  ?>

	<tr>
		<td colspan=2><b>I would like to </b></td>
	</tr>
	<tr>
	<?php
		print $status_opt;
	?>
	</tr>
	<?php if ($cat == 0) { ?>
	<tr class="areat-row">
		<td>Area Teacher</td><td><select class="areat-select" name="areat" required><?php print $select_area_t; ?></select></td>
	</tr>
	 <?php } ?>	
	  
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




