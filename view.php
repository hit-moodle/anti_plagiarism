<?php

require_once("../../config.php");
require_once("../../backup/lib.php"); //for delete_dir_contents
    
$id = required_param('id', PARAM_INT);          // Assignment ID
$block = required_param('block', PARAM_INT);    // Block ID
$action = optional_param('action', 'view', PARAM_ALPHA);

if (! $assignment = get_record("assignment", "id", $id)) {
    error("assignment ID was incorrect");
}

if (! $course = get_record("course", "id", $assignment->course)) {
    error("Course is misconfigured");
}

require_login($course->id, false);

//header
$cm = get_coursemodule_from_instance('assignment', $id, $course->id);
$strassignments = get_string('modulenameplural', 'assignment');
$navigation = build_navigation(get_string('blockname', 'block_anti_plagiarism'), $cm);
$pagetitle = strip_tags($course->shortname.': '.$strassignments.': '.format_string($assignment->name,true).': '.get_string('blockname', 'block_anti_plagiarism'));
print_header($pagetitle, $course->fullname, $navigation, "", "", true, '', navmenu($course));

$context = get_context_instance(CONTEXT_BLOCK, $block);

$canviewall = has_capability('block/anti_plagiarism:viewall', $context);
if (!$canviewall)
    require_capability('block/anti_plagiarism:viewself', $context);
$canjudge = has_capability('block/anti_plagiarism:judge', $context);

$antipla = get_record('block_anti_plagiarism', 'assignment', $id);

$viewurl = 'view.php?id='.$id.'&block='.$block.'&action=view';
$configurl = 'view.php?id='.$id.'&block='.$block.'&action=config';

$row[] = new tabobject('view', $viewurl, get_string('view'));
if ($canjudge) 
    $row[] = new tabobject('config', $configurl, get_string('judge', 'block_anti_plagiarism'));
$tabs[] = $row;

/// Print out the tabs
print "\n".'<div class="tabs">'."\n";
print_tabs($tabs, $action);
print '</div>';

