@mod @mod_coursework
Feature: View of all students: allocated and non allocated students

  As a user with allocated and non allocated students
  I want to see the students who have been allocated at the top
  so that the rest of the enrolled students are toggled below the allocated students

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allocationenabled" setting is "1" in the database
    And the coursework "assessorallocationstrategy" setting is "0" in the database
    And the coursework "numberofmarkers" setting is "1" in the database
    And there is a student
    And there is a teacher
    And there is another student
    And there is another teacher
    And I am logged in as a manager
    And I visit the allocations page
    And I set the following fields in the "student student1" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2 |
        And I manually allocate another student to another teacher
    And I log out

  Scenario: Teachers see all unallocated students pressing the toggle button
    Given I log in as the teacher
    And I am allowed to view all students
    And I visit the coursework page
    And I click show all students button
    Then I should see another student's name on the page
