@mod @mod_coursework @mod_coursework_allocation_auto_interact_manual @mod_coursework_markingallocation
Feature: Automatically allocations interacting with manually allocated students

    As a manager
    I want to be able to reallocate all of the non manual students
    So that if the number of students or teachers has changed, I can make sure everything remains balanced

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allocationenabled" setting is "1" in the database
    And the coursework "numberofmarkers" setting is "1" in the database
    And the managers are not allowed to grade
    And there is a student
    And there is a teacher
    And I am logged in as a manager

  @javascript
  Scenario: Automatic allocations should not alter the manual allocations
    Given there is another teacher
    And there are no allocations in the db
    And I am on the "Coursework 1" "coursework activity" page
    Then I should not see "teacher teacher2" in the "student student1" "table_row"
    When I visit the allocations page
    And I set the following fields in the "student student1" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2 |
    And I set the allocation strategy to 100 percent for the other teacher
    And I am on the "Coursework 1" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"

  @javascript
  Scenario: Automatic allocations should wipe the older automatic allocations
    Given the student is allocated to the teacher
    And there is another teacher
    When I visit the allocations page
    And I set the allocation strategy to 100 percent for the other teacher
    And I wait until the page is ready
    And I click on "Apply" "button"
    # Apply button will reload page via module.js when call to /mod/coursework/actions/processallocation.php returns.
    And I wait until the page is ready
    And I wait "3" seconds
    Then I should see the student allocated to the other teacher for the first assessor
