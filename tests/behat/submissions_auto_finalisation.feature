@mod @mod_coursework @mod_coursework_auto_finalisation
Feature: Auto finalising before cron runs

  As a teacher
  I want to see all work finalised as soon as the deadline passes, without having to
  wait for the cron to run
  So that I can start marking immediately

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 0               |

  Scenario: Teacher visits the page and sees the submission is finalised when the deadline has passed
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should not see "Add mark" in the table row containing "student student1"

    When I navigate to "Settings" in current page administration
    And I set the field "deadline" to "##yesterday##"
    And I press "Save and display"
    Then I should see "Add mark" in the table row containing "student student1"
