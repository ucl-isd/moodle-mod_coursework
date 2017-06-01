Feature: Collisions: two people try to create feedback at the same time

    As a teacher
    I want to see a warning message if I try to save my feedback when another
    teacher has already done so
    So that I do not get a surprise when the grades I have awarded disappear

    Background:
        Given there is a course
        And there is a coursework
        And there is a student
        And the student has a submission
        And the submission is finalised

    Scenario: Single marker: If I submit feedback and it's already been given then the form should reload with a warning
        Given there is a teacher
        And there is another teacher
        And I am logged in as the other teacher
        And the coursework is set to single marker
        When I visit the coursework page
        And I click the new single final feedback button for the student
        And I have an assessor feedback
        When I grade the submission using the simple form
        Then I should be on the create feedback page
        And I should see "has already submitted"
        Then I should see the grade comment in the form on the page
        And I should see the grade in the form on the page

    Scenario: Multiple marker: If I submit feedback and it's already been given then it should be given a new stage_identifier
        Given there is a teacher
        And there is another teacher
        And I am logged in as the other teacher
        And the coursework is set to double marker
        When I visit the coursework page
        And I click on the new feedback button for assessor 1
        And I have an assessor feedback
        When I grade the submission using the simple form
        Then I should be on the coursework page

    Scenario: Multiple marker: If I submit feedback and it's already been given by all teachers then it should fail
        Given there is a teacher
        And there is another teacher
        And I am logged in as a manager
        And the coursework is set to double marker
        When I visit the coursework page
        And I click on the new feedback button for assessor 1
        And I have an assessor feedback
        And there is final feedback from the other teacher
        When I grade the submission using the simple form
        Then I should be on the create feedback page

