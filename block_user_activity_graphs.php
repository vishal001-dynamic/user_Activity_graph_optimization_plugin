<?php
defined('MOODLE_INTERNAL') || die();

$filepath = $CFG->dirroot . '/local/iomad/lib/iomad.php';

if (file_exists($filepath)) {
    require_once($filepath);
}


class block_user_activity_graphs extends block_base
{
    public function init()
    {
        $this->title = get_string('pluginname', 'block_user_activity_graphs');
    }
    public function hide_header() {
        return true;
    }
    public function get_content()
    {
        if ($this->content !== null) {
            return $this->content;
        }

        // Get company ID
        $companyid = null; // Default value

        if (class_exists('iomad') && method_exists('iomad', 'get_my_companyid')) {
            $companyid = iomad::get_my_companyid(context_system::instance(), false);
        }


        // Check for timerange in query parameter, fallback to config or default to 7 days
        $timerange = optional_param('timerange', null, PARAM_INT);
        if ($timerange === null) {
            $timerange = isset($this->config->timerange) ? (int)$this->config->timerange : 7;
        }
        $since = time() - ($timerange * 24 * 60 * 60);

        // Determine which graphs to display based on configuration
        $showlogins = isset($this->config->showlogins) ? (bool)$this->config->showlogins : true;
        $showtopcourses = isset($this->config->showtopcourses) ? (bool)$this->config->showtopcourses : true;
        $showenrolments = isset($this->config->showenrolments) ? (bool)$this->config->showenrolments : true;

        // Fetch data for graphs with company filter if company ID exists
        $dailylogins = $showlogins ? $this->get_daily_logins($since, $companyid) : [];
        $topcourses = $showtopcourses ? $this->get_top_courses($since, $companyid) : [];
        $enrolments = $showenrolments ? $this->get_course_enrolments($since, $companyid) : [];

        // Check if there is no data at all (no logins, no views, no enrollments)
        $nodata_all = empty($dailylogins) && empty($topcourses) && empty($enrolments);

        // Determine if we should show "No data available" for specific graphs
        // Show message for Top Courses if there are logins but no course views
        $nodata_topcourses = !$nodata_all && !empty($dailylogins) && empty($topcourses) && $showtopcourses;
        // Show message for Enrolments if there are logins but no enrollments
        $nodata_enrolments = !$nodata_all && !empty($dailylogins) && empty($enrolments) && $showenrolments;

        // Prepare data for template
        $data = [
            'dailylogins' => $dailylogins,
            'topcourses' => $topcourses,
            'enrolments' => $enrolments,
            'timerange' => $timerange,
            'showlogins' => $showlogins,
            'showtopcourses' => $showtopcourses,
            'showenrolments' => $showenrolments,
            'nodata_all' => $nodata_all, // Flag for no data at all
            'nodata_topcourses' => $nodata_topcourses, // Flag for Top Courses graph
            'nodata_enrolments' => $nodata_enrolments, // Flag for Enrolments graph
            // Flags for selected time range in the dropdown
            'timerange1' => ($timerange == 1),
            'timerange7' => ($timerange == 7),
            'timerange30' => ($timerange == 30),
        ];

        $this->content = new stdClass();
        $this->content->text = $this->render_graphs($data);
        $this->content->footer = '';

        return $this->content;
    }

    private function render_graphs($data)
    {
        global $PAGE;
        $renderer = $PAGE->get_renderer('block_user_activity_graphs');
        return $renderer->render_graphs($data);
    }

    private function get_daily_logins($since, $companyid = null)
    {
        global $DB;
        $sql = "
            SELECT
                FROM_UNIXTIME(l.timecreated, '%d-%m-%Y') AS logindate,
                COUNT(DISTINCT l.userid) AS usercount
            FROM
                {logstore_standard_log} l
        ";
        $params = ['since' => $since];

        if ($companyid) {
            $sql .= " JOIN {company_users} cu ON l.userid = cu.userid";
            $sql .= " WHERE l.action = 'loggedin' AND l.timecreated >= :since AND cu.companyid = :companyid";
            $params['companyid'] = $companyid;
        } else {
            $sql .= " WHERE l.action = 'loggedin' AND l.timecreated >= :since";
        }

        $sql .= "
            GROUP BY
                logindate
            ORDER BY
                logindate ASC
        ";

        try {
            return $DB->get_records_sql($sql, $params);
        } catch (dml_exception $e) {
            return [];
        }
    }

    private function get_top_courses($since, $companyid = null)
    {
        global $DB;

        $params = ['since' => $since];
        $targetCondition = "l.target = 'course' OR l.target = 'course_module'";

        $sql = "
            SELECT
                c.fullname AS coursename,
                COUNT(l.userid) AS views
            FROM
                {logstore_standard_log} l
            JOIN
                {course} c ON l.courseid = c.id
        ";

        if ($companyid) {
            $sql .= "
                JOIN {company_users} cu ON l.userid = cu.userid
                WHERE l.action = 'viewed'
                  AND ($targetCondition)
                  AND cu.companyid = :companyid
                  AND c.format != 'site'
            ";
            $params['companyid'] = $companyid;
        } else {
            $sql .= "
                WHERE l.action = 'viewed'
                  AND ($targetCondition)
                  AND c.format != 'site'
            ";
        }

        $sql .= "
            GROUP BY c.id, c.fullname
            ORDER BY views DESC
            LIMIT 5
        ";

        try {
            return $DB->get_records_sql($sql, $params);
        } catch (dml_exception $e) {
            return [];
        }
    }


    private function get_course_enrolments($since, $companyid = null)
    {
        global $DB;
        $sql = "
            SELECT
                c.fullname AS coursename,
                COUNT(ue.id) AS enrolments
            FROM
                {user_enrolments} ue
            JOIN
                {enrol} e ON ue.enrolid = e.id
            JOIN
                {course} c ON e.courseid = c.id
        ";
        $params = ['since' => $since];

        if ($companyid) {
            $sql .= " JOIN {company_users} cu ON ue.userid = cu.userid";
            $sql .= " WHERE cu.companyid = :companyid AND c.format != 'site'";
            $params['companyid'] = $companyid;
        } else {
            $sql .= " WHERE c.format != 'site'";
        }

        $sql .= "
            GROUP BY
                c.id, c.fullname
            ORDER BY
                enrolments DESC
            LIMIT 5
        ";

        try {
            return $DB->get_records_sql($sql, $params);
        } catch (dml_exception $e) {
            return [];
        }
    }

    public function has_config()
    {
        return true;
    }

    public function instance_allow_config()
    {
        return true;
    }
}
