@mod @mod_coursework @mod_coursework_feedback_single_marking
Feature: As a teacher and sole grader I can have my feedback presented anonymously to the student.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 1          |
    |assessoranonymity|1           |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
      | manager1 | student   | manager1 | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
      | manager1 | C1     | manager |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment |finalised|
      | student1    | Coursework | teacher1 | assessor_1      | 58    | Blah            |1        |

  Scenario: Student cannot see marker when assessor anonymity is enabled
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I follow "Release the marks"

    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    And I should see "Agreed feedback"
    Then I should not see "Admin User" in the ".coursework-feedback" "css_element"
    And I should not see "teacher teacher1" in the ".coursework-feedback" "css_element"
    But I should see "Marker 1" in the ".coursework-feedback" "css_element"
