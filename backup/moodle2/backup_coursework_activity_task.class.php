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


require_once($CFG->dirroot . '/mod/coursework/backup/moodle2/backup_coursework_stepslib.php');

class backup_coursework_activity_task extends backup_activity_task
{
  static public function encode_content_links($content)
  {
      global $CFG;

      $base = preg_quote($CFG->wwwroot, "/");

      //These have to be picked up by the restore code COURSEWORK... are arbitrary
      $search="/(".$base."\/mod\/coursework\/index.php\?id\=)([0-9]+)/";
      $content= preg_replace($search, '$@COURSEWORKINDEX*$2@$', $content);

      $search="/(".$base."\/mod\/coursework\/view.php\?id\=)([0-9]+)/";
      $content= preg_replace($search, '$@COURSEWORKBYID*$2@$', $content);

      return $content;
  }
  
  protected function define_my_settings()
  {
  }

  protected function define_my_steps()
  {
      $this->add_step(new backup_coursework_activity_structure_step('coursework_structure', 'coursework.xml'));
  }
}
