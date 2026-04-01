@mod @mod_coursework @mod_coursework_feedback_single_marking
Feature: Adding and editing single feedback

  In order to provide students with a fair final grade that combines the component grades
  As a course leader
  I want to be able to edit the final grade via a form

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
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | student1 | student   | student1 | student1@example.com |
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

  Scenario: As an admin I can edit a teachers grade and a student should still see the original grader
    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    When I click on "58" "link" in the "student1" "table_row"
    And the field "Mark" matches value "58"
    And the field with xpath "//textarea[@id='id_feedbackcomment']" matches value "Blah"
    And I set the field "Mark" to "50"
    And I press "Save and finalise"
    Then I should see "50" in the "student student1" "table_row"
    And I should see "teacher teacher1" in the "student student1" "table_row"
    And I should see "Ready for release" in the "student student1" "table_row"
    And I should not see "Released" in the "student student1" "table_row"

    And I follow "Release the marks"
    And I should not see "Ready for release" in the "student student1" "table_row"
    And I should see "Released" in the "student student1" "table_row"

    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Admin User" in the ".coursework-feedback" "css_element"
    But I should see "teacher teacher1" in the ".coursework-feedback" "css_element"
    And I should see "50" in the ".coursework-feedback" "css_element"
