@mod @mod_coursework
Feature: Deadlines for submissions

    As a teacher
    I want to set deadlines that are viible to the student
    So that they know when they are expected to submut, and can be sent automatic reminders

    Background:
        Given there is a course
        And I am logged in as a teacher
        And there is a coursework

    Scenario: the general feedback deadline should be visible if enabled and set
        Given the coursework "generalfeedback" setting is "777777" in the database
        And the coursework general feedback is enabled
        When I visit the coursework page
        Then I should see the date when the general feedback will be released

    Scenario: the individual feedback deadline should not be visible if not enabled
        Given the coursework "individualfeedback" setting is "0" in the database
        When I visit the coursework page
        Then I should not see the date when the individual feedback will be released

    Scenario: the individual feedback deadline should be visible if enabled
        Given the coursework "individualfeedback" setting is "777777" in the database
        When I visit the coursework page
        Then I should see the date when the individual feedback will be released

    Scenario: the general feedback deadline should be visible if not enabled
        Given the coursework "individualfeedback" setting is "7777777" in the database
        When I visit the coursework page
        Then I should see the date when the individual feedback will be released