if ($action === 'config') {
    require_capability('block/anti_plagiarism:judge', $context);

    require_once('config_form.php');

    $mform = new anti_plagiarism_config_form();

    if ($fromform=$mform->get_data()){
        $fromform->assignment = $id;
        if (empty($antipla))
            insert_record('block_anti_plagiarism', $fromform);
        else {
            $fromform->id = $antipla->id;
            update_record('block_anti_plagiarism', $fromform);

            if (isset($fromform->cleanall)) {
                delete_records('block_anti_plagiarism_pairs', 'apid', $antipla->id);
            }
        }

        $antipla = get_record('block_anti_plagiarism', 'assignment', $id);

        judge($fromform);

    } else {
        if (isset($antipla->id))
            $antipla->id = $id;
        $mform->set_data($antipla);
        $mform->display();
    }
} else { //View
    if (empty($antipla)) {
        if ($canjudge)
            notice(get_string('noresults', 'block_anti_plagiarism'), $CFG->wwwroot.'/blocks/anti_plagiarism/view.php?id='.$id.'&block='.$block.'&action=config');
        else
            notice(get_string('noresultsandwait', 'block_anti_plagiarism'));
    }
    
    $pairid = optional_param('pairid', '-1', PARAM_INT);
    if ($pairid != -1) {
        require_capability('block/anti_plagiarism:confirm', $context);
        $new = new Object();
        $new->confirmed = required_param('confirmed', PARAM_INT);
        $new->id = $pairid;
        update_record('block_anti_plagiarism_pairs', $new);
    }

    if ($canviewall)
        $results = get_records('block_anti_plagiarism_pairs', 'apid', $antipla->id, 'rank');
    else {
        $select = "apid=$antipla->id AND (user1=$USER->id OR user2=$USER->id) AND confirmed=1";
        $results = get_records_select('block_anti_plagiarism_pairs', $select, 'rank');
    }

    if (!$results) {
        if ($canjudge)
            notice(get_string('noresults', 'block_anti_plagiarism'), $CFG->wwwroot.'/blocks/anti_plagiarism/view.php?id='.$id.'&block='.$block.'&action=config');
        else
            notice(get_string('noresultsandwait', 'block_anti_plagiarism'));
    }

    $confirm = has_capability('block/anti_plagiarism:confirm', $context);

    $table = new Object();
    $table->class = 'flexible antipla';
    $table->id = 'results';

    $column_name = array();
    $column_name[] = get_string('fullname').'1';
    $column_name[] = get_string('fullname').'2';
    $column_name[] = get_string('rank', 'block_anti_plagiarism');
    $column_name[] = get_string('extnames', 'block_anti_plagiarism');
    $column_name[] = get_string('info', 'block_anti_plagiarism');
    if ($confirm)
        $column_name[] = get_string('confirm');

    $table->data[] = $column_name;

    foreach($results as $result) {

        if (!$confirm && $result->confirmed == 0) //Don't show unconfirmed record to people hasn't confirm cap.
            continue;

        $column = array();

        if ($result->confirmed) {
            $grade_button1 = link_to_popup_window('/mod/assignment/submissions.php?a='.$id.'&amp;userid='.$result->user1.'&amp;mode=single&amp;offset=1', 
                'grade'.$result->user1, 
                '<img src="'.$CFG->pixpath.'/i/grades.gif" border="0" alt="'.get_string('grade').'" />', 
                500, 700,
                get_string('grade'),
                'none',
                true);
            $grade_button2 = link_to_popup_window('/mod/assignment/submissions.php?a='.$id.'&amp;userid='.$result->user2.'&amp;mode=single&amp;offset=1', 
                'grade'.$result->user2, 
                '<img src="'.$CFG->pixpath.'/i/grades.gif" border="0" alt="'.get_string('grade').'" />', 
                500, 700,
                get_string('grade'),
                'none',
                true);
            $label = get_string('unconfirm', 'block_anti_plagiarism');
            $jsconfirmmessage = '';
            $tooltip = get_string('unconfirmtooltip', 'block_anti_plagiarism');
        } else {
            $grade_button1 = '';
            $grade_button2 = '';
            $label = get_string('confirm');
            $jsconfirmmessage = get_string('confirmmessage', 'block_anti_plagiarism');
            $tooltip = get_string('confirmtooltip', 'block_anti_plagiarism');
        }

        $user = get_record('user', 'id', $result->user1);
        $column[] = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $course->id . '">' . fullname($user) . '</a>'.$grade_button1;
        $user = get_record('user', 'id', $result->user2);
        $column[] = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $user->id . '&amp;course=' . $course->id . '">' . fullname($user) . '</a>'.$grade_button2;

        $column[] = $result->rank;
        $column[] = $result->judger === 'moss' ? $result->extnames : '.doc .pdf';
        $column[] = $result->info;

        if ($confirm) {
            $column[] = print_single_button($CFG->wwwroot.'/blocks/anti_plagiarism/view.php',
                                            array('id' => $id, 'block' => $block, 'pairid' => $result->id, 'confirmed' => !$result->confirmed),
                                            $label, 'post', '_self', true, $tooltip, false, $jsconfirmmessage);
        }

        $table->data[] = $column;
    }
    print_table($table);

}

print_footer($course);

function judge($config) {
    global $CFG, $course, $id, $block;
    
    print_box_start('generalbox', 'notice');

    print_string('prepareing', 'block_anti_plagiarism');
    flush();
    $submission_path = extract_to_temp($CFG->dataroot.'/'.$course->id.'/'.$CFG->moddata.'/assignment/'.$id.'/');

    $command = eval('return '.$config->judger.'_command($config, $submission_path);');

    $output = array();
    $return = null;

    print_string('done', 'block_anti_plagiarism');
    echo '<br />';
    print_string('judging', 'block_anti_plagiarism');
    flush();
    exec($command.' 2>&1', $output, $return);

    if ($return) { //Error
        delete_dir_contents($submission_path);
        rmdir($submission_path);

        error(get_string('failed', 'block_anti_plagiarism').'<br />'.implode('<br />', $output));
    } else {
        print_string('done', 'block_anti_plagiarism');
        echo '<br />';
        print_string('parsing', 'block_anti_plagiarism');
        flush();
        $results = eval('return '.$config->judger.'_parse($output);');
        foreach($results as $result) {
            insert_record('block_anti_plagiarism_pairs', $result);
        }
        print_string('done', 'block_anti_plagiarism');
    }

    delete_dir_contents($submission_path);
    rmdir($submission_path);

    print_box_end();
    print_continue($CFG->wwwroot.'/blocks/anti_plagiarism/view.php?id='.$id.'&block='.$block.'&action=view');
}

