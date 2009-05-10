<?php

require_once('../../config.php');
    
$ass = required_param('a', PARAM_INT);          // Assignment ID
$user = required_param('u', PARAM_INT);
$filename = required_param('f', PARAM_FILE);

if (! $assignment = get_record("assignment", "id", $ass)) {
    error("assignment ID was incorrect");
}

if (! $cm = get_coursemodule_from_id('assignment', $ass)) {
    error("assignment ID was incorrect");
}

if (! $course = get_record("course", "id", $assignment->course)) {
    error("Course is misconfigured");
}

//TODO: more info

$a = new object();
$a->course = $course->name;
$a->course_url = $CFG->wwwroot.'/course/view.php?id='.$course->id;
$a->assignment = $cm->name;
$a->assignment_url = $CFG->wwwroot.'/mod/assignment/view.php?id='.$cm->id;

//TODO: show info
?>
