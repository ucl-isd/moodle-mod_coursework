@mod @mod_coursework
Feature: Visibility for teachers with blind marking

  As a manager
  I want to be able to prevent teachers from seeing each others' marks
  So that I can be sure that they are not influenced by each other and the marking is fair

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity     | coursework |
      | course       | C1         |
      | name         | Coursework |
      | blindmarking | 1          |
      | renamefiles  | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |

  Scenario: The student names are hidden from teachers in the user cells
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see the student's name in the user cell
    And I should not see the student's picture in the user cell

  @javascript
  Scenario: The user names are hidden from teachers in the group cells
    Given group submissions are enabled
    And the student is a member of a group
    And the group is part of a grouping for the coursework
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "View members" in the "My group" "table_row"
    And I click on "View members" "button"
    Then I should not see "student student2" in the ".dropdown-menu.show" "css_element"
    Then I should see "Members are hidden" in the ".dropdown-menu.show" "css_element"

  Scenario: Teachers cannot see other initial grades before final grading happens
    Given the coursework "numberofmarkers" setting is "2" in the database
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should not see "67" in the "Hidden" "table_row"
    But I should see "Marked" in the "Hidden" "table_row"
    And I should see "63" in the "Hidden" "table_row"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see "63" in the "Hidden" "table_row"
    But I should see "Marked" in the "Hidden" "table_row"
    And I should see "67" in the "Hidden" "table_row"
