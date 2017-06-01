Feature: Auto finalising before cron runs

    As a teacher
    I want to see all work finalised as soon as the deadline passes, without having to
    wait for the cron to run
    So that I can start marking immediately

    Background:
        Given there is a course
        And there is a coursework
        And there is a student
        And the student has a submission

    Scenario: Teacher visits the page and sees the submission is finalised when the deadline has passed
        Given I am logged in as a teacher
        And the coursework deadline has passed
        When I visit the coursework page
        Then I should see "Ready to grade"

    Scenario: Teacher visits the page and sees the submission is not finalised when the deadline has not passed
        Given I am logged in as a teacher
        When I visit the coursework page
        Then I should not see "Ready to grade"
