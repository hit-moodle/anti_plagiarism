<?php

$settings->add(new admin_setting_configexecutable('block_antipla_duplication_path', get_string('duplicationpath', 'block_anti_plagiarism'),
                   get_string('describeduplication', 'block_anti_plagiarism'), ''));

$settings->add(new admin_setting_configexecutable('block_antipla_moss_script_path', get_string('mossscriptpath', 'block_anti_plagiarism'),
                   get_string('describemoss', 'block_anti_plagiarism'), ''));

$settings->add(new admin_setting_configexecutable('block_antipla_unrar_path', get_string('unrarpath', 'block_anti_plagiarism'),
                   get_string('describeunrar', 'block_anti_plagiarism'), ''));
?>
