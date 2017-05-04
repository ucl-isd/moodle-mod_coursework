Feature: Zero grades should show up just like the others

    As a teacher
    I want to be abel to award a grade of zero
    So that in case there is no work submitted or the work is truly and irredeemably useless,
    the student will know

    Background:
        Given there is a course
        And there is a coursework
        And there is a student
        And the student has a submission
        And the submission is finalised

    Scenario: Single maker final feedback
        Given the coursework "grade" setting is "9" in the database
        Given I am logged in as a teacher
        And the coursework "numberofmarkers" setting is "1" in the database
        When I visit the coursework page
        And I click the new single final feedback button for the student
        And I grade the submission as 0 using the simple form
        Then I should be on the coursework page
        And I should see the final grade as 0 on the single marker page

