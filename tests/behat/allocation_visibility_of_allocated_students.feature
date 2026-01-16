@mod @mod_coursework
Feature: Visibility of allocated students

  In order to make sure that the right assessors grade the right students
  As a course leader
  I want teachers to only see the students who have been allocated to them

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "allocationenabled" setting is "1" in the database
    And the coursework "assessorallocationstrategy" setting is "none" in the database
    And there is a student
    And there is a teacher

  Scenario: Teachers do not see students who are unallocated
    Given I log in as the teacher
    And I visit the coursework page
    Then I should not see the student's name on the page

  @javascript
  Scenario: I can allocate a student manually and the teacher will see them
    Given I am logged in as a manager
    When I visit the allocations page
    And I set the following fields in the "student student1" "table_row" to these values:
      | Choose marker assessor_1 | teacher teacher2 |
    And I log out
    And I log in as the teacher
    And I visit the coursework page
    And I am on the "Coursework 1" "coursework activity" page
    Then I should see "teacher teacher2" in the "student student1" "table_row"
