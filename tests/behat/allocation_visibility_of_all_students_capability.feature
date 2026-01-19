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
    And I set the following fields in the "otherstudent student3" "table_row" to these values:
      | Choose marker assessor_1 | otherteacher teacher4 |
    And I log out

  @javascript
  Scenario: Teachers see students who are allocated and do not see students who are unallocated
    Given I log in as the teacher
    And I visit the coursework page
    Then I should see the student's name on the page
    Then I should not see another student's name on the page
