<?php
// "Choose another Recommending AT" - the student's page from DIPI's "Recommending AT Returned" letter (21 Nov 2026).
// Link: choose-teacher.php?c=<code>. The code is made by DIPI for each return (dh_applicant_lc.al_choose_auth, random)
// and works once, only while the application is back at the centre (R-ATTransfer) and until the course start day.
// The student picks an Active teacher who has an email in DIPI (not the one it came back from); DIPI then sends the
// application to that teacher for review and tells the student (status-trigger.php -> update_status_external).
include_once("constants.inc");

function ct_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$code = isset($_REQUEST['c']) ? (string) $_REQUEST['c'] : '';
$err = '';
$done = '';
$app = NULL;
$teachers = array();
$db = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, isset($DB_PORT) ? $DB_PORT : 3306);
if (!$db)
	$err = 'Sorry, this page is not available right now. Please try again later.';
elseif (!preg_match('/^[0-9A-Za-z]{16,40}$/', $code))
	$err = 'This link is not valid.';
else
{
	mysqli_set_charset($db, 'utf8mb4');
	$c = mysqli_real_escape_string($db, $code);
	$res = mysqli_query($db, "select a.a_id, a.a_f_name, a.a_status, a.a_center, c.c_name, c.c_start, al.al_recommending
		from dh_applicant_lc al join dh_applicant a on a.a_id = al.al_applicant join dh_course c on c.c_id = a.a_course
		where al.al_choose_auth = '$c' limit 1");
	$app = $res ? mysqli_fetch_assoc($res) : NULL;
	if (!$app)
		$err = 'This link is not valid any more. It can be used only once.';
	elseif ($app['a_status'] != 'R-ATTransfer')
		$err = 'Your application is not waiting for a new teacher, so this link cannot be used now.';
	elseif (time() > strtotime('+1 day', strtotime($app['c_start'])))
		$err = 'The course has already started, so this link cannot be used now.';
}
if (!$err)
{
	// Active teachers with an email (the review request goes by email), except the one it came back from.
	$res = mysqli_query($db, "select CONCAT(t_f_name, ' ', t_l_name) as name, max(t_area) as area from dh_teacher
		where t_status = 'Active' and ifnull(t_email, '') <> '' group by name order by name");
	while ($res && ($t = mysqli_fetch_assoc($res)))
		if (strcasecmp(trim($t['name']), trim((string) $app['al_recommending'])) != 0)
			$teachers[$t['name']] = $t['area'];
}
if (!$err && $_SERVER['REQUEST_METHOD'] == 'POST')
{
	$chosen = isset($_POST['teacher']) ? (string) $_POST['teacher'] : '';
	if ($chosen === '' || !array_key_exists($chosen, $teachers))
		$err = 'Please choose a teacher from the list.';
	else
	{
		$id = (int) $app['a_id'];
		$res = mysqli_query($db, "select td_val1 from dh_type_detail where td_type = 'COURSE-APPLICANT' and td_key = 'COURSE-APPLICANT-UID'");
		$uid = (int) ($res ? mysqli_fetch_row($res)[0] : 0);
		$n = mysqli_real_escape_string($db, $chosen);
		$c = mysqli_real_escape_string($db, $code);
		// The DIPI Activity Log lines written by the review-row trigger name a_updated_by: the applicant.
		mysqli_query($db, "update dh_applicant set a_updated_by = $uid, a_updated = now() where a_id = $id");
		// One use: the code goes with the change (a second click finds nothing).
		mysqli_query($db, "update dh_applicant_lc set al_recommending = '$n', al_recommending_approved = 'Pending', al_choose_auth = NULL
			where al_applicant = $id and al_choose_auth = '$c'");
		if (mysqli_affected_rows($db) != 1)
			$err = 'This link is not valid any more. It can be used only once.';
		else
		{
			$was = mysqli_real_escape_string($db, (string) $app['al_recommending']);
			mysqli_query($db, "insert into dh_log (l_center, l_module, l_identifier, l_event, l_msg, l_user)
				values (" . (int) $app['a_center'] . ", 'Applicant', '$id', 'LC Workflow', 'Recommending AT chosen by the applicant: $n (was $was)', $uid)");
			// Back to review: DIPI sends it to the new teacher and tells the student.
			exec('cd ' . escapeshellarg($APP_ROOT) . ' && /usr/bin/php status-trigger.php ' . $id . ' R-ATReview > /dev/null 2>&1');
			$done = $chosen;
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>Choose another teacher</title>
	<link href="/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
	<style>
		body { padding: 24px 12px; }
		.box { max-width: 640px; margin: 0 auto; }
		.message { color: #b00020; font-weight: bold; }
		.done { color: #1b5e20; font-weight: bold; }
		.select2-container { width: 100% !important; }
	</style>
	<script src="/jquery.min.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
	<script>
		$(document).ready(function () { $('.teacher-select').select2({ placeholder: 'Type a name to search' }); });
	</script>
</head>
<body>
<div class="box">
	<h3>Choose another teacher</h3>
<?php if ($done !== ''): ?>
	<p class="done">Thank you. Your application has been sent to <?php echo ct_h($done); ?> for review.</p>
	<p>You will get an email about it.</p>
<?php elseif ($err !== '' && !$teachers): ?>
	<p class="message"><?php echo ct_h($err); ?></p>
<?php else: ?>
	<p>Dear <?php echo ct_h($app['a_f_name']); ?>,</p>
	<p>Your long course application for <strong><?php echo ct_h($app['c_name']); ?></strong> has been returned to the centre without approval from
		your Recommending Assistant Teacher <?php echo ct_h($app['al_recommending']); ?>.</p>
	<p>If you know another Assistant Teacher who is willing to approve your application, please choose them below.
		Your application will then be sent to them for review.</p>
	<?php if ($err !== ''): ?><p class="message"><?php echo ct_h($err); ?></p><?php endif; ?>
	<form method="post" action="choose-teacher.php?c=<?php echo ct_h($code); ?>">
		<div class="form-group">
			<label for="teacher">Assistant Teacher</label>
			<select name="teacher" id="teacher" class="teacher-select form-control" required>
				<option value=""></option>
				<?php foreach ($teachers as $name => $area): ?>
				<option value="<?php echo ct_h($name); ?>"><?php echo ct_h($name . ($area != '' ? ' (' . $area . ')' : '')); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<p><small>This link can be used once.</small></p>
		<button type="submit" class="btn btn-primary">Send my application to this teacher</button>
	</form>
<?php endif; ?>
</div>
</body>
</html>
