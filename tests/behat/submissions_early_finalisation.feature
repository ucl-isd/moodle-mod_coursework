@mod @mod_coursework @mod_coursework_submissions_early_finalisation
Feature: Early finalisation of student submissions

  As a teacher
  I want to allow students to finalise their work early
  So that there is a way to know when something is ready to mark before the deadline is due and I
  can plan my grading work more effectively

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    |allowearlyfinalisation|1|
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  @javascript @_file_upload
  Scenario: I upload a file and finalise it immediately
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should see "Test_document.docx"
    But "Finalise your submission" "button" should not exist
    And "Edit your submission" "button" should not exist

  @javascript @_file_upload
  Scenario: I upload a file and do not finalise it immediately
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"
    And I am on the "Coursework" "coursework activity" page
    Then I should see "Test_document.docx"
    And "Edit your submission" "button" should not exist
    And "Finalise your submission" "button" should exist

  @javascript @_file_upload
  Scenario: I upload a file and save it
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"
    And I am on the "Coursework" "coursework activity" page
    And I should see "Not submitted" in the ".behat-submission-information" "css_element"
    And I should see "Finalise your submission"
    And I click on "Finalise your submission" "button"
    And I click on "Yes" "button" in the "Confirmation" "dialogue"
    Then I should see "Submitted" in the ".behat-submission-information" "css_element"
    But "Finalise your submission" "button" should not exist

  @javascript @_file_upload
  Scenario: allowed to submit late if the setting allows it
    Given the coursework "allowlatesubmissions" setting is "1" in the database
    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I follow "Upload your submission"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"
    Then I should see "Test_document.docx"
    And "Edit your submission" "button" should not exist
    And "Finalise your submission" "button" should exist

  @javascript @_file_upload
  Scenario: I should not see the early finalisation button on the student submission form
    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "allowearlyfinalisation" to "0"
    And I press "Save and display"

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I click on "Upload your submission" "link"
    Then I should not see "Submit and finalise"

    When I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"
    And I am on the "Coursework" "coursework activity" page
    Then "Finalise your submission" "button" should not exist
