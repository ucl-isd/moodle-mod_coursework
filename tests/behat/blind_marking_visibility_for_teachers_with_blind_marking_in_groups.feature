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
      | usegroups    | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |

  @javascript
  Scenario: The user names are hidden from teachers in the group cells
    Given the following "groups" exist:
      | course | idnumber | name     |
      | C1     | G1       | My group |
    And the following "group members" exist:
      | group | user     |
      | G1    | student1 |
    And the following "groupings" exist:
      | name | course | idnumber |
      | GX1  | C1     | GXI1     |
    And the following "grouping groups" exist:
      | grouping | group |
      | GXI1     | G1    |
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "View members" in the "My group" "table_row"
    And I click on "View members" "button"
    Then I should not see "student student1" in the ".dropdown-menu.show" "css_element"
    Then I should see "Members are hidden" in the ".dropdown-menu.show" "css_element"
