@mod @mod_coursework
Feature: Visibility of allocated teachers

    As a manager
    I want to know who is allocated to mark each submission
    So that I do not accidentally mark the wrong stuff

    Background:
        Given there is a course
        And there is a coursework
        And there is a student

    Scenario: I should see the name of the allocated teacher in the assessor feedback cell
        Given there is a teacher
        And the coursework is set to single marker
        And the coursework has assessor allocations enabled
        And the student is manually allocated to the teacher
        When I log in as a manager
        And I visit the coursework page
        Then I should see the name of the teacher in the assessor feedback cell
