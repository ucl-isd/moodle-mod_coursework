@mod @mod_coursework @mod_coursework_feedback_zero_grades
Feature: Zero grades should show up just like the others

  As a teacher
  I want to be abel to award a grade of zero
  So that in case there is no work submitted or the work is truly and irredeemably useless,
  the student will know

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: Single maker final feedback
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "student1" "table_row"
    And I set the field "Mark" to "0"
    And I press "Save and finalise"
    Then I am on the "Coursework" "coursework activity" page
    And I should see "0" in the "student1" "table_row"
