<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package    mod_coursework
 * @copyright  2017 University of London Computer Centre {@link ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use mod_coursework\models\user;

/**
 * This forms a common base for the page context objects that are used to hold the functions
 * that the behat steps call on. The pattern is to have one class to represent one page in the
 * application. The functions can be reused in different steps and any common stuff that all pages
 * use lives in this base class.
 */
class mod_coursework_behat_page_base {

    /**
     * @var behat_mod_coursework
     */
    protected $context;

    /**
     * Constructor stores the session for use later when the page API functions are needed.
     *
     * @param behat_mod_coursework $context
     */
    public function __construct($context) {
        $this->context = $context;
    }

    /**
     * @return \Behat\Mink\Session
     */
    protected function getSession() {
        return $this->context->getSession();
    }

    /**
     * @return behat_mod_coursework
     */
    protected function getContext() {
        return $this->context;
    }

    /**
     * @return \Behat\Mink\Element\DocumentElement
     */
    protected function getPage() {
        return $this->getSession()->getPage();
    }

    /**
     * Checks whether the page has the text anywhere on it.
     *
     * Pulled from behat_general
     *
     * @param string $text
     * @return bool
     * @throws Behat\Mink\Exception\ExpectationException
     */
    public function should_have_text($text) {

        $pagetext = $this->getPage()->getText();
        if (substr_count($pagetext, $text) == 0) {
            throw new ExpectationException('Page did not have text "'.$text.'"', $this->getSession());
        }

    }

    /**
     * @param string $css
     * @param string $text
     * @param string $error
     */
    protected function should_have_css($css, $text = '', $error = '') {
        $elements = $this->getPage()->findAll('css', $css);
        $message = "CSS containing '$text' not found " . $error;
        if (empty($elements)) {
            throw new ExpectationException($message, $this->getSession());
        }
        if ($text) {
            $actualtext = reset($elements)->getText();
            if (!str_contains($actualtext, $text)) {
                throw new ExpectationException($message, $this->getSession());
            }
        }
    }

    /**
     * @param string $css
     * @param string $text
     */
    protected function should_not_have_css($css, $text = '') {
        $elements = $this->getPage()->findAll('css', $css);
        if ($text) {
            foreach ($elements as $element) {
                $actualtext = $element->getText();
                if (str_contains($actualtext, $text)) {
                    throw new ExpectationException("Should not have CSS $css", $this->getSession());
                }
            }
        } else {
            if (!empty($elements)) {
                throw new ExpectationException("Should not have CSS $css", $this->getSession());
            }
        }
    }

    /**
     * @param $thing_css
     * @param string $text
     * @throws ExpectationException
     * @throws \Behat\Mink\Exception\ElementException
     */
    protected function click_that_thing($thingcss, $text = '') {
        $ok = false;
        /**
         * @var $things NodeElement[]
         */
        $things = $this->getPage()->findAll('css', $thingcss);
        foreach ($things as $thing) {
            if (empty($text) || $thing->getText() == $text || $thing->getValue() == $text) {
                $thing->click();
                $ok = true;
                break;
            }
        }

        if (empty($ok)) {
            $message = 'Tried to click a thing that is not there: ' . $thingcss. ' '. $text;
            throw new ExpectationException($message, $this->getSession());
        }
    }

    /**
     * @param string $thing_css
     * @param string $text
     * @return bool
     */
    protected function has_that_thing($thingcss, $text = '') {
        $foundit = false;
        /**
         * @var $things NodeElement[]
         */
        $things = $this->getPage()->findAll('css', $thingcss);
        foreach ($things as $thing) {
            if (empty($text) || $thing->getText() == $text || $thing->getValue() == $text) {
                $foundit = true;
                break;
            }
        }

        return $foundit;
    }

    /**
     * @param $allocatable
     * @return string
     */
    protected function allocatable_identifier_hash($allocatable) {
        return $this->getContext()->coursework->get_allocatable_identifier_hash($allocatable);
    }

    /**
     * @param string $field_name
     * @param int $timestamp
     * @throws \Behat\Mink\Exception\ElementNotFoundException
     */
    protected function fill_in_date_field($fieldname, $timestamp) {
        // Select the date from the dropdown
        $minutedropdownselector = "id{$fieldname}minute";
        $hourdropdownselector = "id{$fieldname}hour";
        $daydropdownselector = "id{$fieldname}day";
        $monthdropdownselector = "id{$fieldname}month";
        $yeardropdownselector = "id{$fieldname}year";

        $minute = date('i', $timestamp);
        $hour = date('H', $timestamp);
        $day = date('j', $timestamp);
        $month = date('n', $timestamp);
        $year = date('Y', $timestamp);

        $this->getPage()->fillField($minutedropdownselector, $minute);
        $this->getPage()->fillField($hourdropdownselector, $hour);
        $this->getPage()->fillField($daydropdownselector, $day);
        $this->getPage()->fillField($monthdropdownselector, $month);
        $this->getPage()->fillField($yeardropdownselector, $year);
    }
}
