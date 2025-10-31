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
 * @copyright  2017 University of London Computer Centre {@link https://www.cosector.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_coursework\allocation\table\cell;
use mod_coursework\models\user;
use mod_coursework\stages\base as stage_base;

/**
 * Class data represents the data coming back for one cell.
 *
 * @package mod_coursework\allocation\table\cell
 */
class data {

    /**
     * @var user|int
     */
    protected $assessor = 0;

    /**
     * @var
     */
    private $data;

    /**
     * @var stage_base
     */
    private $stage;

    /**
     * Key in form data for allocation ID.
     */
    const ALLOCATION_ID_KEY = 'allocation_id';

    /**
     * Key in form data for assessor ID.
     */
    const ASSESSOR_ID_KEY = 'assessor_id';

    /**
     * Key in form data for "in set" status.
     */
    const MODERATION_SET_KEY = 'in_set';

    /**
     *  Key in form data for "pinned" status.
     */
    const PINNED_KEY = 'pinned';

    /**
     * @param stage_base $stage
     * @param array $data
     */
    public function __construct($stage, $data  = []) {
        $this->data = $data;
        $this->stage = $stage;
        $this->preprocess_data();
    }

    /**
     * @return mixed
     */
    protected function preprocess_data() {
        if (array_key_exists(self::ASSESSOR_ID_KEY, $this->data) && !empty($this->data[self::ASSESSOR_ID_KEY])) {
            $assessor = user::find($this->data[self::ASSESSOR_ID_KEY]);
            if ($assessor && $this->stage->user_is_assessor($assessor->id())) {
                $this->assessor = $assessor;
            }
        }
    }

    /**
     * @return user
     */
    public function get_assessor() {
        return $this->assessor;
    }

    /**
     * @return bool
     */
    public function has_assessor() {
        return !empty($this->assessor);
    }

    /**
     * @return bool
     */
    public function allocatable_should_be_in_sampling(): bool {
        return array_key_exists(self::MODERATION_SET_KEY, $this->data)
            && $this->data[self::MODERATION_SET_KEY];
    }

    /**
     * @return bool
     */
    public function is_pinned(): bool {
        return array_key_exists(self::PINNED_KEY, $this->data) && $this->data[self::PINNED_KEY];
    }
}
