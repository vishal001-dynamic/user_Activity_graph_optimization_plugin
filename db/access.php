<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'block/user_activity_graphs:addinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => array(
            'manager' => CAP_ALLOW,
            'companymanager' => CAP_ALLOW
        ),
    ),
    'block/user_activity_graphs:myaddinstance' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'user' => CAP_ALLOW
        ),
    ),
);
