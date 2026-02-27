@mod @mod_coursework @mod_coursework_auto_finalisation
Feature: Auto finalising before cron runs

    As a teacher
    I want to see all work finalised as soon as the deadline passes, without having to
    wait for the cron to run
    So that I can start marking immediately

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And there is a student
    And the student has a submission
    And the submission is not finalised

  Scenario: Teacher visits the page and sees the submission is finalised when the deadline has passed
    Given I am logged in as a teacher
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Add mark" in the table row containing "student student1"
    And the coursework deadline has passed
    When I reload the page
    Then I should see "Add mark" in the table row containing "student student1"
