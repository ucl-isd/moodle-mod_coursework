@mod @mod_coursework @mod_coursework_advanced_grading
Feature: Advanced rubric grade visibility
  In order to check rubric score visibility
  As a teacher and a student
  I need to confirm the score text appears only when the relevant option is enabled

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
      | student1 | student   | student1 | student1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Rubric"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name        | Test rubric        |
      | Description | Rubric description |
    And I define the following rubric:
      | first criterion | Bad | 1 | Ok | 2 | Good | 3 |
    And I press "Save rubric and make it ready"

  @javascript
  Scenario: Teacher sees the rubric total when the teacher score option is enabled
    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I go to "Coursework" advanced grading definition page
    And I set the field "Display points for each level during evaluation" to "1"
    And I press "Save"
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    When I click on "Add mark" "link" in the "student1" "table_row"
    And I grade by filling the rubric with:
      | first criterion | 3 | Very good |
    And I press "Save and finalise"
    And I click on "100" "link" in the "student student1" "table_row"
    Then I should see "Mark:"
    And I should see "3" in the ".total-score-text" "css_element"
    And I should see "3" in the ".total-max-text" "css_element"

  @javascript
  Scenario: Teacher does not see the rubric total when the teacher score option is disabled
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    When I click on "Add mark" "link" in the "student1" "table_row"
    And I grade by filling the rubric with:
      | first criterion | 3 | Very good |
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I go to "Coursework" advanced grading definition page
    And I set the field "Display points for each level during evaluation" to ""
    And I press "Save"
    And I press "Continue"
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "100" "link" in the "student student1" "table_row"
    Then I should not see "Mark:"

  @javascript
  Scenario: Student sees the rubric total when the student score option is enabled
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    When I click on "Add mark" "link" in the "student1" "table_row"
    And I grade by filling the rubric with:
      | first criterion | 3 | Very good |
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I go to "Coursework" advanced grading definition page
    And I set the field "Display points for each level to those being graded" to "1"
    And I press "Save"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I follow "Release the marks"
    And I press "Confirm"
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Mark:"
    And I should see "3" in the ".total-score-text" "css_element"
    And I should see "3" in the ".total-max-text" "css_element"

  @javascript
  Scenario: Student does not see the rubric total when the student score option is disabled
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    When I click on "Add mark" "link" in the "student1" "table_row"
    And I grade by filling the rubric with:
      | first criterion | 3 | Very good |
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I go to "Coursework" advanced grading definition page
    And I set the field "Display points for each level to those being graded" to ""
    And I press "Save"
    And I press "Continue"
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I follow "Release the marks"
    And I press "Confirm"
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Mark:"
