<?php

namespace block_user_activity_graphs\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;

class renderer extends plugin_renderer_base
{
    public function render_graphs($data)
    {
        global $PAGE, $USER, $DB;

        // Check if the user is a Site Administrator or has Manager/Company Manager role
        $context = \context_system::instance();
        $is_admin = is_siteadmin($USER); // Check if user is a Site Administrator

        // Check if the user has Manager or Company Manager role
        $is_manager = false;
        $sql = "
            SELECT r.id AS roleid, r.shortname, r.name AS rolename, c.contextlevel, c.instanceid
            FROM {role_assignments} ra
            JOIN {role} r ON ra.roleid = r.id
            JOIN {context} c ON ra.contextid = c.id
            WHERE ra.userid = :userid
        ";

        $params = ['userid' => $USER->id];
        $userroles = $DB->get_records_sql($sql, $params);
        foreach ($userroles as $role) {
            if ($role->shortname === 'manager' || $role->shortname === 'companymanager') {
                $is_manager = true;
                break;
            }
        }

        // If the user is neither a Site Administrator nor a Manager/Company Manager, deny access
        if (!$is_admin && !$is_manager) {
            return ''; // Return empty string to hide graphs without any message
        }

        $js = <<<JS

        document.addEventListener('DOMContentLoaded', function () {
           
                // Show loading spinner
                const loader = document.getElementById('graphs-loading');
                const wrapper = document.getElementById('graphs-wrapper');
                if (loader) loader.style.display = 'block';
                if (wrapper) wrapper.style.display = 'none';

                // AJAX call to fetch data
                fetch(M.cfg.wwwroot + '/blocks/user_activity_graphs/ajax.php?companyid=' + {$USER->id})
                    .then(response => response.json())
                    .then(data => {
                        if (!data || !data.success) throw new Error('Invalid data');

                        const wrapLabel = (label, maxLength) => {
                            if (typeof label !== 'string') return label;
                            const bracketIndex = label.indexOf('(');
                            let visibleLabel = bracketIndex !== -1
                                ? label.substring(0, bracketIndex).trim()
                                : label;
                            if (visibleLabel.length <= maxLength) return visibleLabel;
                            let splitIndex = visibleLabel.lastIndexOf(' ', maxLength);
                            if (splitIndex === -1 || splitIndex < maxLength / 2) {
                                splitIndex = maxLength;
                            }
                            return [
                                visibleLabel.substring(0, splitIndex).trim(),
                                visibleLabel.substring(splitIndex).trim()
                            ];
                        };

                        const ctxDailyLogins = document.getElementById('dailyLoginsChart')?.getContext('2d');
                        const ctxTopCourses = document.getElementById('topCoursesChart')?.getContext('2d');
                        const ctxEnrolments = document.getElementById('enrolmentsChart')?.getContext('2d');

                        new Chart(ctxDailyLogins, {
                            type: 'bar',
                            data: {
                                labels: data.dailylogins.map(l => wrapLabel(l.logindate, 20)),
                                datasets: [{
                                    label: 'Daily Logins',
                                    data: data.dailylogins.map(l => l.usercount),
                                    backgroundColor: ['#FFBD02', '#FFA12C', '#FF8F34', '#FF7A00']
                                }]
                            },
                            options: { responsive: true }
                        });

                        new Chart(ctxTopCourses, {
                            type: 'pie',
                            data: {
                                labels: data.topcourses.map(c => wrapLabel(c.coursename, 20)),
                                datasets: [{
                                    label: 'Top 5 Courses',
                                    data: data.topcourses.map(c => c.views),
                                    backgroundColor: ['#80A20D', '#A1B71A', '#C2CC28', '#E3E135', '#FFBD02']
                                }]
                            },
                            options: { 
                                responsive: true, 
                                cutout: '50%',
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        labels: {
                                            font: { size: 11 },
                                            boxWidth: 12,
                                            padding: 8,
                                            generateLabels: function(chart) {
                                                const data = chart.data;
                                                return data.labels.map((label, i) => ({
                                                    text: wrapLabel(label, 20),
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: 'transparent', // Set border to white (or use 'transparent' to remove)
                                                    lineWidth: 1, // Ensure minimal line width
                                                    hidden: !chart.getDataVisibility(i),
                                                    index: i
                                                }));
                                            }
                                        }
                                    },
                                    tooltip: {
                                        bodyFont: { size: 10 },
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.parsed; // Use parsed for pie chart
                                                const wrappedLabel = wrapLabel(label, 20);
                                                return typeof wrappedLabel === 'string' ? `\${wrappedLabel}: \${value}` : [wrappedLabel[0], wrappedLabel[1], `: \${value}`];
                                            }
                                        }
                                    }
                                }
                             }
                        });

                        new Chart(ctxEnrolments, {
                            type: 'pie',
                            data: {
                                labels: data.enrolments.map(e => wrapLabel(e.coursename, 20)),
                                datasets: [{
                                    label: 'Top 5 Enrolled Courses',
                                    data: data.enrolments.map(e => e.enrolments),
                                    backgroundColor: ['#0D47A1', '#1976D2', '#2196F3', '#64B5F6', '#90CAF9']
                                }]
                            },
                            options: { 
                                responsive: true, 
                                cutout: '50%',
                                plugins: {
                                    legend: {
                                        position: 'right',
                                        labels: {
                                            font: { size: 11 },
                                            boxWidth: 12,
                                            padding: 8,
                                            generateLabels: function(chart) {
                                                const data = chart.data;
                                                return data.labels.map((label, i) => ({
                                                    text: wrapLabel(label, 20),
                                                    fillStyle: data.datasets[0].backgroundColor[i],
                                                    strokeStyle: 'transparent', // Set border to white (or use 'transparent' to remove)
                                                    lineWidth: 1, // Ensure minimal line width
                                                    hidden: !chart.getDataVisibility(i),
                                                    index: i
                                                }));
                                            }
                                        }
                                    },
                                    tooltip: {
                                        bodyFont: { size: 10 },
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.label || '';
                                                const value = context.parsed; // Use parsed for pie chart
                                                const wrappedLabel = wrapLabel(label, 20);
                                                return typeof wrappedLabel === 'string' ? `\${wrappedLabel}: \${value}` : [wrappedLabel[0], wrappedLabel[1], `: \${value}`];
                                            }
                                        }
                                    }
                                }
                             }
                        });

                        if (loader) loader.style.display = 'none';
                        if (wrapper) wrapper.style.display = 'block';
                    })
                    .catch(err => {
                        if (loader) loader.innerHTML = '<div class="alert alert-danger">Failed to load graphs.</div>';
                    });
            
        });
        JS;

        // Enqueue JavaScript
        $PAGE->requires->js_init_code($js);

        return $this->render_from_template('block_user_activity_graphs/graphs', $data);
    }

    private function json(array $array, string $key): string
    {
        return json_encode(array_column($array, $key)) ?: '[]';
    }
}
