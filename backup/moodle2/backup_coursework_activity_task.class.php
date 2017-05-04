<?php

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