@mod @mod_coursework
Feature: File upload limits

  As a course leader
  I want to be able to limit the number of files that a student can upload
  So that they must submit a specific number

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity               | coursework |
      | course                 | C1         |
      | name                   | Coursework |
      | maxfiles               | 2          |
      | allowearlyfinalisation | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  @javascript @_file_upload
  Scenario: I am prevented from uploading more files than specified
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    Then "Add..." "link" in the ".filemanager" "css_element" should be visible
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I upload "mod/coursework/tests/files_for_uploading/Test_document_two.docx" file to "Upload a file" filemanager
    Then "Add..." "link" in the ".filemanager" "css_element" should not be visible
