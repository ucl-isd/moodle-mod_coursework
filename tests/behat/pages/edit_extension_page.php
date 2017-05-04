<?php

/**
 * Class mod_coursework_behat_new_extension_page is responsible for representing the new
 * extension page and its operation for the steps in Behat tests. Any reference to the page
 * CSS should be in this class only.
 *
 */
class mod_coursework_behat_edit_extension_page extends mod_coursework_behat_page_base {

    /**
     * @param $time
     * @throws \Behat\Mink\Exception\ElementException
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    public function edit_active_extension($time) {
        global $CFG;

        // Select the date from the dropdown
        $this->fill_in_date_field('extended_deadline', $time);

        // Choose an extension reason from the dropdown if it's there
        if (!empty($CFG->coursework_extension_reasons_list)) {
            $this->getPage()->fillField('pre_defined_reason', 1);
        }

        $fieldnode = $this->getPage()->findField('Extra information');

        $field = behat_field_manager::get_form_field($fieldnode, $this->getSession());
        // Delegates to the field class.
        $field->set_value('New info here');

        // Click the submit button
        $this->getPage()->find('css', '#id_submitbutton')->click();

    }

    /**
     * @param string $reason
     */
    public function should_show_extension_reason_for_allocatable($reason) {
        $field = $this->getPage()->findField('pre_defined_reason');
        assertEquals($reason, $field->getValue());
    }

    /**
     * @param string $string
     */
    public function should_show_extra_information_for_allocatable($string) {
        $field = $this->getPage()->findField('extra_information[text]');
        assertContains($string, $field->getValue());
    }
}