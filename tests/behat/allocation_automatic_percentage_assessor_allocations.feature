@mod @mod_coursework @mod_coursework_markingallocation
Feature: Automatic percentage assessor allocations

  As a manager
  I want to be able to allocate assesors to students using percentages for each assessor
  So that the marking is fairly distributed and the interface is less cluttered for teachers,
  and they don't mark to many or too few.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity          | coursework |
      | course            | C1         |
      | name              | Coursework |
      | allocationenabled | 1          |
      | numberofmarkers   | 1          |
    And the following "users" exist:
      | username | firstname    | lastname | email                |
      | teacher2 | teacher      | teacher2 | teacher2@example.com |
      | manager1 | Manager      | 1        | manager1@example.com |
      | student1 | student      | student1 | student1@example.com |
      | teacher4 | otherteacher | teacher4 | teacher4@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
      | teacher4 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |

  @javascript
  Scenario: Automatic percentage allocations should allocate to the right teacher
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessorallocationstrategy | Percentage per marker |
      | teacher teacher2           | 0                     |
      | otherteacher teacher4      | 100                   |
    And I press "Apply"
    Then I should see "otherteacher teacher4" in the "student student1" "table_row"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should not see "student student1" in the ".mod-coursework-submissions-table" "css_element"

    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | assessorallocationstrategy | Percentage per marker |
      | teacher teacher2           | 100                   |
      | otherteacher teacher4      | 0                     |
    And I press "Apply"
    When I am on the "Coursework" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    Then I should see "student student1" in the ".mod-coursework-submissions-table" "css_element"
