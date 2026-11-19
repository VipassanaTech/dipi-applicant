<?php
/*
 * Safe database helpers for dipi-submit.php (applications posted by schedule.vridhamma.org).
 *
 * - Lookups (city, PIN, country, state) send the typed text as a parameter, never inside the SQL,
 *   so a name like "Xi'an" or a PIN like "I don't know" is matched instead of breaking the query.
 * - Saving checks every field name against the table's real columns and escapes every value with
 *   the database's own escaping. Unknown field names are dropped and logged.
 * - Fields that only DIPI may set (status, centre, course, approvals, ...) cannot come from the post:
 *   dsub_protected() tells the copy loop in dipi-submit.php to skip them.
 * - A lookup that fails is logged ("Lookup failed ...") instead of being taken as "not found".
 *
 * Uses the connection made by db_connect() and logit() from constants.inc.
 */

/* Fields a post may never set, per table. DIPI sets these itself (or later, in DIPI). */
function dsub_protected($table, $field) {
  static $protected = array(
    'dh_applicant' => array('a_id', 'a_center', 'a_course', 'a_status', 'a_pre_status', 'a_pc_date', 'a_rc_date', 'a_conf_no',
      'a_attended', 'a_attended_mail', 'a_referral', 'a_alist', 'a_source', 'a_source_id', 'a_internal_note', 'a_xml_msg_id',
      'a_xml_id', 'a_student', 'a_transferred_course', 'a_created', 'a_created_by', 'a_updated', 'a_updated_by', 'a_login',
      'a_auth_code', 'a_processed', 'a_uri', 'a_photo', 'a_vray_id'),
    'dh_applicant_extra' => array('ae_id', 'ae_applicant', 'ae_created', 'ae_created_by', 'ae_updated', 'ae_updated_by'),
    'dh_applicant_course' => array('ac_id', 'ac_applicant', 'ac_teacher', 'ac_teacher_code', 'ac_comment', 'ac_created',
      'ac_created_by', 'ac_updated', 'ac_updated_by'),
    'dh_applicant_lc' => array('al_id', 'al_applicant', 'al_recommending_id', 'al_recommending_comments', 'al_recommending_auth',
      'al_recommending_approved', 'al_area_at', 'al_area_at_id', 'al_area_at_comments', 'al_area_auth', 'al_area_at_approved'),
  );
  return isset($protected[$table]) && in_array($field, $protected[$table], TRUE);
}

/* TRUE when a posted field (a_, ae_, ac_, al_ ...) is one only DIPI may set. */
function dsub_posted_protected($key) {
  $key = (string) $key;
  $tables = array('a_' => 'dh_applicant', 'ae_' => 'dh_applicant_extra', 'ac_' => 'dh_applicant_course', 'al_' => 'dh_applicant_lc');
  foreach ($tables as $pre => $table)
    if (strncmp($key, $pre, strlen($pre)) == 0)
      return dsub_protected($table, $key);
  return FALSE;
}

/* The table's real column names (as keys). Read once per table per request. */
function dsub_columns($table) {
  global $DB_CONN;
  static $cache = array();
  if (!isset($cache[$table])) {
    $cache[$table] = array();
    $r = mysqli_query($DB_CONN, "show columns from `" . str_replace('`', '', $table) . "`");
    if ($r) {
      while ($row = mysqli_fetch_row($r))
        $cache[$table][$row[0]] = TRUE;
      mysqli_free_result($r);
    }
    else
      logit("Lookup failed (columns of $table): " . mysqli_error($DB_CONN));
  }
  return $cache[$table];
}

/* A field name made safe to write in the log (posted names can be anything). */
function dsub_log_name($name) {
  return substr(preg_replace('/[^A-Za-z0-9_ .,=-]/', '?', (string) $name), 0, 60);
}

/* A value as the old code saved it: text as is, empty for nothing or a list. */
function dsub_text($v) {
  return (is_array($v) || is_object($v) || $v === NULL) ? '' : (string) $v;
}

/* The first column of the first row of $sql, run with ? parameters; '' when there is no row.
 * A failed query is logged as "Lookup failed" and also gives ''. */
function dsub_value($sql, $params = array()) {
  global $DB_CONN;
  $st = mysqli_prepare($DB_CONN, $sql);
  if (!$st) {
    logit("Lookup failed: " . mysqli_error($DB_CONN) . " | " . preg_replace('/\s+/', ' ', $sql));
    return '';
  }
  $bind = array();
  foreach (array_values($params) as $i => $p)
    $bind[$i] = dsub_text($p);
  if ($bind)
    mysqli_stmt_bind_param($st, str_repeat('s', count($bind)), ...$bind);
  $val = '';
  if (!mysqli_stmt_execute($st))
    logit("Lookup failed: " . mysqli_stmt_error($st) . " | " . preg_replace('/\s+/', ' ', $sql));
  else {
    $out = NULL;
    mysqli_stmt_bind_result($st, $out);
    if (mysqli_stmt_fetch($st))
      $val = ($out === NULL) ? '' : $out;
  }
  mysqli_stmt_close($st);
  return $val;
}

/* "col = 'value'" pairs / column list for $data, keeping only real columns of $table. */
function dsub_fields($table, $data) {
  global $DB_CONN;
  $cols = dsub_columns($table);
  $out = array();
  $ignored = array();
  foreach ($data as $k => $v) {
    if (!isset($cols[$k])) {
      $ignored[] = dsub_log_name($k);
      continue;
    }
    $out[$k] = "'" . mysqli_real_escape_string($DB_CONN, dsub_text($v)) . "'";
  }
  if ($ignored)
    logit("Ignored fields for $table (not columns): " . implode(', ', $ignored));
  return $out;
}

/* Insert $data into $table; returns the new id (0 when the insert failed, as before). */
function dsub_insert($table, $data) {
  global $DB_CONN;
  $f = dsub_fields($table, $data);
  if (!$f) {
    logit("Nothing to insert into $table");
    return 0;
  }
  $sql = "insert into $table (" . implode(",", array_keys($f)) . ") values (" . implode(", ", $f) . ")";
  logit($sql);
  if (!mysqli_query($DB_CONN, $sql)) {
    logit(mysqli_error($DB_CONN));
    return 0;
  }
  return mysqli_insert_id($DB_CONN);
}

/* Update the row of $table whose $idcol is $id (a number) with $data. */
function dsub_update_by_id($table, $data, $idcol, $id) {
  global $DB_CONN;
  $f = dsub_fields($table, $data);
  if (!$f || !isset(dsub_columns($table)[$idcol]))
    return FALSE;
  $set = array();
  foreach ($f as $k => $v)
    $set[] = "$k = $v";
  $sql = "update $table set " . implode(", ", $set) . " where $idcol = " . (int) $id;
  logit($sql);
  if (!mysqli_query($DB_CONN, $sql)) {
    logit(mysqli_error($DB_CONN));
    return FALSE;
  }
  return TRUE;
}
