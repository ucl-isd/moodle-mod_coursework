<?php

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
     * @param stage_base $stage
     * @param array $data
     */
    public function __construct($stage, $data = array()) {
        $this->data = $data;
        $this->stage = $stage;
        $this->preprocess_data();
    }

    /**
     * @return mixed
     */
    protected function preprocess_data() {

        $key = $this->assessor_id_key_name();
        if (array_key_exists($key, $this->data) && !empty($this->data[$key])) {
            $assessor = user::find($this->data[$key]);
            
            if ($assessor && $this->stage->user_is_assessor($assessor)) {
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
    public function allocatable_should_be_in_sampling() {
        $key = $this->moderation_set_key();
        return array_key_exists($key, $this->data) && !!$this->data[$key];
    }

    /**
     * @return bool
     */
    public function is_pinned() {
        $key = $this->pinned_key();
        return array_key_exists($key, $this->data) && !!$this->data[$key];
    }

    /**
     * @return string
     */
    private function assessor_id_key_name() {
        return 'assessor_id';
    }

    /**
     * @return string
     */
    private function moderation_set_key() {
        return 'in_set';
    }

    /**
     * @return string
     */
    private function pinned_key() {
        return 'pinned';
    }
}