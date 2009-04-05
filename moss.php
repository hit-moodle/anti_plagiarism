<?php
    require_once("../../config.php");
    require_once("../../mod/assignment/lib.php");
    
    $id = required_param('id', PARAM_INT);          // Course module ID
    $proglang = optional_param('proglang', 'c', PARAM_ALPHA);   //The programming language of the files
    $submit = optional_param('submit', 0, PARAM_INT);       //submit or directly display 

    if (! $cm = get_coursemodule_from_id('assignment', $id)) {
        error("Course Module ID was incorrect");
    }

    if (! $assignment = get_record("assignment", "id", $cm->instance)) {
        error("assignment ID was incorrect");
    }

    if (! $course = get_record("course", "id", $assignment->course)) {
        error("Course is misconfigured");
    }

    require_login($course->id, false, $cm);

    if (!isteacher($course->id)) {
        error("Only teachers can look at this page");
    }
    
    $mossdata = $CFG->dataroot.'/moss/';
   
    $strassignments = get_string('modulenameplural', 'assignment');

    $navigation = "<a target=\"{$CFG->framename}\" href=\"$CFG->wwwroot/course/view.php?id={$course->id}\">{$course->shortname}</a> -> <a target=\"{$CFG->framename}\" href=\"$CFG->wwwroot/mod/assignment/index.php?id={$course->id}\">$strassignments</a> ->" . format_string($assignment->name,true);

    $pagetitle = strip_tags($course->shortname.': '.$strassignment.': '.format_string($assignment->name,true).': '.get_string('resulttitle', 'block_moss'));

    print_header($pagetitle, $course->fullname, "$navigation $strassignment", 
                             "", "", true, '', navmenu($course, $cm));
 
    if ($submit) {  // Submit to moss server
        echo '<pre>';
        $command = $CFG->dirroot.'/blocks/moss/moss'.' -l '.$proglang.' -d -c \''.$assignment->name."\' {$CFG->dataroot}/{$course->id}/moddata/assignment/{$assignment->id}/*/*.c";
        $url = system($command, $retval);
        echo '</pre>';

        if (!file_exists($mossdata)) {
            mkdir($mossdata);
        }
        
        if (!strpos($url, 'http://') && !copy($url, $mossdata.$id.'.html')) {
            error(get_string('failed', 'block_moss'));         
        }
    }
    
    // Call back function for preg_replace_callback()
    // Replace pathname with fullname and info, grade button in result page
    function replace_with_fullname($matches) {
        global $course;
        global $cm;
        global $CFG;

        $user = get_record("user", "id", $matches[2]);
        if (! $user) {
            $name = get_string('nouser', 'block_moss');
        } else {
            $name = '('.$user->idnumber.')'.fullname($user);
        }

        $info_button = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$user->id.'&amp;course='.$course->id.'">'.'<img src="'.$CFG->pixpath.'/i/search.gif" border="0" alt="'.get_string('more').'" /></a>';
        //$info_button = print_user_picture($user->id, $course->id, true, true, true);

        $grade_button = link_to_popup_window('/mod/assignment/submissions.php?id='.$cm->id.'&amp;userid='.$user->id.'&amp;mode=single&amp;offset=1', 
            'grade'.$user->id, 
            '<img src="'.$CFG->pixpath.'/i/grades.gif" border="0" alt="'.get_string('grade').'" />', 
            500, 700,
            get_string('grade'),
            'none',
            true);
        
        return '&nbsp;' . $name . $matches[3] . $info_button . $grade_button . '&nbsp'; 
    }
    
    if (!file_exists($mossdata.$id.'.html')) {
        $submittext = get_string('submit', 'block_moss');
        print_simple_box(get_string('nosubmitted', 'block_moss'), 'center');
    } else {
        $submittext = get_string('resubmit', 'block_moss');
        $fp = fopen($mossdata.$id.'.html', "r");
        while (!feof($fp)) {
            $line = fgets($fp);

            //Replace path with fullname
            $line = preg_replace_callback('|(/[^:\.\*\"]+/)(\d+)/ (\([0-9]+%\)\</A\>$)|', 
                                          'replace_with_fullname', $line);

            //Get the language
            if (preg_match('/Options -l ([a-z0-9]*)/i', $line, $matches)) {
                $selected = $matches[1];
            }

            //Change the time to local time
            //if (preg_match('/[:alpha:]{3} [:alpha:]{3} [0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2} [A-Z]{3} [0-9]{4}$/i', $line)) {
            if (preg_match('/[0-9]{2}:[0-9]{2}:[0-9]{2} [A-Z]{3} [0-9]{4}$/', $line)) {
                $line = userdate(filectime($mossdata.$id.'.html'), get_string('strftimerecentfull'));
            }

            echo $line;
        }
        fclose($fp);
    }
    
    $proglangs = array('ada' => 'Ada', 'ascii' => 'ASCII', 'a8086' => 'a8086 assembly', 'c' => 'C', 'cc' => 'C++', 'csharp' => 'C#', 'fortran' => 'FORTRAN', 'haskell' => 'Haskell', 'java' => 'Java', 'javascript' => 'Javascript', 'lisp' => 'Lisp', 'matlab' => 'Matlab', 'mips' => 'MIPS assembly', 'ml' => 'ML', 'modula2' => 'Modula2', 'pascal' => 'Pascal', 'perl' => 'Perl', 'plsql' => 'PLSQL', 'prolog' => 'Prolog', 'python' => 'Python', 'scheme' => 'Scheme', 'spice' => 'Spice', 'vhdl' => 'VHDL');

    uksort($proglangs, strcmp);

    if (isteacheredit($course->id)) {
	    /// Mini form for setting submit parament
	    print_spacer(10);
	    echo '<form name="options" action="moss.php?id='.$id.'" method="post">';
	    print_string('chooselang','block_moss');
	    echo '<input type="hidden" name="submit" value="1" />';
	    echo '<select name="proglang">';
        foreach ($proglangs as $code => $name) {
            if (strcmp($code, $selected)) {
                echo '<option value="'.$code.'">'.$name.'</option>';
            } else {
                echo '<option selected value="'.$code.'">'.$name.'</option>';
            }
        }
	    echo '<input type="submit" value="'.$submittext.'" />';
	    echo '</form>';
	    ///End of mini form
    }
    print_footer($course);
?>
