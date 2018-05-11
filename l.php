<?php

include_once("constants.inc");

$out = '';
if ( ! isset($_GET['a']) )
   return;

$current_dir = getcwd();
chdir( $APP_ROOT );
define('DRUPAL_ROOT', $APP_ROOT);
require_once $APP_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

//echo simple_crypt($_GET['a'], 'e');
$decrypt = simple_crypt( $_GET['a'], 'd');
//$decrypt = $_GET['a'];

$temp = explode("-", $decrypt);
if ( count($temp) < 2) return;

$app_id = $temp[0];
$letter_id = $temp[1];


$out = '';
$ret = dh_get_letter('applicant', $app_id, '', $letter_id );
if ( $ret['result'] )
{
   $out = $ret['body'];
}
$app_url = variable_get('applicant_url', '');
chdir( $current_dir );

$out = str_replace($app_url,'<a href="'.$app_url.'">'.$app_url.'</a>', $out);

?>
<html>
<head>
<style>
   pre{
       padding: 25px;
       font-size: 16px;
       font-family: Consolas, Menlo, Monaco, Lucida Console, Liberation Mono, DejaVu Sans Mono, Bitstream Vera Sans Mono, Courier New, monospace, serif;
       background-color: #fcfcfc;
       white-space: pre-wrap;       /* Since CSS 2.1 */
       white-space: -moz-pre-wrap;  /* Mozilla, since 1999 */
       white-space: -pre-wrap;      /* Opera 4-6 */
       white-space: -o-pre-wrap;    /* Opera 7 */
       word-wrap: break-word;       /* Internet Explorer 5.5+ */
   }
   .container { width: 100%; margin: 20px 0; }
   .main { width: 85%; margin: 0 auto; }
</style>
</head>
<body>
<div class="container">
  <div class="main">
    <pre>
	<?php print $out; ?>
    </pre>
  </div>
</div>
</body>
</html>
