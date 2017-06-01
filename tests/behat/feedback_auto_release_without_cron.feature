Feature: Auto releasing the student feedback without cron

    As a student
    I want to be able to see my grades and feedback as soon as the deadline
    for automatic release passes
    So that I get the feedback I need and don't think the system is broken

    Background:
        Given there is a course
        And there is a coursework
        And the coursework is set to single marker
        And there is a student
        And the student has a submission
        And there is a teacher
        And there is feedback for the submission from the teacher

    Scenario: auto release happens after the deadline without the cron running
        Given the coursework individual feedback release date has passed
        When I log in as a student
        And I visit the coursework page
        Then I should see "Released to students"

    Scenario: auto release does not happen before the deadline without the cron running
        Given the coursework individual feedback release date has not passed
        When I log in as a student
        And I visit the coursework page
        Then I should not see "Released to students"