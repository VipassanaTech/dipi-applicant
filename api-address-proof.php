<?php

include_once "constants.inc";

$LOG = "/var/log/$APP-photo-id.log";
$UPLOAD_DIR = "/dhamma/web/files/$APP";
//$UPLOAD_DIR = "/tmp/a";

function simple_crypt( $string, $action = 'e' )
{
    // you may change these values to your own
    $secret_key = 'DHAMMA4ALL_ANICCA';
    $secret_iv = 'THIS_IS_THE_END';

    $output = false;
    $encrypt_method = "AES-256-CBC";
    $key = hash( 'sha256', $secret_key );
    $iv = substr( hash( 'sha256', $secret_iv ), 0, 16 );

    if( $action == 'e' ) {
        $output = base64_encode( openssl_encrypt( $string, $encrypt_method, $key, 0, $iv ) );
    }
    else if( $action == 'd' ){
        $output = openssl_decrypt( base64_decode( $string ), $encrypt_method, $key, 0, $iv );
    }
    return $output;
}

function my_result( $q )
{
   global $DB_CONN;
   $hand = mysqli_query($DB_CONN, $q ) or logit("my_result: Could not exec query: ".mysqli_error($DB_CONN)."\n");
   if ( !$hand )
      return 0;
   if ( mysqli_num_rows( $hand ) <= 0  )
      return 0;
   $r = mysqli_fetch_array( $hand );
   return $r[0];
}


function logit1($data)
{
   global $LOG;
   $fp = fopen( $LOG , "a+");
   fwrite($fp, "[".date("Y-m-d H:i:s")."] ".$data."\n");
   fclose($fp);
}


//logit(print_r($_FILES, true));

if ( $_POST['id'] == '' )
{
   logit("Identifier not present");
   print "104: Identifier not present\n";
   exit();
}

$centre = simple_crypt($_POST['id'], 'd');
if ( !is_numeric($centre))
{
   logit("Identifier Invalid");
   print "105: Identifier Invalid\n";
   exit();
}

if ( $_POST['confno'] == '' )
{
   logit("Conf No not present");
   print "101: Conf No not present\n";
   exit();
}

$_POST['confno'] = str_replace("-", "", $_POST['confno']);

if ( !is_array($_FILES['img']) )
{
   logit("Image not present for ".$_POST['confno']);
   print "102: Image not present\n";
   exit();
}

if ( $_FILES['img']['name'] == '' )
{
   logit("Image not present for ".$_POST['confno']);
   print "103: Image not present\n";
   exit();
}

$date = date('Y-m-d');
if ( isset($_POST['date']) && ($_POST['date'] <> ''))
   $date = $_POST['date'];


//$conn = mysql_connect($DB_HOST, $DB_USER, $DB_PASS );
/*$DB_CONN = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if ( ! $conn )
{
   logit("Could not connect to DB ".$_POST['confno']." - ".mysql_error());
   print "106: No connection\n";
   exit();
}

if (! mysql_select_db($DB_NAME) )
{
   logit("Could not select DB ".$_POST['confno']." - ".mysql_error());
   print "107: No select connection\n";
   exit();
}*/
db_connect()

$course = my_result("select c_id from dh_course where c_center='$centre' and c_start='".$date."' and c_deleted=0");
if ( $course == '' )
   $course =  date("Y-m-d");

$dir = "photo-id/$centre/$course";
//$date_dir = date('Y-m-d').'-TO-'.date('Y-m-d', strtotime('+11 day'));;

if ( !file_exists( $UPLOAD_DIR.'/'.$dir ) )
{
    mkdir( $UPLOAD_DIR.'/'.$dir, 0755, true);
}


$uploadfile = $UPLOAD_DIR.'/'.$dir.'/'.$_POST['confno'];
$ext = '.jpg';
$temp = $uploadfile; $i=2;
/*while( file_exists($temp.$ext) )
{
    $temp = $uploadfile.'-'.$i++;
}
*/

$uploadfile = $temp.$ext;

if (!move_uploaded_file($_FILES['img']['tmp_name'], $uploadfile))
{
    logit("300: Could not upload file for ".$_POST['confno']);
    echo "300: Could not upload file\n";
}

//if ( ($i <= 2) && (is_numeric($course)) )
//{
   $upload_uri = str_replace($UPLOAD_DIR, 'private:/', $uploadfile);
   $q = "update dh_applicant set a_photo='$upload_uri' where a_center='$centre' and a_course='$course' and a_conf_no='".$_POST['confno']."'";
   mysqli_query($DB_CONN, $q);
   $applicant_id = my_result("select a_id from dh_applicant where a_center='$centre' and a_course='$course' and a_conf_no='".$_POST['confno']."'");
   $old = getcwd();
   chdir($APP_ROOT);
   $cmd = "/usr/bin/php action.php $applicant_id 'Photo'";
//   logit("Called - $cmd");
   exec($cmd);
   chdir($old);
//}

logit("File saved to $uploadfile - ".$_POST['confno']." - Centre: $centre, Course: $course");
print "250: ok\n";

?>
