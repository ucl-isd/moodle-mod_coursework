@mod @mod_coursework
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

    Scenario: Automatic allocations should not alter the manual allocations
        Given there is another teacher
        And there are no allocations in the db
        When I visit the allocations page
        And I manually allocate the student to the teacher
        And I set the allocation strategy to 100 percent for the other teacher
        And I save everything
        When I visit the allocations page
        Then I should see the student allocated to the teacher for the first assessor

    @javascript
    Scenario: Automatic allocations should wipe the older automatic allocations
        Given the student is allocated to the teacher
        And there is another teacher
        When I visit the allocations page
        And I set the allocation strategy to 100 percent for the other teacher
        And I press "Apply"
        When I visit the allocations page
        Then I should see the student allocated to the other teacher for the first assessor

