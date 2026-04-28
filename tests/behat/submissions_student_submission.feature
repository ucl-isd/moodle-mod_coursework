@mod @mod_coursework
Feature: Students can submit files

  In order to submit work to my tutor for grading
  As a student who has completed some work
  I want to be able to upload it as a file to the coursework instance

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  @javascript @_file_upload
  Scenario: I upload a file and see it on the coursework page as read only
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I should see "Not submitted" in the ".behat-submission-information" "css_element"
    And I click on "Upload your submission" "link"
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"

    When I am on the "Coursework" "coursework activity" page
    And I should see "Test_document.docx"
    And I should see "Submitted" in the ".behat-submission-information" "css_element"
    And I should see "##today##%d %B %Y##" in the ".behat-submission-information" "css_element"

    When I am on the "Coursework" "coursework activity" page
    And I click on "Edit your submission" "link"
    Then I should see "1" elements in "Upload a file" filemanager
