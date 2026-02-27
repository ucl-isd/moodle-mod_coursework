@mod @mod_coursework @mod_coursework_automatic_agreement
Feature: Automatic agreement for simple grades

  As an user with add/edit coursework capability
  I can add an automatic agreement for double marking when both simple grades are adjacent within a specified range,
  so that the highest grade is chosen for all cases apart from the fail grades.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                    | coursework    |
      | course                      | C1            |
      | name                        | Coursework    |
      | numberofmarkers             | 2             |
      | deadline                    | ##yesterday## |
      | autopopulatefeedbackcomment | 1             |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Manager   | 1        | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | manager1 | C1     | manager |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | student1 | C1     | student |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  @javascript
  Scenario: If "Auto-populate agreed feedback comment" is enabled then the final grade should contain the combined feedback of markers
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  |
      | student1    | Coursework | teacher1 | assessor_1      | 67    | New comment here |
      | student1    | Coursework | teacher2 | assessor_2      | 63    | New comment here |
    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student student1" "table_row"
    Then the following fields match these values:
      | Comment | <p>Marker 1 comment:<br>New comment here<br>Marker 2 comment:<br>New comment here</p> |
