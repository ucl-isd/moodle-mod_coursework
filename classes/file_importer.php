<?php

namespace mod_coursework;

use mod_coursework\models\coursework;
use mod_coursework\models\submission;
use mod_coursework\export;

defined('MOODLE_INTERNAL') || die();

class coursework_file_zip_importer    {



    public function extract_zip_file($filename,$contextid)  {

        global $USER;

        //raise_memory_limit(MEMORY_EXTRA);
        //@set_time_limit(ASSIGNFEEDBACK_FILE_MAXFILEUNZIPTIME);

        $packer = get_file_packer('application/zip');

        return $packer->extract_to_storage($filename,
            $contextid,
            'coursework_temp_feedback_file',
            'coursework_feedback_file',
            $USER->id,
            'import');
    }

    public function get_import_files($contextid) {
        global $USER;

        $fs = get_file_storage();
        $files = $fs->get_directory_files($contextid,
            'coursework_temp_feedback_file',
            'coursework_feedback_file',
            $USER->id,
            '/import/');

        $keys = array_keys($files);

        if (count($files) == 1 && $files[$keys[0]]->is_directory()) {
            // An entire folder was zipped, rather than its contents.
            // We need to return the contents of the folder instead, so the import can continue.
            $files = $fs->get_directory_files($contextid,
                'coursework_temp_feedback_file',
                'coursework_feedback_file',
                $USER->id,
                $files[$keys[0]]->get_filepath());
        }

        return $files;
    }

    /**
     * Delete all temp files used when importing a zip
     *
     * @param int $contextid - The context id of this assignment instance
     * @return bool true if all files were deleted
     */
    public function delete_import_files($contextid) {
        global $USER;

        $fs = get_file_storage();

        return $fs->delete_area_files($contextid,
            'coursework_temp_feedback_file',
            'coursework_feedback_file',
            $USER->id);
    }


    /**
     * Process an uploaded zip file
     *
     * @param coursework $coursework
     * @return string - The html response
     */
    public function import_zip_files($coursework,$feedbackstage,$overwritecurrent)   {
        global $CFG, $PAGE, $DB, $USER;

        @set_time_limit(ASSIGNFEEDBACK_FILE_MAXFILEUNZIPTIME);

        $results        =   array();

        $feedbackfilesupdated = 0;
        $feedbackfilesadded = 0;
        $userswithnewfeedback = array();
        $contextid = $coursework->get_context_id();

        $fs = get_file_storage();
        $feedbackfiles = $this->get_import_files($contextid);

        $participants = $coursework->get_allocatables();


        foreach ($feedbackfiles as $file) {

            $filename   =   $file->get_filename();

            if ($allocatableid = $this->is_valid_feedback_file_filename($coursework, $file, $participants) ) {

                $subdbrecord =   $DB->get_record('coursework_submissions',array('courseworkid'=>$coursework->id(),'allocatableid'=>$allocatableid,'allocatabletype'=>$coursework->get_allocatable_type()));

                $submission =   \mod_coursework\models\submission::find($subdbrecord);

                if ($submission->get_state() < \mod_coursework\models\submission::PUBLISHED) {

                    //if only add/edit initial capability then workout stage identifier
                    if ($feedbackstage == 'initialassessor'){

                        $feedback = $DB->get_record('coursework_feedbacks', array('submissionid'=>$submission->id, 'assessorid'=>$USER->id ));

                        if($feedback){
                            $feedbackstage = $feedback->stage_identifier;

                        } else {
                            $results[$filename] = get_string('assessorfeedbacknotfound', 'mod_coursework');
                        }
                    }


                    if ($feedback = $this->feedback_exists($coursework, $submission, $feedbackstage)) {

                        if ($oldfile = $fs->get_file($contextid,
                            'mod_coursework',
                            'feedback',
                            $feedback->id,
                            '/',
                            $filename
                        )
                        ) {
                            if (!empty($overwritecurrent)) {
                                // Update existing feedback file.
                                $oldfile->replace_file_with($file);
                                $feedbackfilesupdated++;

                                $results[$filename] = get_string('feedbackfileupdated', 'mod_coursework');
                            } else {
                                $results[$filename] = get_string('feedbackcurrentfileexist', 'mod_coursework');
                            }
                        } else {
                            // Create a new feedback file.
                            $newfilerecord = new \stdClass();
                            $newfilerecord->contextid = $contextid;
                            $newfilerecord->component = 'mod_coursework';
                            $newfilerecord->filearea = 'feedback';
                            $newfilerecord->filename = $filename;
                            $newfilerecord->filepath = '/';
                            $newfilerecord->itemid = $feedback->id;
                            $fs->create_file_from_storedfile($newfilerecord, $file);
                            $feedbackfilesadded++;
                            $results[$filename] = get_string('feedbackfilecreated', 'mod_coursework');
                        }


                    } else {
                        $results[$filename] = get_string('assessorfeedbacknotfound', 'mod_coursework');
                    }
                } else {
                    $results[$filename] = get_string('feedbacksubmissionpublished', 'mod_coursework');
                }

             } else {
                $results[$filename] = get_string('feedbacknotfound','mod_coursework');
            }
        }

        //clear up the files that have not been moved to the mod_coursework area
        $this->delete_import_files($contextid);

        return $results;


    }


    public function is_valid_feedback_file_filename($coursework,$feedbackfile,$participants) {


        $result     =   false;

        $filename   =   explode('.',$feedbackfile->get_filename());
        $filename   =   $filename[0];

        if ($feedbackfile->is_directory()) {
            return $result;
        }

        // Ignore hidden files.
        if (strpos($filename, '.') === 0) {
            return $result;
        }
        // Ignore hidden files.
        if (strpos($filename, '~') === 0) {
            return $result;
        }

        foreach ($participants as $user)    {
            if ($filename   ==  $coursework->get_username_hash($user->id))   {
                $result = $user->id;
                break;
            }
        }


        return $result;

    }


    public function feedback_exists($coursework,$submission,$stageidentifier)   {

        global $DB, $USER;

        $sql    =   "SELECT     *
                     FROM       {coursework_feedbacks}
                     WHERE      submissionid  = :submissionid
                     AND        stage_identifier = :stage
                     ";

        $params     =   array('submissionid'=>$submission->id,
                              'stage'=>$stageidentifier);

        if (!has_capability('mod/coursework:administergrades',$coursework->get_context())) {
            $sql .= "AND        (assessorid = :assessorid || lasteditedbyuser = :lasteditedbyuser)";
            $params['assessorid'] = $USER->id;
            $params['lasteditedbyuser'] = $USER->id;
        }

        return   $DB->get_record_sql($sql,$params);

    }


}