@mod @mod_coursework @RVC_PT_83106618 @mod_coursework_deadline_extension
Feature: Deadlines extensions for submissions

  As a manager
  I can add allow students to submit after the deadline
  So that late work can still be given a grade

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity          | coursework    |
      | course            | C1            |
      | name              | Coursework    |
      | deadline          | ##yesterday## |
      | extensionsenabled | 1             |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |

  Scenario: A teacher and a student should both see the deadline
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see due date "##yesterday##%d %B %Y##"
    But I should not see "Extended deadline"

    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see due date "##yesterday##%d %B %Y##"
    But I should not see "Extended deadline"

  Scenario: The student can submit after the deadline when they have a deadline extension
    Given the following "mod_coursework > deadline_extensions" exist:
      | allocatable | coursework | deadline    |
      | student1    | Coursework | ##+1 week## |
    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then "Upload your submission" "link" should exist
    And I should see extension date "##+1 week##%d %B %Y##"

  Scenario: The student can not submit when their deadline extension is in the past
    Given the following "mod_coursework > deadline_extensions" exist:
      | allocatable | coursework | deadline    |
      | student1    | Coursework | ##-1 week## |
    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Upload your submission"

  @javascript
  Scenario: The manager can add and edit a deadline extension to an individual submission
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I press "Actions"
    And I wait until the page is ready
    And I click on "Submission extension" "link"
    And I wait until the page is ready
    And I set the field "Extended deadline" to "##+2 weeks, 8:00 AM##"
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    Then I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"
    When I am on the "Coursework" "coursework activity" page
    Then I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"

    Given I press "Actions"
    And I wait until the page is ready
    And I click on "Submission extension" "link"
    And I wait until the page is ready
    And I set the field "Extended deadline" to "##+3 weeks, 8:00 AM##"
    And I click on "Save" "button" in the "Extended deadline" "dialogue"
    Then I should see "##+3 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"
    When I am on the "Coursework" "coursework activity" page
    And I should see "##+3 weeks##%d %B %Y, 8:00 AM##" in the "student student1" "table_row"
