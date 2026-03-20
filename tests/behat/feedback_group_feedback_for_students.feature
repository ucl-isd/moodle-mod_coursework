@mod @mod_coursework @mod_coursework_feedback_group_feedback_for_students
Feature: Students see feedback on group assignments

  As a student
  I want to be able to see the feedback for the group assignment even if I did not submit it
  So that I know what my marks are and can improve my work

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 2          |
      | usegroups       | 1          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | student2 | student   | student2 | student2@example.com |
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | teacher1 | C1     | teacher |
      | teacher2 | C1     | teacher |
      | manager1 | C1     | manager |
    And the following "groups" exist:
      | course | idnumber | name     |
      | C1     | G1       | My group |
    And the following "group members" exist:
      | group | user     |
      | G1    | student1 |
      | G1    | student2 |
    Given the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus | createdby |
      | G1          | Coursework | 1               | student1  |
    Given the following "mod_coursework > feedbacks" exist:
      | allocatable | coursework | assessor | stageidentifier | grade | feedbackcomment  | finalised |
      | G1          | Coursework | teacher1 | assessor_1      | 67    | New comment here | 1         |
      | G1          | Coursework | teacher2 | assessor_2      | 63    | New comment here | 1         |

  Scenario: Users in groups can see the published grade whether or not they submitted
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    When I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the following fields to these values:
      | Mark    | 45   |
      | Comment | blah |
    And I press "Save and finalise"
    And I should see "Changes saved"
    And I follow "Release the marks"

    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "45" in the "#behat-final-feedback-grade" "css_element"
    And I should see "blah" in the "#behat-final-feedback-comment" "css_element"
    When I am on the "Course 1" "grades > User report > View" page
    Then I should see "45.00"

    Given I am on the "Coursework" "coursework activity" page logged in as "student2"
    Then I should see "45" in the "#behat-final-feedback-grade" "css_element"
    And I should see "blah" in the "#behat-final-feedback-comment" "css_element"
    When I am on the "Course 1" "grades > User report > View" page
    Then I should see "45.00"
