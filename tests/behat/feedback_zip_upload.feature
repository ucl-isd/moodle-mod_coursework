@mod @mod_coursework @mod_coursework_feedback_zip_upload @javascript @_file_upload

Feature: Upload feedback files

  Scenario: Upload empty zip should occur without error
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And there is a student
    And there is a manager
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click on "Upload" "button"
    And I click on "Feedback files in a zip" "link"
    And I upload "mod/coursework/tests/files_for_uploading/test_invalid_feedback_upload.zip" file to "Feedback zip file" filemanager
    And I click on "Upload feedback zip" "button"
    # File is successfully uploaded and processed, albeit unsuccessfully for reason below.
    Then I should see "The zip file uploaded has been processed. The results are shown below"
    And I should see "File empty.txt : A submission with feedback that matches the filename of this file was not found"
