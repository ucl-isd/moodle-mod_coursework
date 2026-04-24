@mod @mod_coursework @mod_coursework_file_renaming_candidate
Feature: Username hash based file renaming for submission files

  As a teacher
  I want submitted files to be renamed with a hash of the username when blind marking is enabled and candidate numbers
  are disabled
  So that I can ensure blind marking with standardized candidate number anonymization

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity     | coursework |
      | course       | C1         |
      | name         | Coursework |
      | maxfiles     | 2          |
      | blindmarking | 1          |
      | renamefiles  | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  @javascript @_file_upload
  Scenario: Files use username hash when candidate number is disabled
    Given blind marking is enabled
    And the coursework "usecandidate" setting is "0" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    And I press "Submit"
    Then I should see "_1.docx"
    And I should see "_2.docx"
    And I should not see "Test_document.docx"
    And I should not see "Test_document_two.docx"
