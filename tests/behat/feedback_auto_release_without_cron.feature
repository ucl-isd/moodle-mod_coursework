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
    And the student has a submission
    And there is a teacher
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment |
      | student1    | Coursework | teacher1 | assessor_1      | 58    | Blah            |

  Scenario: auto release does not happen before the deadline without the cron running
    Given the coursework individual feedback release date has not passed
    When I log in as a student
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Released"

  Scenario: auto release happens after the deadline without the cron running
    Given the coursework individual feedback release date has passed
    And I log in as a student
    When I am on the "Coursework" "coursework activity" page
    Then I should see "Released"
