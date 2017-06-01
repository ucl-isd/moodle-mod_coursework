@mod @mod_coursework
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

    Scenario: auto allocations should not alter the manual allocations
        Given there is another teacher
        And there are no allocations in the db
        When I visit the allocations page
        And I manually allocate the student to the teacher
        And I set the allocation strategy to 100 percent for the other teacher
        And I save everything
        And I visit the allocations page
        Then I should see the student allocated to the teacher for the first assessor

    Scenario: allocating multiple teachers
        Given there is another teacher
        When I visit the allocations page
        And I manually allocate the student to the teacher
        And I save everything
        And I visit the allocations page
        And I manually allocate the student to the other teacher for the second assessment
        And I save everything
        And I visit the allocations page
        Then I should see that the student has two allcations

    Scenario: Allocations work for more than one student
        Given there is another student
        When I visit the allocations page
        And I manually allocate the student to the teacher
        And I manually allocate the other student to the teacher
        And I save everything
        Then I should see that both students are allocated to the teacher
