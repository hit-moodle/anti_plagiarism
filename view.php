<?php

require_once('../../config.php');
require_once('../../lib/filelib.php'); // for get_file_url() and fulldelete()
require_once('../../lib/adminlib.php'); // for print_progress()
    
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
        $fromform->instance = $block;
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

    if ($canviewall) {
        $relevant = optional_param('relevant', '-1', PARAM_INT);
        if ($relevant != -1) {
            $seed = get_record('block_anti_plagiarism_pairs', 'id', $relevant);

            $select = "apid=$antipla->id AND (user1=$seed->user1 OR user2=$seed->user1)";
            $results = get_records_select('block_anti_plagiarism_pairs', $select);
            $members1 = array();
            foreach ($results as $row) {
                $members1[] = $row->user1;
                $members1[] = $row->user2;
            }

            $select = "apid=$antipla->id AND (user1=$seed->user2 OR user2=$seed->user2)";
            $results = get_records_select('block_anti_plagiarism_pairs', $select);
            $members2 = array();
            foreach ($results as $row) {
                $members2[] = $row->user1;
                $members2[] = $row->user2;
            }

            $members = array_unique(array_intersect($members1, $members2));

            $member_str = implode(',', $members);

            $select = "apid=$antipla->id AND user1 IN ($member_str) AND user2 IN ($member_str)";
            $results = get_records_select('block_anti_plagiarism_pairs', $select, 'rank');
        } else {
            $results = get_records('block_anti_plagiarism_pairs', 'apid', $antipla->id, 'rank');
        }
    } else {
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
        $column_name[] = get_string('action');

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
            //confirm button
            $args = array('id' => $id, 'block' => $block, 'pairid' => $result->id, 'confirmed' => !$result->confirmed);
            if ($relevant != -1)
                $args['relevant'] = $relevant;
            $cell = print_single_button($CFG->wwwroot.'/blocks/anti_plagiarism/view.php', $args, $label, 'post', '_self', true, $tooltip, false, $jsconfirmmessage);

            //relevant button
            $args = array('id' => $id, 'block' => $block);
            if ($relevant == $result->id) {
                $label = get_string('showall', 'block_anti_plagiarism');
                $tooltip = get_string('showalltooltip', 'block_anti_plagiarism');
            } else {
                $label = get_string('showrelevantonly', 'block_anti_plagiarism');
                $tooltip = get_string('showrelevanttooltip', 'block_anti_plagiarism');
                $args['relevant'] = $result->id;
            }

            $cell .= print_single_button($CFG->wwwroot.'/blocks/anti_plagiarism/view.php', $args, $label, 'post', '_self', true, $tooltip, false);
            $column[] = $cell;
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
    if (debugging('', DEBUG_DEVELOPER)) 
        print_object($command);

    $output = array();
    $return = null;

    print_string('done', 'block_anti_plagiarism');
    echo '<br />';

    $descriptorspec = array(
        0 => array('pipe', 'r'),  // stdin 
        1 => array('pipe', 'w'),  // stdout
        2 => array('pipe', 'w') // stderr
    );
    $proc = proc_open($command, $descriptorspec, $pipes);
    if (!is_resource($proc)) {
        delete_dir_contents($submission_path);
        rmdir($submission_path);
        error(get_string('failed', 'block_anti_plagiarism'));
    }

    //Wait for the process to finish.
    $output = eval('return '.$config->judger.'_waiting($pipes[1], $pipes[2]);');
    $return = proc_close($proc);
    print_string('done', 'block_anti_plagiarism');
    echo '<br />';

    if (debugging('', DEBUG_DEVELOPER)) 
        print_object($output);

    if ($return) { //Error
        error(get_string('failed', 'block_anti_plagiarism'));
    } else {
        $results = eval('return '.$config->judger.'_parse($output);');
        foreach($results as $result) {
            insert_record('block_anti_plagiarism_pairs', $result);
        }
        print_string('done', 'block_anti_plagiarism');
        echo '<br />';
        print_string('numberofplagiarism', 'block_anti_plagiarism', count($results));
    }

    if (!debugging('', DEBUG_DEVELOPER)) {
        fulldelete($submission_path);
    }

    print_box_end();
    print_continue($CFG->wwwroot.'/blocks/anti_plagiarism/view.php?id='.$id.'&block='.$block.'&action=view');
}

