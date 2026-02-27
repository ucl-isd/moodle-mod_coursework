@mod @mod_coursework
Feature: Add assessors tab appears for users with moodle/role:assign

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
      | teacher2 | teacher   | teacher2        | teacher2@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher2 | C1     | editingteacher |
      | manager1 | C1     | manager        |

  Scenario: Manager can see add assessors
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "Add markers"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see "Add markers"
