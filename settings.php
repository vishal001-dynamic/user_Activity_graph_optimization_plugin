<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('block_user_activity_graphs', get_string('pluginname', 'block_user_activity_graphs'));

    $settings->add(new admin_setting_configtext(
        'block_user_activity_graphs/blocktitle',
        get_string('blocktitle', 'block_user_activity_graphs'),
        get_string('blocktitledesc', 'block_user_activity_graphs'),
        'User Activity Graphs'
    ));

    $ADMIN->add('blocks', $settings);
}