function moss_command($config, $path) {
    global $CFG;

    if (isset($CFG->block_antipla_moss_script_path) and !empty($CFG->block_antipla_moss_script_path)) {
        $basepath = $path.'*/*';
        $path_args = array();
        $extnames = explode(' ', $config->extnames);
        if (!$extnames)
            return false;
        foreach($extnames as $extname) {
            $path_args[] = $basepath.$extname;
        }
        $path = implode(' ', $path_args);
        
        return $CFG->block_antipla_moss_script_path
            .' -l '.$config->type
            .' -m '.$config->sensitivity
            .' -d '.$path;
    } else {
        return null;
    }
}

function moss_parse($output) {
    global $antipla;

    $url = array_pop($output);
    $fp = fopen($url, 'r');
    if (!$fp) {
        error(get_string('connecterror', 'block_anti_plagiarism', $url));
    }

    $results = array();

    $rank = 1;
    $re_url = '/(http:\/\/moss\.stanford\.edu\/results\/\d+\/match\d+\.html)">.*\/(\d+)\/ \((\d+)%\)/';
    while (!feof($fp)) {
        $line = fgets($fp);

        if (preg_match($re_url, $line, $matches1)) {
            $line = fgets($fp);
            if (preg_match($re_url, $line, $matches2)) {
                $line = fgets($fp);
                if (preg_match('/(\d+)/', $line, $matches3)) {
                    $result = new stdClass();
                    $result->user1 = $matches1[2];
                    $result->user2 = $matches2[2];
                    $result->apid = $antipla->id;
                    $result->rank = $rank++;
                    $result->judger = 'moss';
                    $result->extnames = $antipla->extnames;
                    $result->judgedate = time();

                    $a = new stdClass();
                    $a->user1_percent = $matches1[3];
                    $a->user2_percent = $matches2[3];
                    $a->url = $matches1[1];
                    $a->line_count = $matches3[1];
                    $result->info = get_string('mossinfo', 'block_anti_plagiarism', $a);

                    $results[] = $result;
                } else {
                    error(get_string('parseerror', 'block_anti_plagiarism'));
                }
            } else {
                error(get_string('parseerror', 'block_anti_plagiarism'));
            }
        }
    }
    fclose($fp);

    return $results;
}

function duplication_command($config, $path) {
    global $CFG;

    if (isset($CFG->block_antipla_duplication_path) and !empty($CFG->block_antipla_duplication_path)) {
        
        return $CFG->block_antipla_duplication_path.' '.$path.' '.$path.'duplication.out';

    } else {
        return null;
    }
}

function duplication_parse($output) {
    global $antipla;

    $results = array();

    $rank = 1;
    $re = '/^([0-9\.]+) .*\/'.$antipla->assignment.'\/(\d+)\/.*\/119\/(\d+)\//';
    foreach ($output as $line) {
        if (preg_match($re, $line, $matches)) {
            $result = new stdClass();
            $result->user1 = $matches[2];
            $result->user2 = $matches[3];
            $result->apid = $antipla->id;
            $result->rank = $rank++;
            $result->judger = 'duplication';
            $result->extnames = $antipla->extnames;
            $result->judgedate = time();
            $result->info = get_string('duplicationinfo', 'block_anti_plagiarism', $matches[1]);

            $results[] = $result;
        }
    }

    return $results;
}

function extract_to_temp($source) {
    global $id, $CFG;

    // Make temp dir
    $temp_dir = $CFG->dataroot.'/temp/anti_plagiarism/'.$id.'/';
    delete_dir_contents($temp_dir);
    if (!check_dir_exists($temp_dir, true, true)) {
        error("Can't mkdir ".$temp_dir);
    }

    if ($files = get_directory_list($source)) {
        foreach ($files as $key => $file) {
            $dir = $temp_dir.dirname($file);
            if (!check_dir_exists($dir, true, true)) {
                error("Can't mkdir ".$dir);
            }

            $path_parts = pathinfo(cleardoubleslashes($file));
            $ext= $path_parts["extension"];    //The extension of the file

            if ($ext === 'rar' && !empty($CFG->block_antipla_unrar_path)) {
                $command = $CFG->block_antipla_unrar_path.' e '.$source.$file.' '.$temp_dir.dirname($file).'/ >/dev/null';
                system($command);
            } else if ($ext === 'zip') {
                unzip_file($source.$file, $temp_dir.dirname($file), false);
                //Move all files to its home root
                $basedir = $temp_dir.dirname($file).'/';
                if ($fs = get_directory_list($basedir)) {
                    foreach ($fs as $k => $f) {
                        rename($basedir.$f, $basedir.basename($f));
                    }
                }
            } else {
                if (!copy($source.$file, $temp_dir.$file))
                    error('Can\'t copy file');
            }
        }
    }

    return $temp_dir;
}
?>
