@mod @mod_coursework @mod_coursework_file_renaming
Feature: Candidate number based file renaming for submission files

  As a teacher
  I want submitted files to be renamed with candidate numbers when using candidate providers

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity     | coursework |
      | course       | C1         |
      | name         | Coursework |
      | maxfiles     | 2          |
      | blindmarking | 0          |
      | renamefiles  | 1          |
      | usecandidate | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                | idnumber |
      | student1 | student   | student1 | student1@example.com | TEST001  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following config values are set as admin:
      | candidate_provider | idnumber | mod_coursework |

  @javascript @_file_upload
  Scenario: Multiple files with candidate numbers get sequential numbering which cannot be disabled
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    And I press "Submit"
    Then I should see "TEST001_1.docx"
    And I should see "TEST001_2.docx"
    And I should not see "Test_document.docx"
    And I should not see "Test_document_two.docx"

    When I am on the "Coursework" "coursework activity" page logged in as admin
    And I click on "Settings" "link"
    And I expand all fieldsets
    And I should see "Candidate number file naming is enabled."
    Then I should see "This setting cannot be changed because students have already submitted files."
