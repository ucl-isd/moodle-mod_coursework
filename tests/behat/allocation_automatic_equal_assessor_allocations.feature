@mod @mod_coursework
Feature: Automatic equal assessor allocations

    As a manager
    I want to be able to allocate assesors to students
    So that the marking is fairly distributed and the interface is less cluttered for teachers,
    and they don't mark to many or too few.

    Background:
        Given the managers are not allowed to grade
        And there is a course
        And there is a coursework
        And the coursework "allocationenabled" setting is "1" in the database
        And the coursework "numberofmarkers" setting is "1" in the database
        And there is a student
        And there is a teacher
        And teachers hava a capability to administer grades
        And I am logged in as a manager
        And there are no allocations in the db

    Scenario: Automatic allocations should work
        When I visit the allocations page
        And I save everything
        And I log out
        And I log in as the teacher
        And I visit the coursework page
        Then I should see the student's name on the page

    Scenario: Automatic allocations of non-manually allocated should work
        When I visit the allocations page
        And I save everything
        And I log out
        And I log in as the teacher
        And I visit the coursework page
        Then I should see the student's name on the page





