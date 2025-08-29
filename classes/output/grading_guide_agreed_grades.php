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
 * Output class to assemble data needed to display the marking guide form for agreed marks.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\output;

use mod_coursework\models\submission;

/**
 * Output class to assemble data needed to display the marking guide form for agreed marks.
 *
 * @package    mod_coursework
 * @copyright  2025 UCL
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grading_guide_agreed_grades implements \renderable, \templatable {

    /**
     * Moodle form attributes.
     * @var array
     */
    protected array $formattributes;

    /**
     * Moodle form elements.
     * @var array
     */
    protected array $formelements;

    /**
     * Grading form controller.
     * @var \gradingform_controller
     */
    protected \gradingform_controller $gradingcontroller;

    /**
     * Submission we are grading.
     * @var submission
     */
    protected submission $submission;
    /**
     * Constructor.
     */
    public function __construct(
        array $formattributes, array $formelements, \gradingform_controller $gradingcontroller, submission $submission
    ) {
        $this->formattributes = $formattributes;
        $this->formelements = $formelements;
        $this->submission = $submission;
        $this->gradingcontroller = $gradingcontroller;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \core\output\renderer_base $output Renderer base.
     * @return \stdClass
     * @see gradingform_controller for $gradingcontroller class.
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        $formattributes = $this->formattributes;
        $formelements = $this->formelements;
        $gradingcontroller = $this->gradingcontroller;
        $gradingdefinition = $gradingcontroller->get_definition();

        $templatedata = (object)[
            'action' => $formattributes['action'],
            'method' => $formattributes['method'],
            'id' => $formattributes['id'],
            'formclass' => $formattributes['class'],
            'accept-charset' => $formattributes['accept-charset'],
            'button_elements' => [],
        ];

        // Now add the hidden elements to template data.
        $hiddenelements = array_filter($formelements, function($el) {
            return $el->_type == "hidden";
        });

        foreach ($hiddenelements as $hiddenelement) {
            $templatedata->hidden_elements[] = [
                'elementname' => $hiddenelement->_attributes['name'],
                'elementvalue' => $hiddenelement->_attributes['value'],
            ];
        }

        // Now add the general comment field.
        $generalcommentextareaelement = array_values(array_filter($formelements, function($el) {
            return $el->_type == "editor" && $el->_attributes['name'] == 'feedbackcomment';
        }))[0];
        $generalcommentextareaelement->_generateId();
        $templatedata->general_comment_field =
            (object)[
                'id' => $generalcommentextareaelement->_attributes['id'],
                'name' => $generalcommentextareaelement->_attributes['name'],
                'label' => $generalcommentextareaelement->_label,
                'fieldtype' => $generalcommentextareaelement->_type,
                'textareahtml' => $generalcommentextareaelement->toHtml(),
            ];

        // Now add the "Upload a file" filemanager field for bottom of form.
        $filemanagerelement = array_values(array_filter($formelements, function($el) {
            return $el->_type == "filemanager" && $el->_attributes['name'] == 'feedback_manager';
        }))[0];
        $filemanagerelement->_generateId();
        $templatedata->file_upload_field =
            (object)[
                'id' => $filemanagerelement->_attributes['id'],
                'name' => $filemanagerelement->_attributes['name'],
                'label' => $filemanagerelement->_label,
                'html' => $filemanagerelement->toHtml(),
            ];

        // Now add the buttons array (submit etc).
        $buttonsgroup = array_values(array_filter($formelements, function($el) {
            return $el->_type == "group"
                && $el->_name == 'buttonar';
        }))[0]->_elements;
        foreach ($buttonsgroup as $buttonelement) {
            $buttonelement->_generateId();
            switch ($buttonelement->_attributes['name']) {
                case 'cancel':
                    $btnclass = 'btn-secondary';
                    break;
                case 'removefeedbackbutton':
                    $btnclass = 'btn-danger';
                    break;
                default:
                    $btnclass = 'btn-primary';
            }
            $attrs = array_merge($buttonelement->_attributes, ['btn-class' => $btnclass]);
            $templatedata->button_elements[] = $attrs;
        }

        // Add the criteria which are used as the rows in table body.
        $templatedata->criteria_rows = array_map(
            function($c) {
                $c['maxscore'] = number_format($c['maxscore']);
                return (object)$c;
            },
            array_values($gradingcontroller->get_definition()->guide_criteria)
        );

        // Now add the feedbacks - these will be the "Marker 1", "Marker 2" etc. columns in our display.
        $feedbacks = $this->submission->get_feedbacks();
        usort($feedbacks, function ($a, $b) {
            return $a->markernumber <=> $b->markernumber;
        });

        // Add the feedbacks to template - will be used for columns in the table.
        // First filter out agreed feedback from the marker columns (if it's there already).
        $filteredfeedbacks = array_filter(
            $feedbacks,
            function($feedback) {
                return $feedback->stage_identifier != 'final_agreed_1';
            }
        );
        $templatedata->marker_columns = array_map(
            function($feedback) use ($gradingcontroller) {
                $criteriongrades = array_values($gradingcontroller->get_current_instance(
                    $feedback->assessorid, $feedback->id)->get_guide_filling()['criteria']);
                return (object)[
                    'markernumber' => $feedback->markernumber,
                    'feedbackid' => $feedback->id,
                    'assessorid' => $feedback->assessorid,
                    'grade' => $feedback->grade,
                    'stage_identifier' => $feedback->stage_identifier,
                    'finalised' => $feedback->finalised,
                    'criterion_grades' => $criteriongrades,
                ];
            },
            $filteredfeedbacks
        );

        // For the agreed feedback we need an array of dropdown options.
        $frequentcommentoptions = array_values($gradingdefinition->guide_comments);
        $templatedata->hasfrequentcomments = !empty($frequentcommentoptions);
        if ($templatedata->hasfrequentcomments) {
            // Prepare a simple non-associative array of values for later.
            $frequentcommentssimple = array_map(function($item) {
                return $item['description'];
            }, $frequentcommentoptions);

            // Add "Custom" item to the dropdown array.
            $frequentcommentoptions[] = [
                'id' => 'custom',
                'description' => get_string('custom', 'mod_coursework'),
            ];
        }
        $customoptionindex = count($frequentcommentoptions) - 1;

        // Now add agreed feedback as a separate item.
        $existingagreedfeedbacks = array_values(array_filter(
            $feedbacks,
            function($feedback) {
                return $feedback->stage_identifier == 'final_agreed_1';
            }
        ));
        if (count($existingagreedfeedbacks) > 1) {
            debugging(
                "Unexpectedly found more than one agreed feedback for submission " . $this->submission->id(),
                DEBUG_DEVELOPER
            );
        }
        $existingagreedfeedback = !empty($existingagreedfeedbacks) ? $existingagreedfeedbacks[0] : null;
        $existingagreedfeedback = $existingagreedfeedback ? (object)[
            'markernumber' => $existingagreedfeedback->markernumber,
            'feedbackid' => $existingagreedfeedback->id,
            'assessorid' => $existingagreedfeedback->assessorid,
            'grade' => format_float($existingagreedfeedback->grade, 2, false, true),
            'stage_identifier' => $existingagreedfeedback->stage_identifier,
            'finalised' => $existingagreedfeedback->finalised,
            'criterion_grades' => array_values(
                array_map(
                    function($item) use ($existingagreedfeedback, $frequentcommentoptions, $customoptionindex) {
                        $item['score'] = format_float($item['score'], 2, false, true);
                        $item['stage_identifier'] = $existingagreedfeedback->stage_identifier ?? 'final_agreed_1';
                        return $item;
                    },
                    $gradingcontroller->get_current_instance(
                        $existingagreedfeedback->assessorid, $existingagreedfeedback->id)->get_guide_filling()['criteria']
                )
            ),
        ] : null;

        // Now set the dropdown options for each criterion the agreed feedback comments.
        if ($existingagreedfeedback) {
            foreach ($templatedata->criteria_rows as $criteriarow) {
                foreach ($existingagreedfeedback->criterion_grades as $agreedgrade) {
                    if ($criteriarow->id == $agreedgrade['criterionid']) {
                        $criteriarow->dropdownoptions = self::mark_dropdown_option_as_selected(
                            $frequentcommentoptions, $agreedgrade['remark'], $customoptionindex
                        );
                        $criteriarow->customisselected = !in_array($agreedgrade['remark'], $frequentcommentssimple);
                    }
                }
            }
        } else {
            // Dropdown options where is no existing feedback.
            foreach ($templatedata->criteria_rows as $criteriarow) {
                $criteriarow->dropdownoptions = $frequentcommentoptions;
                $criteriarow->customisselected = empty($frequentcommentoptions);
            }
        }
        $templatedata->existing_agreed_feedback = $existingagreedfeedback;

        // The $templatedata->criteria_rows array represents rows in the table.
        // Add the marker grades that we already retrieved to each criterion.
        foreach ($templatedata->criteria_rows as $criterion) {
            // Feedbacks represent the columns.
            foreach ($templatedata->marker_columns as $feedback) {
                foreach ($feedback->criterion_grades as $criteriongrade) {
                    if ($criteriongrade['criterionid'] == $criterion->id) {
                        $criteriongrade['score'] = format_float($criteriongrade['score'], 2, false, true);
                        $criteriongrade['sortorder'] = $criterion->sortorder;
                        $criteriongrade['assessorid'] = $feedback->assessorid;
                        $criteriongrade['stage_identifier'] = $feedback->stage_identifier;
                        $criterion->criterion_grades[] = $criteriongrade;
                    }
                }
            }

            // Also add the existing agreed feedback grade to the row if there is one.
            $criterion->existing_agreed_feedback = null;
            if ($existingagreedfeedback) {
                $existingfeedbackthiscriterion = array_values(array_filter(
                    $existingagreedfeedback->criterion_grades,
                    function($item) use ($criterion) {
                        return $item['criterionid'] == $criterion->id;
                    }
                ));
                if (!empty($existingfeedbackthiscriterion)) {
                    $criterion->existing_agreed_feedback = $existingfeedbackthiscriterion[0];
                }

            }
        }
        return $templatedata;
    }

    /**
     * For agreed feedback dropdown options, mark the appropriate item as "selected".
     * @param array $options
     * @param string $comment
     * @param int $customoptionindex
     * @return array
     */
    private static function mark_dropdown_option_as_selected(array $options, string $comment, int $customoptionindex): array {
        $hasselected = false;
        foreach ($options as $index => $option) {
            $selected = $option['description'] == $comment
                && $option['id'] != 'custom';
            if ($selected) {
                $hasselected = true;
            }
            $options[$index]['selected'] = $selected;
        }
        if (!$hasselected) {
            // If nothing matched, the "custom" option is selected.
            $options[$customoptionindex]['selected'] = true;
        }
        return $options;
    }
}
