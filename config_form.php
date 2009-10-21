<?php  //$Id: edit_form.php,v 1.37.2.17 2009/02/13 10:01:15 stronk7 Exp $

require_once($CFG->libdir.'/formslib.php');

class anti_plagiarism_config_form extends moodleform {

    function definition() {
        global $USER, $CFG;
        global $id, $block, $action;

        $mform =& $this->_form;

        $choices = array();
        if (isset($CFG->block_antipla_moss_script_path) and !empty($CFG->block_antipla_moss_script_path))
            $choices['moss'] = get_string('moss', 'block_anti_plagiarism');
        if (isset($CFG->block_antipla_duplication_path) and !empty($CFG->block_antipla_duplication_path))
            $choices['duplication'] = get_string('duplication', 'block_anti_plagiarism');
        if (empty($choices)) {
            notice(get_string('nojudgerconfig', 'block_anti_plagiarism'));
        }
        $mform->addElement('select', 'judger', get_string('describejudger', 'block_anti_plagiarism'), $choices);
        $mform->setDefault('judger', 'moss');
        $mform->setType('judger', PARAM_NOTAGS);

        $mform->addElement('header', 'mossoptions', get_string('mossoptions', 'block_anti_plagiarism'));

        $attributes='size="20"';
        $mform->addElement('text', 'extnames', get_string('describeextnames', 'block_anti_plagiarism'), $attributes);
        $mform->setType('extnames', PARAM_NOTAGS);
        $mform->setDefault('extnames', '.c');
        $mform->disabledIf('extnames', 'judger', 'eq', 'duplication');

        $mossarray = array();
        $choices = array('ada' => 'Ada', 'ascii' => 'ASCII', 'a8086' => 'a8086 assembly', 'c' => 'C', 'cc' => 'C++', 'csharp' => 'C#', 'fortran' => 'FORTRAN', 'haskell' => 'Haskell', 'java' => 'Java', 'javascript' => 'Javascript', 'lisp' => 'Lisp', 'matlab' => 'Matlab', 'mips' => 'MIPS assembly', 'ml' => 'ML', 'modula2' => 'Modula2', 'pascal' => 'Pascal', 'perl' => 'Perl', 'plsql' => 'PLSQL', 'prolog' => 'Prolog', 'python' => 'Python', 'scheme' => 'Scheme', 'spice' => 'Spice', 'vhdl' => 'VHDL', 'vb' => 'Visual Basic');
        $mossarray[] = $mform->addElement('select', 'type', get_string('describetype', 'block_anti_plagiarism'), $choices);
        $mform->setDefault('type', 'c');
        $mform->setType('type', PARAM_NOTAGS);
        $mform->disabledIf('type', 'judger', 'eq', 'duplication');
        
        $attributes='size="10"';
        $mform->addElement('text', 'sensitivity', get_string('describesensitivity', 'block_anti_plagiarism'), $attributes);
        $mform->setType('sensitivity', PARAM_INT);
        $mform->setDefault('sensitivity', '10');
        $mform->disabledIf('sensitivity', 'judger', 'eq', 'duplication');
        $mform->setHelpButton('sensitivity', array('sensitivity', get_string('describesensitivity', 'block_anti_plagiarism'), 'block_anti_plagiarism'));
        
        $mform->addElement('choosecoursefile', 'basefile', get_string('basefile', 'block_anti_plagiarism'), null, array('maxlength' => 255, 'size' => 48));
        $mform->addGroupRule('basefile', array('value' => array(array(get_string('maximumchars', '', 255), 'maxlength', 255, 'client'))));
        $mform->disabledIf('basefile', 'judger', 'eq', 'duplication');
        $mform->setHelpButton('basefile', array('basefile', get_string('describebasefile', 'block_anti_plagiarism'), 'block_anti_plagiarism'));

        $mform->closeHeaderBefore('cleanall');

        $mform->addElement('checkbox', 'cleanall', get_string('describecleanall', 'block_anti_plagiarism'));
        $mform->setDefault('cleanall', '0');

//--------------------------------------------------------------------------------
        $this->add_action_buttons(false, get_string('judge', 'block_anti_plagiarism'));
//--------------------------------------------------------------------------------
        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'block', $block);
        $mform->setType('block', PARAM_INT);
        $mform->addElement('hidden', 'action', $action);
        $mform->setType('action', PARAM_ACTION);
    }


/// perform some extra moodle validation
    function validation($data, $files) {
        global $CFG;

        $errors = parent::validation($data, $files);

        if ($data['judger'] === 'moss' && (!array_key_exists('extnames', $data) || $data['extnames'] === ''))
            $errors['extnames'] = get_string('extnameserror', 'block_anti_plagiarism');

        return $errors;
    }
}
?>
