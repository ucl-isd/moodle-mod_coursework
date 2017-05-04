<?php

namespace mod_coursework\exceptions;

use mod_coursework\models\coursework;
use mod_coursework\router;

/**
 * Class access_denied is used when we have a user who is sometimes allowed to do something (has_capability()
 * returns true for this context), but is prevented by the business rules of the plugin. e.g. cannot access the
 * new submission page because they have already submitted. This should only be used in controller actions that
 * the user would not normally see a link to.
 *
 * @package mod_coursework
 */
class access_denied extends \moodle_exception {

    /**
     * @param coursework $coursework
     * @param string $message
     * @throws \coding_exception
     */
    public function __construct($coursework, $message = null) {

        $link = router::instance()->get_path('coursework', array('coursework' => $coursework));

        parent::__construct('access_denied', 'mod_coursework', $link, null, $message);
    }

}