<?php
// Administrator (centre-independent) bulk-email unsubscribe landing.
// Mirrors bm-unsubscribe.php but is GLOBAL (no centre): the token decrypts to just
// an email, and opt-out is stored in dh_admin_bulk_mail_unsubscribe. Opt-out happens
// only when the user clicks the button below (a two-step confirmation), never on the
// plain link click.

include_once("constants.inc");

$auth = htmlentities(addslashes($_REQUEST['authcode']));
$email_raw = openssl_decrypt(hex2bin($auth), "AES-128-ECB", $BULK_MAIL_AUTH_PASS);

$set_error = 0;
$abu_id = '';
$success_msg = '';
$error_msg = '';
$email = '';

if ($email_raw && filter_var($email_raw, FILTER_VALIDATE_EMAIL)) {
  // A valid email has no SQL/HTML metacharacters worth escaping, but stay defensive.
  $email = addslashes($email_raw);
}
else {
  $set_error = 1;
  $error_msg = "Not a Valid URL.";
}

if (!$set_error) {
  $DB_CONN = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
  if (!$DB_CONN) {
    $set_error = 1;
    $error_msg = "Connect Failed!";
  }
}

if (!$set_error) {
  mysqli_set_charset($DB_CONN, 'utf8mb4');
  $q = "select abu_id from dh_admin_bulk_mail_unsubscribe where abu_email='$email'";
  $hand = mysqli_query($DB_CONN, $q);
  if (!$hand) {
    $set_error = 1;
    $error_msg = "Query Failed!";
  }
  elseif (mysqli_num_rows($hand) > 0) {
    $row = mysqli_fetch_array($hand);
    $abu_id = $row['abu_id'];
    // Already unsubscribed: allow re-subscribe on explicit button click.
    if (isset($_REQUEST['email-delete']) && $_REQUEST['email-delete'] == 1) {
      mysqli_query($DB_CONN, "delete from dh_admin_bulk_mail_unsubscribe where abu_id='$abu_id'");
      $success_msg = "Subscribed Successfully.";
      $abu_id = '';
    }
  }
  else {
    // Not unsubscribed: opt out only on explicit button click.
    if (isset($_REQUEST['email-insert']) && $_REQUEST['email-insert'] == 1) {
      mysqli_query($DB_CONN, "insert into dh_admin_bulk_mail_unsubscribe (abu_email, abu_created) values ('$email', NOW())");
      $success_msg = "Unsubscribed Successfully.";
      $abu_id = 1;
    }
  }
}

$email_disp = htmlentities($email_raw);
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
    <script src="/jquery.min.js"></script>
    <script src="/bootstrap.min.js"></script>
  </head>
  <body class="text-center">
    <?php if (!$set_error): ?>
      <form class="form-signin" method="POST" action="<?php echo htmlentities($_SERVER['REQUEST_URI']); ?>" enctype="multipart/form-data">
        <h1 class="h3 mb-3 font-weight-normal"><?php if ($success_msg) echo $success_msg; ?></h1>
        <?php if (!$abu_id): ?>
          <input type="hidden" name="email-insert" value="1">
          <h1 class="h3 mb-3 font-weight-normal">Click Unsubscribe to stop receiving outreach emails to <?php echo $email_disp; ?></h1>
          <button class="btn btn-lg btn-primary btn-block" type="submit">Unsubscribe</button>
        <?php else: ?>
          <input type="hidden" name="email-delete" value="1">
          <h1 class="h3 mb-3 font-weight-normal">Click Subscribe to resume receiving outreach emails to <?php echo $email_disp; ?></h1>
          <button class="btn btn-lg btn-primary btn-block" type="submit">Subscribe</button>
        <?php endif; ?>
      </form>
    <?php else: ?>
      <h1 class="h3 mb-3 font-weight-normal error"><?php echo $error_msg; ?></h1>
    <?php endif; ?>
  </body>
</html>
