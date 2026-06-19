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
 *
 * @package   mod_coursework
 * @copyright  2012 ULCC {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework;

use mod_coursework\models\deadline_extension;

/**
 * Checks that parts of the renderer are doing what they should.
 */
final class renderer_test extends \advanced_testcase {
    use \mod_coursework\test_helpers\factory_mixin;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * Test for the expected output.
     *
     * @dataProvider student_intro_provider
     * @covers \mod_coursework_object_renderer::coursework_intro
     * @param array $courseworkparams
     * @param int $extensiondate
     * @param array $shouldmatch Array of regexes expected to appear in the
     * rendered output.
     * @param array $shouldnotmatch Array of regexes expected to not appear in
     * the rendered output.
     */
    public function test_student_intro(array $courseworkparams, int $extensiondate, array $shouldmatch, array $shouldnotmatch): void {
        global $PAGE;

        $coursework = $this->create_a_coursework($courseworkparams);

        $student = $this->create_a_student();

        if ($extensiondate !== 0) {
            $extension = deadline_extension::build([
                'allocatableid' => $student->id,
                'allocatabletype' => 'user',
                'courseworkid' => $coursework->id,
                'extended_deadline' => $extensiondate,
            ]);
            $extension->save();
        }

        $this->setUser((object)['id' => $student->id]);
        $objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');
        $html = $objectrenderer->render(new \mod_coursework_coursework($coursework));

        foreach ($shouldmatch as $re) {
            $this->assertMatchesRegularExpression($re, $html);
        }

        foreach ($shouldnotmatch as $re) {
            $this->assertDoesNotMatchRegularExpression($re, $html);
        }
    }

    /**
     * Provider for test_student_intro.
     *
     * @return array
     */
    public static function student_intro_provider(): array {
        $due = time();
        $formatteddue = userdate($due, get_string('strftimedatetime', 'langconfig'));
        $extension = strtotime('+1 week', $due);
        $formattedextension = userdate($extension, get_string('strftimedatetime', 'langconfig'));
        $individualfeedback = strtotime('+2 week', $due);
        $formattedindividualfeedback = userdate($individualfeedback, get_string('strftimedatetime', 'langconfig'));

        return [
            'duedate' => [
                'courseworkparams' => ['deadline' => $due, 'individualfeedback' => 0],
                'extensiondate' => 0,
                'shouldmatch' => ['/>Due<\/h3>\s+<p>' . $formatteddue . '<\/p>/'],
                'shouldnotmatch' => [
                    '/>Extended deadline<\/h3>/',
                    '/>Auto-release feedback<\/h3>/',
                    '/>\s+Late submissions allowed\s+<\/p>/',
                ],
            ],
            'noduedate' => [
                'courseworkparams' => ['deadline' => 0],
                'extensiondate' => 0,
                'shouldmatch' => [],
                'shouldnotmatch' => ['/>Due<\/h3>/', '/>Extended deadline<\/h3>/'],
            ],
            'extensiondate' => [
                'courseworkparams' => ['deadline' => $due],
                'extensiondate' => $extension,
                'shouldmatch' => [
                    '/>Due<\/h3>\s+<p>' . $formatteddue . '<\/p>/',
                    '/>Extended deadline<\/h3>\s+<p>' . $formattedextension . '<\/p>/',
                ],
                'shouldnotmatch' => [],
            ],
            'individualfeedback' => [
                'courseworkparams' => ['deadline' => $due, 'individualfeedback' => $individualfeedback],
                'extensiondate' => 0,
                'shouldmatch' => ['/>Auto-release feedback<\/h3>\s+<p>' . $formattedindividualfeedback . '<\/p>/'],
                'shouldnotmatch' => [],
            ],
            'allowlatesubmissions' => [
                'courseworkparams' => ['deadline' => $due, 'allowlatesubmissions' => 1],
                'extensiondate' => 0,
                'shouldmatch' => ['/>\s+Late submissions allowed\s+<\/p>/'],
                'shouldnotmatch' => [],
            ],
        ];
    }

    /**
     * Test for the expected output.
     *
     * @dataProvider teacher_intro_provider
     * @covers \mod_coursework_object_renderer::coursework_intro
     * @param array $courseworkparams
     * @param array $shouldmatch Array of regexes expected to appear in the
     * rendered output.
     * @param array $shouldnotmatch Array of regexes expected to not appear in
     * the rendered output.
     */
    public function test_teacher_intro(array $courseworkparams, array $shouldmatch, array $shouldnotmatch): void {
        global $PAGE;

        $coursework = $this->create_a_coursework($courseworkparams);

        // Create teacher and cast to stdClass.
        $teacher = $this->create_a_teacher();

        $this->setUser((object)['id' => $teacher->id]);
        $objectrenderer = $PAGE->get_renderer('mod_coursework', 'object');
        $html = $objectrenderer->render(new \mod_coursework_coursework($coursework));

        foreach ($shouldmatch as $re) {
            $this->assertMatchesRegularExpression($re, $html);
        }

        foreach ($shouldnotmatch as $re) {
            $this->assertDoesNotMatchRegularExpression($re, $html);
        }
    }

    /**
     * Provider for test_teacher_intro.
     *
     * @return array
     */
    public static function teacher_intro_provider(): array {
        $due = time();
        $formatteddue = userdate($due, get_string('strftimedatetime', 'langconfig'));
        $individualfeedback = strtotime('+2 week', $due);
        $formattedindividualfeedback = userdate($individualfeedback, get_string('strftimedatetime', 'langconfig'));

        return [
            'duedate' => [
                'courseworkparams' => ['deadline' => $due, 'individualfeedback' => 0],
                'shouldmatch' => ['/>Due<\/h3>\s+<p>' . $formatteddue . '<\/p>/'],
                'shouldnotmatch' => ['/>Extended deadline<\/h3>/', '/>Auto-release feedback<\/h3>/'],
            ],
            'noduedate' => [
                'courseworkparams' => ['deadline' => 0],
                'shouldmatch' => [],
                'shouldnotmatch' => ['/>Due<\/h3>/', '/>Extended deadline<\/h3>/'],
            ],
            'individualfeedback' => [
                'courseworkparams' => ['deadline' => $due, 'individualfeedback' => $individualfeedback],
                'shouldmatch' => ['/>Auto-release feedback<\/h3>\s+<p>' . $formattedindividualfeedback . '<\/p>/'],
                'shouldnotmatch' => [],
            ],
        ];
    }
}
