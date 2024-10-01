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
 * @copyright  2011 University of London Computer Centre {@link http://ulcc.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Capability definitions for the coursework module
 *
 * The capabilities are loaded into the database table when the module is
 * installed or updated. Whenever the capability definitions are updated,
 * the module version number should be bumped up.
 *
 * The system has four possible values for a capability:
 * CAP_ALLOW, CAP_PREVENT, CAP_PROHIBIT, and inherit (not set).
 *
 * It is important that capability names are unique. The naming convention
 * for capabilities that are specific to modules and blocks is as follows:
 *   [mod/block]/<plugin_name>:<capabilityname>
 *
 * component_name should be the same as the directory name of the mod or block.
 *
 * Core moodle capabilities are defined thus:
 *    moodle/<capabilityclass>:<capabilityname>
 *
 * Examples: mod/forum:viewpost
 *           block/recent_activity:view
 *           moodle/site:deleteuser
 *
 * The variable name for the capability definitions array is $capabilities
 *
 * @package   mod_coursework
 * @copyright 2010 Your Name
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'mod/coursework:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/course:manageactivities',
    ],

    'mod/coursework:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'guest' => CAP_ALLOW,
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:submit' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'student' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:submitonbehalfof' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:addinitialgrade' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:editinitialgrade' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:addagreedgrade' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:editagreedgrade' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:publish' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:viewanonymous' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:revertfinalised' => [
        'riskbitmask' => RISK_MANAGETRUST,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:allocate' => [
        'riskbitmask' => RISK_MANAGETRUST,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:viewallgradesatalltimes' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:administergrades' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:grantextensions' => [
        'riskbitmask' => RISK_MANAGETRUST,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:canexportfinalgrades' => [
        'riskbitmask' => RISK_MANAGETRUST,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:viewextensions' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:grade' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],
    'mod/coursework:addgeneralfeedback' => [
        'riskbitmask' => RISK_MANAGETRUST | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:viewallstudents' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:addallocatedagreedgrade' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:editallocatedagreedgrade' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:editpersonaldeadline' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:moderate' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:receivesubmissionnotifications' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:addplagiarismflag' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],

    'mod/coursework:updateplagiarismflag' => [
        'riskbitmask' => RISK_PERSONAL | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'legacy' => [
            'manager' => CAP_ALLOW,
        ],
    ],

];
