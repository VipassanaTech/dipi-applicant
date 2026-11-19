<?php

include_once('vendor/autoload.php');
include_once("constants.inc");
include_once("dana-s3.inc");
include_once("dipi-submit-db.inc");   // safe lookups and saving

$PHOTO_DIR = "/dhamma/web/files/dipi/photo-id";

/*
if ( $_SERVER['REMOTE_ADDR'] <> '62.138.24.94' )
{
   echo "Invalid IP!";
   logit( "Invalid IP ".$_SERVER['REMOTE_ADDR'].print_r($_REQUEST, true) );
}
*/
//logit(print_r($_REQUEST, true));
$data = $_REQUEST;

// An application must name its centre and course. Without them it would be filed under centre 0, where no
// centre sees it (only test and empty posts have ever arrived like that). Refuse it before anything is saved.
$centre_id = (int) dsub_text(isset($data['centre']) ? $data['centre'] : '');
$course_id = (int) dsub_text(isset($data['course']) ? $data['course'] : '');
if ($centre_id <= 0 || $course_id <= 0)
{
    logit("Refused: no centre or course (centre=[" . dsub_log_name(dsub_text(isset($data['centre']) ? $data['centre'] : '')) . "], course=["
        . dsub_log_name(dsub_text(isset($data['course']) ? $data['course'] : '')) . "])");
    http_response_code(400);
    print("Not saved: centre or course missing");
    exit;
}

db_connect();
$system_uids = db_sql_get_options("select td_key, td_val1 from dh_type_detail where td_type = 'COURSE-APPLICANT'");

$app['a_old'] = $data['a_old']; // Assuming old student, since first phase is only for seva
if ($data['a_type'] == '')
   $data['a_type'] = 'Student';
$app['a_type'] = $data['a_type'];
$app['a_status'] =  '';
$app['a_source'] = 'ExtApp';
$app['a_center'] = $centre_id;
$app['a_course'] = $course_id;
$app['a_created_by'] = $system_uids['COURSE-APPLICANT-UID'];
$app['a_created'] = date('Y-m-d H:i:s');
$app['a_updated_by'] = $system_uids['COURSE-APPLICANT-UID'];
$app['a_updated'] = date('Y-m-d H:i:s');
$app['a_attended'] = 0;

if (!isset($app['a_m_name'])) {
    $app['a_m_name'] = '';
}

$ignored = array();
foreach ($data as $key => $value)
{
    // Fields only DIPI may set (status, centre, approvals, ...) are not taken from the post.
    if (dsub_posted_protected($key))
    {
        $ignored[] = dsub_log_name($key);
        continue;
    }
    if ( substr($key,0,2) == 'a_')
        $app[$key] = $value;
    if ( substr($key,0,3) == 'ac_')
    {
        if ($data['a_old'])
        {
            if (in_array($key, array('ac_first_location', 'ac_last_location', 'ac_first_teacher', 'ac_last_teacher')))
                $app_ac[$key."_str"] = $value;
            else
                $app_ac[$key] = $value;
        }
    }
    if ( substr($key,0,3) == 'al_')
    {
        if ($data['a_old'])
            $app_al[$key] = $value;
    }    
    if ( substr($key,0,3) == 'ae_')
    {
        if (in_array($key, array('ae_desc_other_technique', 'ae_desc_mental', 'ae_desc_physical', 'ae_desc_medication', 'ae_desc_addiction_current', 'ae_pregnant_detail', 'ae_teach_other_details')))
        {
            if ( (trim($value) <> '0') && (trim($value) <> '') )
            {
                $app_ae[$key] = $value;
                if (in_array($key, array('ae_desc_physical', 'ae_desc_mental')))
                    $app[str_replace("ae_desc", "a_problem", $key)] = 1;
                elseif ($key == 'ae_desc_addiction_current')
                    $app['a_addiction_current'] = 1;
                elseif ($key == 'ae_pregnant_detail')
                    $app_ae['ae_pregnant'] = 1;
                elseif ($key == 'ae_teach_other_details')
                    $app['a_teach_others'] = 1;
                else
                    $app[str_replace("ae_desc", "a", $key)] = 1;
            }
        }
        else
        {
            $app_ae[$key] = $value;
        }
    }
}
if ($ignored)
    logit("Ignored protected fields: " . implode(', ', $ignored));

if ($app['a_friend_family_details'])
    $app['a_friend_family']=1;

if(isset($app['a_dob_s']))
{
	if($app['a_dob_s'])
		$app['a_dob'] = $app['a_dob_s'];

	unset($app['a_dob_s']);
}


if ($data['country'])
{
    unset($app['a_state']);
    try {
        $temp = trim(file_get_contents("https://schedule.vridhamma.org/t.php?t=".urlencode($data['country'])));
        $temp = explode("|", $temp);
        $country = dsub_value("select c_code from dh_country where c_name = ? limit 1", array(isset($temp[1]) ? $temp[1] : ''));
        $state = dsub_value("select s_code from dh_state where s_country = ? and s_name = ? limit 1", array($country, $temp[0]));
        $app['a_state'] = $state;
        $app['a_country'] = $country;

    } catch (Exception $e) {
        logit("Failed to get country - ".$e->getMessage()  ) ;   
    }
}

if (isset($data['photo_id_type']))
{
    if ($data['photo_id_type'] == 'Voter ID')
        $app['a_voter_id'] = $data['photo_id_val'];
    elseif ($data['photo_id_type'] == 'Passport')
        $app['a_passport'] = $data['photo_id_val'];
    elseif ($data['photo_id_type'] == 'Aadhar')
        $app['a_aadhar'] = $data['photo_id_val'];
    elseif ($data['photo_id_type'] == 'Pancard')
        $app['a_pancard'] = $data['photo_id_val'];
}

