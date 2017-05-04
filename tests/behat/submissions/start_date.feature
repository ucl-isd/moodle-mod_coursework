Feature: Start date

    As a teacher
    I want to be able to restrict the start date of the coursework
    So that students will not begin to work on it until the right time

    Background:
        Given there is a course
        And there is a coursework

    Scenario: The student can submit when the start date is disabled
        Given the coursework start date is disabled
        When I log in as a student
        And I visit the coursework page
        Then I should see the new submission button

    Scenario: The student can not submit when the start date is in the future
        Given the coursework start date is in the future
        When I log in as a student
        And I visit the coursework page
        Then I should not see the new submission button

    Scenario: The student can submit when the start date is in the past
        Given the coursework start date is in the past
        When I log in as a student
        And I visit the coursework page
        Then I should see the new submission button