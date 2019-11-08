@mod @mod_coursework
Feature: Automatic percentage assessor allocations

    As a manager
    I want to be able to allocate assesors to students using percentages for each assessor
    So that the marking is fairly distributed and the interface is less cluttered for teachers,
    and they don't mark to many or too few.

    Background:
        Given there is a course
        And there is a coursework
        And the coursework "allocationenabled" setting is "1" in the database
        And the coursework "numberofmarkers" setting is "1" in the database
        And the managers are not allowed to grade
        And there is a student
        And there is a teacher
        And I am logged in as a manager
        And there are no allocations in the db

    @javascript
    Scenario: Automatic percentage allocations should allocate to the right teacher
        Given there is another teacher
        And there are no allocations in the db
        When I visit the allocations page
        And I set the allocation strategy to 100 percent for the other teacher
        And I press "Apply"
        When I visit the allocations page
        Then I should see the student allocated to the other teacher for the first assessor

    Scenario: percentage allocations should not allocate to the wrong teacher
        Given there is another teacher
        And there are no allocations in the db
        When I visit the allocations page
        And I set the allocation strategy to 100 percent for the other teacher
        And I save everything
        And I log out
        And I log in as the teacher
        And I visit the coursework page
        Then I should not see the student's name on the page




