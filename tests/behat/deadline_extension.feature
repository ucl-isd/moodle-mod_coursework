@mod @mod_coursework @RVC_PT_83106618 @mod_coursework_deadline_extension
Feature: Deadlines extensions for submissions

  As a manager
  I can add allow students to submit after the deadline
  So that late work can still be given a grade

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | deadline                   | ##yesterday## |
    And the coursework individual extension option is enabled
    And there is a student

  Scenario: The student can submit after the deadline when the start date is disabled
    Given there is an extension for the student that allows them to submit
    When I log in as a student
    And I am on the "Coursework" "coursework activity" page
    Then "Upload your submission" "link" should exist
    And I should see extension date "##+1 week##%d %B %Y##"

  Scenario: The student can not submit when the start date is in the future
    Given there is an extension for the student which has expired
    When I log in as a student
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Upload your submission"

  @javascript
  Scenario: The manager can add a deadline extension to an individual submission
    Given I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I press "Actions"
    And I wait until the page is ready
    And I click on "Submission extension" "link"
    And I wait until the page is ready
    And I set the field "Extended deadline" to "##+2 weeks, 8:00 AM##"
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"
    Then I am on the "Coursework" "coursework activity" page
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"

  @javascript
  Scenario: The manager can edit a deadline extension to an individual submission
    Given there is an extension for the student which has expired
    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I press "Actions"
    And I wait until the page is ready
    And I click on "Submission extension" "link"
    And I wait until the page is ready
    And I set the field "Extended deadline" to "##+2 weeks, 8:00 AM##"
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"
    Then I am on the "Coursework" "coursework activity" page
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"