function moss_command($config, $path) {
    global $CFG, $course;

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
        
        $cmd = $CFG->block_antipla_moss_script_path
            .' -l '.$config->type
            .' -m '.$config->sensitivity;
        if (!empty($config->basefile))
            $cmd .= " -b $CFG->dataroot/$course->id/$config->basefile";
        $cmd .= " -d $path";

        return $cmd;
    } else {
        return null;
    }
}

/*
 * Waiting for the finishment, show progress and return output
 */
function moss_waiting($stdout, $stderr) {
    $outputs = array();
    $done = 0;
    print_string('mosscheckfiles', 'block_anti_plagiarism');
    flush();
    while (!feof($stdout)) {
        $line = rtrim(fgets($stdout));
        $outputs[] = $line;
        if ($line == 'OK') {
            print_string('mossuploadfiles', 'block_anti_plagiarism');
            flush();
        } else if ($line == 'Query submitted.  Waiting for the server\'s response.') {
            print_string('mossjudge', 'block_anti_plagiarism');
            flush();
        }
    }

    while (!feof($stderr)) {
        $outputs[] = rtrim(fgets($stderr));
    }

    return $outputs;
}

function moss_parse($output) {
    global $antipla;

    print_string('mossdownloadresults', 'block_anti_plagiarism');
    flush();

    // Skip two empty lines
    array_pop($output);
    array_pop($output);

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
        
        return $CFG->block_antipla_duplication_path.' '.$path.' '.$path.'duplication.out '.$CFG->locale;

    } else {
        return null;
    }
}

/*
 * Waiting for the finishment, show progress and return output
 */
function duplication_waiting($stdout, $stderr) {
    $outputs = array();
    while (!feof($stdout)) {
        $line = rtrim(fgets($stdout));
        $outputs[] = $line;
    }

    while (!feof($stderr)) {
        $outputs[] = rtrim(fgets($stderr));
    }

    return $outputs;
}

function duplication_parse($output) {
    global $antipla;

    $results = array();

    $rank = 1;
    $re = '/^(?<similarity>[0-9\.]+) .*\/(?<assignment1>\d+)\/(?<user1>\d+)\/(?<filename1>[^\/]+)<-->.*\/(?<assignment2>\d+)\/(?<user2>\d+)\/(?<filename2>[^\/]+)/';
    foreach ($output as $line) {
        if (preg_match($re, $line, $matches)) {

            // Ignore plagiarism between the same user
            if ($matches['user1'] === $matches['user2'])
                continue;

            $result = new stdClass();
            $result->user1 = $matches['user1'];
            $result->user2 = $matches['user2'];
            $result->apid = $antipla->id;
            $result->rank = $rank++;
            $result->judger = 'duplication';
            $result->extnames = $antipla->extnames;
            $result->judgedate = time();

            //Make info
            $info = new object();
            $info->filename1 = $matches['filename1'];
            $info->filename2 = $matches['filename2'];
            $info->url1 = fileurl($matches['assignment1'], $matches['user1'], $matches['filename1']);
            $info->url2 = fileurl($matches['assignment2'], $matches['user2'], $matches['filename2']);
            $info->similarity = $matches['similarity'];
            
            $result->info = get_string('duplicationinfo', 'block_anti_plagiarism', $info);

            $results[] = $result;
        }
    }

    return $results;
}

function fileurl($assignment, $user, $filename) {
    global $antipla, $course, $CFG;

    if ($assignment === $antipla->assignment) {
        return get_file_url("$course->id/moddata/assignment/$assignment/$user/$filename", array('forcedownload'=>1));
    } else {
        return "$CFG->wwwroot/blocks/anti_plagiarism/others_file.php?a=$assignment&u=$user&f=$filename";
    }
}

function extract_to_temp($source) {
    global $id, $CFG;

    // Make temp dir
    $temp_dir = $CFG->dataroot.'/temp/anti_plagiarism/'.$id.'/';
    fulldelete($temp_dir);
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
                $command = "export LC_ALL=$CFG->locale ; $CFG->block_antipla_unrar_path e $source$file $temp_dir".dirname($file).'/ >/dev/null';
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
