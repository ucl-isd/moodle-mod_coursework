@mod @mod_coursework
Feature: Late submissions

  As a teacher
  I want to be able to allow stuents to submit work past the deadline
  So that they can still get some credit even if their grades get capped

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity             | coursework    |
      | course               | C1            |
      | name                 | Coursework    |
      | deadline             | ##yesterday## |
      | allowlatesubmissions | 0             |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  @javascript @_file_upload
  Scenario: not allowed to submit late if the setting does not allow it
    Given the coursework "allowlatesubmissions" setting is "0" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Upload your submission"

    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "allowlatesubmissions" to "1"
    And I press "Save and display"

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Upload your submission"
    When I follow "Upload your submission"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"
