<?PHP //$Id: block_anti_plagiarism.php,v 1.6 2004/10/03 09:50:39 stronk7 Exp $

class block_anti_plagiarism extends block_list {

    function init () {
        global $course;

        $this->title = get_string('blockname','block_anti_plagiarism');

        $this->course = $course;

        $this->version = 2006091200;
    }

    function get_content() {
        global $CFG, $USER;

        // This prevents your block from recalculating its content more than once before the page
        // is displayed to the user. Unless you KNOW that there is a VERY SPECIFIC reason not to do
        // that, accept the speed improvement and DO NOT TOUCH the next three lines.

        if($this->content !== NULL) {
            return $this->content;
        }

        if (empty($this->instance->pinned)) {
            $context = get_context_instance(CONTEXT_BLOCK, $this->instance->id);
        } else {
            $context = get_context_instance(CONTEXT_SYSTEM); // pinned blocks do not have own context
        }
        
        $viewall = has_capability('block/anti_plagiarism:viewall', $context);
        $viewself = has_capability('block/anti_plagiarism:viewself', $context);

        if (!$viewall && !$viewself)    //Should not see the block
            return null;

        $this->content = &New stdClass;

        $this->content->items = array(); // this is the text for each item
        $this->content->icons = array(); // this is the icon for each item

        if ($viewall) {
            $assignments = get_all_instances_in_course("assignment", $this->course);
            if (!$assignments)
                $assignments = array();
        } else { //viewself
            $select = "(user1=$USER->id OR user2=$USER->id) AND confirmed=1";
            $rows = get_records_select('block_anti_plagiarism_pairs', $select);
            $assignments = array();
            if ($rows) {
                $apids = array();
                foreach ($rows as $row) {
                    if (in_array($row->apid, $apids))
                        continue;
                    $apids[] = $row->apid;
                    $ap = get_record('block_anti_plagiarism', 'id', $row->apid);
                    $assignments[] = get_record('assignment', 'id', $ap->assignment);
                }
            }
        }

        foreach ($assignments as $assignment) {

            //if ($assignment->visible) {
            if (instance_is_visible('assignment', $assignment)) {
                //Show normal if the mod is visible
                $this->content->icons[] = '<img src="'.$CFG->modpixpath.'/assignment/icon.gif"></img>';
                $this->content->items[] = '<a href="'.$CFG->wwwroot.'/blocks/anti_plagiarism/view.php?id='.$assignment->id.'&block='.$this->instance->id.'">'.format_string($assignment->name,true).'</a>';
            } else {
                //Show dimmed if the mod is hidden
                $this->content->icons[] = '<img src="'.$CFG->modpixpath.'/assignment/icon.gif"></img>';
                $this->content->items[] = '<a class ="dimmed" href="'.$CFG->wwwroot.'/blocks/anti_plagiarism/view.php?id='.$assignment->id.'&block='.$this->instance->id.'">'.format_string($assignment->name,true).'</a>';
            }   
        }

        if (!empty($this->content->items)) {
            $this->content->footer = $viewall ? '' : get_string('hasplagiarism', 'block_anti_plagiarism', count($this->content->items));
        } else {
            $this->content->footer = $viewall ? get_string('noassignments', 'assignment') : get_string('noplagiarism', 'block_anti_plagiarism');
        }

        return $this->content;
    }

    function applicable_formats() {
        return array('course' => true);
    }

    function has_config() {
        return true;
    }

    function handle_config($config) {
        foreach ($config as $name => $value) {
            set_config($name, $value);
        }
        return true;
    }
}

?>