try {
    $mobile_format = \libphonenumber\PhoneNumberUtil::getInstance();
    $m_phone = $mobile_format->parse($app['a_phone_mobile'], null, null, true);
    $m_country_code = $m_phone->getCountryCode();
    $app['a_mob_country'] = (string)$m_country_code;
    $num = str_replace( "+", "", $app['a_phone_mobile']);
    $num = substr($num, strlen((string)$m_country_code));
    $app['a_phone_mobile'] = $num;
    
} catch (Exception $e) {
     logit("Failed to get phone country code - ".$e->getMessage()  ) ;      
}



if ($app['a_city'] <> '')
{
    $city_id = dsub_value("select c_id from dh_city where c_country = ? and c_state = ? and c_name = ? limit 1",
        array($app['a_country'], $app['a_state'], $app['a_city']));
    if ( $city_id <> '')
        $app['a_city'] = $city_id;
    else
    {
        $city = dsub_value("select ci.c_id from dh_pin_code p left join dh_city ci on p.pc_city=ci.c_id left join
            dh_state s on (ci.c_state=s.s_code and ci.c_country=s.s_country) left join dh_country co on ci.c_country=co.c_code where
            pc_pin = ? limit 0,1", array($app['a_zip']));
        if ($city > 0)
            $app['a_city'] = $city;
        else
        {
            // We just could not find the city, so lets just add it 
            $f['c_country'] = $app['a_country'];
            $f['c_state'] = $app['a_state'];
            $f['c_name'] = $app['a_city'];
            $city = dsub_insert('dh_city', $f);
            $app['a_city'] = $city;
        }
    }
}

if ($app['a_f_name'] <> '')
    $app['a_f_name'] = ucwords(strtolower(trim($app['a_f_name'])));

if ($app['a_m_name'] <> '')
    $app['a_m_name'] = ucwords(strtolower(trim($app['a_m_name'])));

if ($app['a_l_name'] <> '')
    $app['a_l_name'] = ucwords(strtolower(trim($app['a_l_name'])));

if ($app['a_email'] <> '')
    $app['a_email'] = strtolower(trim($app['a_email']));

if ($app['a_phone_mobile'] <> '')
    $app['a_phone_mobile'] = trim($app['a_phone_mobile']);

$app_id = dsub_insert('dh_applicant', $app);
if ( isset($data['_photo']['data']) && ($data['_photo']['data'] <> ''))
{
    $temp = base64_decode($data['_photo']['data']);
    $path = pathinfo($data['_photo']['name']);
    $ext = preg_replace('/[^A-Za-z0-9]/', '', isset($path['extension']) ? $path['extension'] : '');
    $dir = $PHOTO_DIR."/".$app['a_center']."/".$app['a_course'];
    $fname = $dir."/app-".$app_id.".".$ext;
    /*if (!is_dir($dir))
        mkdir($dir, 0755, true);*/
    $file_local = tempnam("/var/dana", "photo-");
    file_put_contents($file_local, $temp);
    $ff['a_photo'] = "private://photo-id/".$app['a_center']."/".$app['a_course']."/app-$app_id.".$ext;
    $ret = s3_put_file('vri-dipi', $file_local, str_replace("private://", '', $ff['a_photo']));
    if (!$ret['success'])
        logit($ret['messsage']);
    dsub_update_by_id('dh_applicant', $ff, 'a_id', $app_id);
}

if(isset($app_al['al_at_year']) && $app_al['al_at_year'] > 0)
  $app_ac['ac_teacher'] = 1;

//dh_send_letter('applicant', $app_id, $app['a_status'] );
if ( isset($app_ae) )
{
    $app_ae['ae_applicant'] = $app_id;
    $app_ae['ae_updated_by'] = $app_ae['ae_created_by'] = $system_uids['COURSE-APPLICANT-UID'];
    $app_ae['ae_updated'] = $app_ae['ae_created'] = date('Y-m-d H:i:s');
    dsub_insert('dh_applicant_extra', $app_ae);
}
if (isset($app_ac))
{
    $app_ac['ac_applicant'] = $app_id;
    $app_ac['ac_updated_by'] = $app_ac['ac_created_by'] = $system_uids['COURSE-APPLICANT-UID'];
    $app_ac['ac_updated'] = $app_ac['ac_created'] = date('Y-m-d H:i:s');
    $temp = explode("-", $data['date_first_course']);
    $app_ac['ac_first_year'] = $temp[0];
    $app_ac['ac_first_month'] = $temp[1];
    $app_ac['ac_first_day'] = $temp[2];
    $temp = explode("-", $data['date_last_course']);
    $app_ac['ac_last_year'] = $temp[0];
    $app_ac['ac_last_month'] = $temp[1];
    $app_ac['ac_last_day'] = $temp[2];
    dsub_insert('dh_applicant_course', $app_ac);
}

if (isset($app_al))
{
    $app_al['al_applicant'] = $app_id;
    dsub_insert('dh_applicant_lc', $app_al);
}

$msg = "Application submitted successfully";
//return array("Result" => "Success", "Message" => $msg, 'ID' => $app_id );
print("Appid $app_id submitted successfully");
$old = getcwd();
chdir($APP_ROOT);
$cmd = "/usr/bin/php status-trigger.php $app_id 'Received'";
exec($cmd);


chdir($APP_ROOT);
$cmd = "/usr/bin/php action.php $app_id 'Photo'";
exec($cmd);


