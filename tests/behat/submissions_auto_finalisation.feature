@mod @mod_coursework @mod_coursework_auto_finalisation @javascript
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
    And the submission is not finalised

  Scenario: Teacher visits the page and sees the submission is finalised when the deadline has passed
    Given I am logged in as a teacher
    And I visit the coursework page
    Then I should not see "Add feedback" in the table row containing "student student1"
    And the coursework deadline has passed
    When I reload the page
    Then I should see "Add feedback" in the table row containing "student student1"
