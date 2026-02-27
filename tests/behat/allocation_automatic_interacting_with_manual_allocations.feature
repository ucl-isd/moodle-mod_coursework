@mod @mod_coursework @mod_coursework_allocation_auto_interact_manual @mod_coursework_markingallocation
Feature: Automatically allocations interacting with manually allocated students

  As a manager
  I want to be able to reallocate all of the non manual students
  So that if the number of students or teachers has changed, I can make sure everything remains balanced

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
      | teacher4 | otherteacher | teacher4 | teacher4@example.com |
      | manager1 | Manager      | 1        | manager1@example.com |
      | student1 | student      | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher2 | C1     | teacher |
      | teacher4 | C1     | teacher |
      | manager1 | C1     | manager |
      | student1 | C1     | student |
    And I log in as "manager1"

  @javascript
  Scenario: Automatic allocations should not alter the manual allocations
    Given I am on the "Coursework" "coursework activity" page
    Then I should not see "teacher teacher2" in the "student student1" "table_row"

    When I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | Choose marker assessor_1   | teacher teacher2      |
      | assessorallocationstrategy | Percentage per marker |
      | otherteacher teacher4      | 100                   |
    And I press "Apply"
    And I am on the "Coursework" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"

    When I navigate to "Allocate markers" in current page administration
    Then the following fields match these values:
      | assessorallocationstrategy | Percentage per marker |
      | otherteacher teacher4      | 100                   |
      | Choose marker assessor_1   | teacher teacher2      |

    When I am on the "Coursework" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"

  @javascript
  Scenario: Automatic allocations should wipe the older automatic allocations
    Given I am on the "Coursework" "coursework activity" page
    And I navigate to "Allocate markers" in current page administration
    And I set the following fields to these values:
      | Choose marker assessor_1 | teacher teacher2 |
      | Pinned                   | 0                |

    When I am on the "Coursework" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"

    When I navigate to "Allocate markers" in current page administration
    Then the following fields match these values:
      | Choose marker assessor_1 | teacher teacher2 |
      | Pinned                   | 0                |

    When I set the following fields to these values:
      | assessorallocationstrategy | Percentage per marker |
      | otherteacher teacher4      | 100                   |
    And I click on "Apply" "button"
    When I am on the "Coursework" "coursework activity" page
    Then I should see "otherteacher teacher4" in the "student student1" "table_row"
