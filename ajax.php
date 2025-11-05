<?php
require_once(__DIR__ . '/../../config.php');
require_login();
header('Content-Type: application/json');

global $DB, $CFG, $USER;

// === Parameters ===
$companyid = 0;
if (class_exists('iomad') && method_exists('iomad', 'get_my_companyid')) {
    $companyid = iomad::get_my_companyid(context_system::instance(), false);
}
$timerange = optional_param('timerange', 7, PARAM_INT);
if ($timerange <= 0) { $timerange = 7; }

$since = time() - ($timerange * 24 * 60 * 60);
$until = time();

// === Try Moodle Cache (file/redis) ===
$cache = \cache::make('block_user_activity_graphs', 'graphdata');
$cachekey = "company:{$companyid}:timerange:{$timerange}";
if ($cached = $cache->get($cachekey)) {
    echo $cached;
    exit;
}

// === Optimized queries ===
$params = ['since' => $since, 'until' => $until];
$companyfilter = '';
if ($companyid) {
    $companyfilter = " AND l.userid IN (SELECT cu.userid FROM {company_users} cu WHERE cu.companyid = :companyid) ";
    $params['companyid'] = $companyid;
}

// --- 1️⃣ Daily Logins ---
$sql_daily = "
    SELECT FROM_UNIXTIME(l.timecreated, '%Y-%m-%d') AS logindate,
           COUNT(DISTINCT l.userid) AS usercount
    FROM {logstore_standard_log} l
    WHERE l.action = 'loggedin'
      AND l.timecreated >= :since
      AND l.timecreated < :until
      {$companyfilter}
    GROUP BY logindate
    ORDER BY logindate ASC
";
$dailylogins = $DB->get_records_sql($sql_daily, $params);

// --- 2️⃣ Top Courses ---
$sql_top = "
    SELECT c.fullname AS coursename, COUNT(*) AS views
    FROM {logstore_standard_log} l
    JOIN {course} c ON l.courseid = c.id
    WHERE l.action = 'viewed'
      AND l.timecreated >= :since
      AND l.timecreated < :until
      {$companyfilter}
      AND c.format != 'site'
    GROUP BY c.id, c.fullname
    ORDER BY views DESC
    LIMIT 5
";
$topcourses = $DB->get_records_sql($sql_top, $params);

// --- 3️⃣ Enrolments ---
$params_enrol = [];
if ($companyid) $params_enrol['companyid'] = $companyid;

$sql_enrol = "
    SELECT c.fullname AS coursename, COUNT(ue.id) AS enrolments
    FROM {user_enrolments} ue
    JOIN {enrol} e ON ue.enrolid = e.id
    JOIN {course} c ON e.courseid = c.id
    " . ($companyid ? " WHERE ue.userid IN (SELECT cu.userid FROM {company_users} cu WHERE cu.companyid = :companyid) " : "") . "
      AND c.format != 'site'
    GROUP BY c.id, c.fullname
    ORDER BY enrolments DESC
    LIMIT 5
";
$enrolments = $DB->get_records_sql($sql_enrol, $params_enrol);

// === Build JSON Response ===
$response = json_encode([
    'success' => true,
    'companyid' => $companyid,
    'dailylogins' => array_values($dailylogins),
    'topcourses' => array_values($topcourses),
    'enrolments' => array_values($enrolments),
]);

// === Cache for 5 minutes ===
$cache->set($cachekey, $response, 300);

echo $response;
exit;
