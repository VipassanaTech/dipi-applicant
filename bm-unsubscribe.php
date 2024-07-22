<?php
require_once("vendor/autoload.php");
include_once("constants.inc");

$auth = htmlentities(addslashes($_REQUEST['authcode']));
$orig_auth = openssl_decrypt(hex2bin($auth),"AES-128-ECB",$BULK_MAIL_AUTH_PASS);
$auth_array = explode('||||', $orig_auth);
$set_error = 0;
$bmu_id = '';
$center_name = '';
$success_msg = '';
$error_msg ='';

if(count($auth_array) == 3 && $auth_array[1] == $auth_array[2])
{
  $email = htmlentities(addslashes($auth_array[0]));
  $center = htmlentities(addslashes($auth_array[1]));
}
else
{
  $set_error = 1;
  $error_msg = "Not a Valid URL.";
}

if(!$set_error)
{
  $DB_CONN = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
   if (! $DB_CONN)
   {
      $set_error = 1;
      $error_msg = "Connect Failed!";
   }
}

if(!$set_error)
{
  mysqli_set_charset($DB_CONN, 'utf8mb4');
  $q = "select c_name from dh_center where c_id='$center'";
  $res = mysqli_query($DB_CONN, $q);

  if(!$res)
  {
    $set_error = 1;
    $error_msg = "Query Failed!";
  }
}

if(!$set_error)
{
  if((mysqli_num_rows($res) > 0))
  {
    $c_row = mysqli_fetch_array($res);
    $center_name = $c_row['c_name'];
  }
  else
  {
    $set_error = 1;
    $error_msg = "Center Not Found!";
  }
}

if(!$set_error)
{
  $q = "select bmu_id from dh_bulk_mail_unsubscribe where bmu_email='$email' and bmu_center='$center'";
  $hand = mysqli_query($DB_CONN, $q);
  if(!$hand)
  {
    $set_error = 1;
    $error_msg = "Query Failed!";
  }
  elseif((mysqli_num_rows($hand) > 0))
  {
    $row = mysqli_fetch_array($hand);
    $bmu_id = $row['bmu_id'];
    if(isset($_REQUEST['email-center-delete']) && $_REQUEST['email-center-delete'] == 1)
    {
      $q = "delete from dh_bulk_mail_unsubscribe where bmu_id='$bmu_id'";
      mysqli_query($DB_CONN, $q);
      $success_msg = "Subscribed Successfully.";
      $bmu_id = '';
    }
  }
  else
  {
    if(isset($_REQUEST['email-center-insert']) && $_REQUEST['email-center-insert'] == 1)
    {
      $q = "insert into dh_bulk_mail_unsubscribe (bmu_email, bmu_center) values ('$email', '$center')";
      mysqli_query($DB_CONN, $q);
      $success_msg = "Unsubscribed Successfully.";
      $bmu_id = 1;
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

    <title>Unsubscribe</title>

    <link href="/bootstrap.min.css" rel="stylesheet">
    <link href="/signin.css" rel="stylesheet">
    <style>

    </style>
    <script src="/jquery.min.js"></script>
    <script src="/bootstrap.min.js"></script>
  </head>

  <body class="text-center">
    <?php if(!$set_error): ?>
      <form class="form-signin" method="POST" action="<?php echo htmlentities($_SERVER['REQUEST_URI']);?>" enctype="multipart/form-data">
        <!-- <h1 class="h3 mb-3 font-weight-normal">Auth Pass: <?php echo $auth_pass; ?></h1>
        <h1 class="h3 mb-3 font-weight-normal">Auth: <?php echo $auth; ?></h1>
        <h1 class="h3 mb-3 font-weight-normal">OrigAuth: <?php echo $orig_auth; ?></h1>
        <h1 class="h3 mb-3 font-weight-normal">Email: <?php echo $email; ?></h1>
        <h1 class="h3 mb-3 font-weight-normal">Center: <?php echo $center; ?></h1> -->
        <h1 class="h3 mb-3 font-weight-normal"><?php if($success_msg) echo $success_msg; ?></h1>
        <?php if(!$bmu_id): ?>
          <input type="hidden" name="email-center-insert" value="1">
          <h1 class="h3 mb-3 font-weight-normal">Click Unsubscribe button in order to unsubscribe outreach emails from <?php echo $center_name; ?> to <?php echo $email; ?></h1>
          <button class="btn btn-lg btn-primary btn-block" type="submit">Unsubscribe</button>
        <?php else: ?>
          <input type="hidden" name="email-center-delete" value="1">
          <h1 class="h3 mb-3 font-weight-normal">Click Subscribe button in order to subscribe outreach emails from <?php echo $center_name; ?> to <?php echo $email; ?></h1>
          <button class="btn btn-lg btn-primary btn-block" type="submit">Subscribe</button>
        <?php endif; ?>
      </form>
    <?php else: ?>
      <h1 class="h3 mb-3 font-weight-normal error"><?php echo $error_msg; ?></h1>
    <?php endif; ?>
  </body>
</html>
