@mod @mod_coursework @RVC_PT_83107284 @mod_coursework_submit_on_behalf
Feature: User can submit on behalf of a student

  As a user with the capability ‘coursework:submitonbehalfofstudent’
  I can submit a file on behalf of a student.
  so that the work can be graded by the grader.
  I can see submitted files on the grading report page

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
      | manager1 | manager   | manager1 | manager1@example.com |
      | student1 | John1     | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | student1 | C1     | student |

  @javascript @_file_upload
  Scenario: As a manager, I upload a file and see it on the coursework page
    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I press "Actions"
    And I wait until the page is ready
    And I click on "Submit on behalf" "link"
    And I wait until the page is ready
    And I upload "mod/coursework/tests/files_for_uploading/Test_document.docx" file to "Upload a file" filemanager
    And I press "Submit"

    When I am on the "Coursework" "coursework activity" page
    And I should see "Test_document.docx" in the table row containing "John1"
    And I should see "Draft" in the table row containing "John1"

    And I press "Actions"
    And I wait until the page is ready
    And I click on "Edit submission on behalf of this student" "link"
    And I wait until the page is ready
    Then I should see "1" elements in "Upload a file" filemanager
    And I should see "Test_document.docx"

  Scenario: As a manager, I can see draft submitted file on the grading report page
    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should not see "myfile.txt"
    When the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 0               |
    And I am on the "Coursework" "coursework activity" page
    Then I should see "myfile.txt" in the table row containing "John1"
    And I should see "Draft" in the table row containing "John1"

  Scenario: As a manager, I can see draft submitted file on the grading report page
    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am on the "Coursework" "coursework activity" page
    Then I should see "myfile.txt" in the table row containing "John1"
    And I should not see "Draft" in the table row containing "John1"
