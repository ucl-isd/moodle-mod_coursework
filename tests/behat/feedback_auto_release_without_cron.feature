@mod @mod_coursework
Feature: Auto releasing the student feedback without cron

  As a student
  I want to be able to see my grades and feedback as soon as the deadline
  for automatic release passes
  So that I get the feedback I need and don't think the system is broken

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment |
      | student1    | Coursework | teacher1 | assessor_1      | 58    | Blah            |

  Scenario: auto release does not happen before the deadline without the cron running
    When I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "individualfeedback" to "##+1 week##"
    And I press "Save and display"

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Released"

  Scenario: auto release happens after the deadline without the cron running
    When I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "individualfeedback" to "##-1 week##"
    And I press "Save and display"

    Given the coursework individual feedback release date has passed
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Released"
