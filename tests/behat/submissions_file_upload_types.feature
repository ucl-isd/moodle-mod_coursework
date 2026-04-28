@mod @mod_coursework @mod_coursework_submissions_file_upload_types
Feature: Restricting the types of files that students can upload

  As a teacher
  I want to be able to restrict what file types the students can upload
  So that tutors marking the work have a consistent experence and don't waste time

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity  | coursework |
      | course    | C1         |
      | name      | Coursework |
      | filetypes |            |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  @javascript @_file_upload
  Scenario: I can upload anything when the settings are empty but only allowed file types when the settings are restrictive
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_image.png" file to "Upload a file" filemanager
    Then I should see "1" elements in "Upload a file" filemanager

    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "filetypes" to "docx"
    And I press "Save and display"

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    Then I should see "1" elements in "Upload a file" filemanager
