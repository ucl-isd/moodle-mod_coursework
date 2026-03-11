@mod @mod_coursework @javascript @mod_coursework_plagiarism_flagging
Feature: Teachers and course administrators should be able to add and edit
  plagiarism flags

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the coursework "plagiarismflagenabled" setting is "1" in the database
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: Teacher can flag a submission for plagiarism
    Given I am logged in as a teacher
    And I am on the "Coursework" "coursework activity" page
    And I should not see "Flagged for plagiarism"
    And "Actions" "button" should exist
    And I click on "Actions" "button"
    And I click on "Plagiarism action" "link"
    And I set the field "Status" to "Under Investigation"
    And I set the field "Internal comment" to "Test comment"
    And I click on "Save" "button"
    And I wait until the page is ready
    Then I should see "Flagged for plagiarism"
