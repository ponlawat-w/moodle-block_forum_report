<?php

namespace block_forum_report\forms;

use html_writer;

require_once(__DIR__ . '/../../../../lib/formslib.php');

defined('MOODLE_INTERNAL') or die();

class deleteconfirm_form extends \moodleform {
  private $id;

  public function __construct($id) {
    $this->id = $id;
    parent::__construct();
  }

  public function definition() {
    $mform = $this->_form;

    $mform->addElement('html', html_writer::tag('h3', get_string('deleteconfirmation_title', 'block_forum_report')));
    $mform->addElement('html', get_string('deleteconfirmation_description', 'block_forum_report'));

    $mform->addElement('hidden', 'id', $this->id);
    $mform->addElement('hidden', 'action', 'delete');

    $this->add_action_buttons(true, get_string('yes'));
  }
}
