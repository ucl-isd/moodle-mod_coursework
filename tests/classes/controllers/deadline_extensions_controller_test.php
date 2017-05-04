<?php

/**
 * Class deadline_extensions_controller_test is responsible for testing the deadline_extensions controller
 * class.
 *
 */
class deadline_extensions_controller_test extends basic_testcase {

    public function test_model_name() {
        $controller = new \mod_coursework\controllers\deadline_extensions_controller(array());
        $this->assertEquals('deadline_extension', $controller->model_name());
    }


}