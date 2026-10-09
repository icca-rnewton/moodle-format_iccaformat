<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_format_iccaformat_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026060301) {
        $table = new xmldb_table('iccaformat_label_rules');
        $field = new xmldb_field('flipped', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'label_enabled');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026060301, 'format', 'iccaformat');
    }

    if ($oldversion < 2026060401) {
        $table = new xmldb_table('iccaformat_label_rules');
        $field = new xmldb_field('image_hidden', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'flipped');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026060401, 'format', 'iccaformat');
    }

    return true;
}
