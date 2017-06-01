@mod @mod_coursework
Feature: Automatic agreement for simple grades

    As an user with add/edit coursework capability
    I can add an automatic agreement for double marking when both simple grades are adjacent within a specified range,
    so that the highest grade is chosen for all cases apart from the fail grades.

    Background:
        Given there is a course
        And there is a coursework
        And the coursework "numberofmarkers" setting is "2" in the database
        And there is a teacher
        And there is another teacher
        And there is a student
        And the student has a submission
        And the submission is finalised
        And the coursework deadline has passed

    Scenario: Only one grade in the submissions
        And the coursework "automaticagreementstrategy" setting is "none" in the database
        Given I am logged in as a teacher
        And I visit the coursework page
        And I click on the new feedback button for assessor 1
        When I grade the submission as 56 using the simple form
        Then I should not see the final grade on the multiple marker page

    Scenario: Simple grades within 10% boundaries takes higher mark as a final grade
        Given the coursework "automaticagreementstrategy" setting is "percentage_distance" in the database
        Given the coursework "automaticagreementrange" setting is "10" in the database
        And I am logged in as a teacher
        And I visit the coursework page
        And I click on the new feedback button for assessor 1
        And I grade the submission as 67 using the simple form
        And I log out
        And I log in as the other teacher
        And I visit the coursework page
        And I click on the new feedback button for assessor 2
        And I grade the submission as 63 using the simple form
        Then I should see the final grade as 67 on the multiple marker page













