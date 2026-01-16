@mod @mod_coursework @mod_coursework_markingallocation
Feature: Manually assessor allocations

  In order to make sure that the right assessors grade the right students
  As a course leader
  I want to be able to manually allocate students to assessors

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allocationenabled" setting is "1" in the database
    And the coursework "assessorallocationstrategy" setting is "none" in the database
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a student
    And there is a teacher
    And I am logged in as a manager

  Scenario: Teachers do not see students who are allocated to other teachers
    Given there is another teacher
    And there are no allocations in the db
    When I visit the allocations page
    And I manually allocate the student to the other teacher
    And I log out
    And I log in as the teacher
    And I visit the coursework page
    Then I should not see the student's name on the page

  @javascript
  Scenario: auto allocations should not alter the manual allocations
    Given there is another teacher
    And there are no allocations in the db
    And I am on the "Coursework 1" "coursework activity" page
    Then I should not see "teacher teacher2" in the "student student1" "table_row"
    When I visit the allocations page
    And I set the following fields in the "student student1" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2 |
    And I am on the "Coursework 1" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"

  @javascript
  Scenario: allocating multiple teachers to multiple learners
    Given there is another teacher
    And there is another student
    And I am on the "Coursework 1" "coursework activity" page
    Then I should not see "teacher teacher2" in the "student student1" "table_row"
    And I should not see "otherteacher teacher4" in the "student student1" "table_row"
    And I should not see "teacher teacher2" in the "student student5" "table_row"
    And I should not see "otherteacher teacher4" in the "student student5" "table_row"
    When I visit the allocations page
    And I set the following fields in the "student student1" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2      |
      | Choose marker assessor_2 | otherteacher teacher4 |
    And I set the following fields in the "otherstudent student5" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2 |
    And I am on the "Coursework 1" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"
    And I should see "otherteacher teacher4" in the "student student1" "table_row"
    And I should see "teacher teacher2" in the "student student5" "table_row"
    And I should not see "otherteacher teacher4" in the "student student5" "table_row"
