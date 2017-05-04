<?php

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/mod/coursework/tests/behat/pages/page_base.php');

use Behat\Mink\Exception\ExpectationException;
use mod_coursework\models\feedback;

/**
 * Holds the functions that know about the HTML structure of the student page.
 *
 *
 */
class mod_coursework_behat_show_feedback_page extends mod_coursework_behat_page_base {

    /**
     * @var feedback
     */
    protected $feedback;

    /**
     * @param string $grade
     */
    public function should_have_grade($grade) {
        assertContains($grade, $this->get_feedback_table()->getText());
    }

    /**
     * @param string $comment
     */
    public function should_have_comment($comment) {
        assertContains($comment, $this->get_feedback_table()->getText());
    }

    /**
     * @param feedback $feedback
     */
    public function set_feedback($feedback) {
        $this->feedback = $feedback;
    }

    /**
     * @return \Behat\Mink\Element\NodeElement|null
     * @throws coding_exception
     */
    private function get_feedback_table() {
        return $this->getPage()->find('css', '#feedback_'.$this->get_feedback()->id);
    }

    /**
     * @return feedback
     * @throws coding_exception
     */
    private function get_feedback() {
        if (empty($this->feedback)) {
            throw new coding_exception('Must set the feedback on the show feedback page before using it!');
        }
        return $this->feedback;
    }
}