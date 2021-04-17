<?php

include_once('vendor/autoload.php');
include_once("constants.inc");


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

db_connect();
$system_uids = db_sql_get_options("select td_key, td_val1 from dh_type_detail where td_type = 'COURSE-APPLICANT'");

$app['a_old'] = $data['a_old']; // Assuming old student, since first phase is only for seva
if ($data['a_type'] == '')
   $data['a_type'] = 'Student';
$app['a_type'] = $data['a_type'];
$app['a_status'] =  '';
$app['a_source'] = 'ExtApp';
$app['a_center'] = $data['centre'];
$app['a_course'] = $data['course'];
$app['a_created_by'] = $system_uids['COURSE-APPLICANT-UID'];
$app['a_created'] = date('Y-m-d H:i:s');
$app['a_updated_by'] = $system_uids['COURSE-APPLICANT-UID'];
$app['a_updated'] = date('Y-m-d H:i:s');
$app['a_attended'] = 0;
$app['a_m_name'] = '';

foreach ($data as $key => $value)
{
    if ( substr($key,0,2) == 'a_')
        $app[$key] = $value;
    if ( substr($key,0,3) == 'ac_')
    {
        if ($data['a_old'])
            $app_ac[$key] = $value;
    }
    if ( substr($key,0,3) == 'al_')
    {
        if ($data['a_old'])
            $app_al[$key] = $value;
    }    
    if ( substr($key,0,3) == 'ae_')
    {
        if (in_array($key, array('ae_desc_other_technique', 'ae_desc_mental', 'ae_desc_physical', 'ae_desc_medication', 'ae_desc_addiction_current')))
        {
            if ( trim($value) <> '0' )
            {
                $app_ae[$key] = $value;
                if (in_array($key, array('ae_desc_physical', 'ae_desc_mental')))
                    $app[str_replace("ae_desc", "a_problem", $key)] = 1;
                elseif ($key == 'ae_desc_addiction_current')
                    $app['a_addiction_current'] = 1;
                else
                    $app[str_replace("ae_desc", "a", $key)] = 1;
            }
        }
    }
}

if ($data['country'])
{
    unset($app['a_state']);
    try {
        $temp = trim(file_get_contents("https://www.dhamma.in/t.php?t=".$data['country']));
        $temp = explode("|", $temp);
        $q = "select c_code from dh_country where c_name='".$temp[1]."' limit 1";
        $country = db_query_single($q);
        $q = "select s_code from dh_state where s_country='".$country."' and s_name='".$temp[0]."' limit 1";
        $state = db_query_single($q);
        $app['a_state'] = $state;
        $app['a_country'] = $country;

    } catch (Exception $e) {
        logit("Failed to get country - ".$e->getMessage()  ) ;   
    }
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
    $q = "select c_id from dh_city where c_country='".$app['a_country']."' and c_state='".$app['a_state']."' and c_name='".$app['a_city']."' limit 1";
    $city_id = db_query_single($q);
    if ( $city_id <> '')
        $app['a_city'] = $city_id;
    else
    {
        $q = "select ci.c_id from dh_pin_code p left join dh_city ci on p.pc_city=ci.c_id left join
            dh_state s on (ci.c_state=s.s_code and ci.c_country=s.s_country) left join dh_country co on ci.c_country=co.c_code where
            pc_pin='".$app['a_zip']."' limit 0,1";
        $city = db_query_single($q);
        if ($city > 0)
            $app['a_city'] = $city;
        else
        {
            // We just could not find the city, so lets just add it 
            $f['c_country'] = $app['a_country'];
            $f['c_state'] = $app['a_state'];
            $f['c_name'] = $app['a_city'];
            $city = db_exec('dh_city', $f);
            $app['a_city'] = $city;
        }
    }
}


$app_id = db_exec('dh_applicant', $app);
if ($data['photo__data'] <> '')
{
    $temp = base64_decode($data['photo__data']);
    $path = pathinfo($data['photo__name']);
    $dir = $PHOTO_DIR."/".$data['centre']."/".$data['course'];
    $fname = $dir."/app-".$app_id.".".$path['extension'];
    if (!is_dir($dir))
        mkdir($dir, 0755, true);
    file_put_contents($fname, $temp);
    $ff['a_photo'] = "private://photo-id/".$data['centre']."/".$data['course']."/app-$app_id.".$path['extension'];
    db_exec('dh_applicant', $ff, ' a_id='.$app_id );
}


//dh_send_letter('applicant', $app_id, $app['a_status'] );
if ( isset($app_ae) )
{
    $app_ae['ae_applicant'] = $app_id;
    $app_ae['ae_updated_by'] = $app_ae['ae_created_by'] = $system_uids['COURSE-APPLICANT-UID'];
    $app_ae['ae_updated'] = $app_ae['ae_created'] = date('Y-m-d H:i:s');
    db_exec('dh_applicant_extra', $app_ae);
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
    db_exec('dh_applicant_course', $app_ac);
}

if (isset($app_al))
{
    $app_al['al_applicant'] = $app_id;
    db_exec('dh_applicant_lc', $app_al);
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


