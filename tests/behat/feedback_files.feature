@mod @mod_coursework @_file_upload @mod_coursework_feedback_files
Feature: Adding feedback files

  As a teacher
  I want to be able to add grades, comments and feedback files
  So that I can provide users with rich, detailed feedback

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 1          |
      | filetypes       | pdf        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | manager1 | manager   | manager1 | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  @javascript @_file_upload
  Scenario: Grades, files and comments can be saved and edited
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Add mark" "link" in the "student1" "table_row"
    And I set the field "Mark" to "52"
    And I set the field "Comment" to "Some new comment 3"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I click on "Save as draft" "button"
    And I should see "Changes saved"
    And I click on "52" "link" in the "student1" "table_row"
    Then the field "Mark" matches value "52"
    And the field "Comment" matches value "Some new comment 3"
    And I should see "1" elements in "Upload a file" filemanager

    When I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
    And I set the field "Mark" to "62"
    And I set the field "Comment" to "Edited Some new comment 3"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    And I should see "62" in the "student1" "table_row"

    When I follow "Release the marks"
    And I press "Confirm"
    And I log out
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Test_image.png" in the ".coursework-feedback" "css_element"
    And I should see "Test_document.docx" in the ".coursework-feedback" "css_element"
