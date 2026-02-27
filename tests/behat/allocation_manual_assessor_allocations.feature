@mod @mod_coursework @mod_coursework_markingallocation
Feature: Manually assessor allocations

  In order to make sure that the right assessors grade the right students
  As a course leader
  I want to be able to manually allocate students to assessors

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework |
      | course                     | C1         |
      | name                       | Coursework |
      | allocationenabled          | 1          |
      | assessorallocationstrategy | none       |
      | numberofmarkers            | 2          |
    And the following "users" exist:
      | username | firstname    | lastname | email                |
      | teacher2 | teacher      | teacher2 | teacher2@example.com |
      | manager1 | Manager      | 1        | manager1@example.com |
      | student1 | student      | student1 | student1@example.com |
      | student5 | student      | student5 | student5@example.com |
      | student6 | student      | student6 | student6@example.com |
      | teacher4 | otherteacher | teacher4 | teacher4@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
      | teacher4 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |
      | student5 | C1     | student |
      | student6 | C1     | student |

  @javascript
  Scenario: Teachers do not see students who are unallocated or allocated to other teachers
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"

    Then I should not see "teacher teacher2" in the "student student1" "table_row"
    And I should not see "teacher teacher4" in the "student student1" "table_row"
    And I should not see "teacher teacher2" in the "student student5" "table_row"
    And I should not see "otherteacher teacher4" in the "student student5" "table_row"

    When I navigate to "Allocate markers" in current page administration
    And I set the following fields in the "student student1" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2      |
      | Choose marker assessor_2 | otherteacher teacher4 |
    And I set the following fields in the "student student5" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2 |
    And I am on the "Coursework" "coursework activity" page
    Then I should see "otherteacher teacher4" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student5" "table_row"
    And I should not see "otherteacher teacher4" in the "student student5" "table_row"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should see "student student1" in the ".mod-coursework-submissions-table" "css_element"
    Then I should see "student student5" in the ".mod-coursework-submissions-table" "css_element"
    And I should not see "student student6" in the ".mod-coursework-submissions-table" "css_element"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher4"
    Then I should see "student student1" in the ".mod-coursework-submissions-table" "css_element"
    And I should not see "student student5" in the ".mod-coursework-submissions-table" "css_element"
    And I should not see "student student6" in the ".mod-coursework-submissions-table" "css_element"
