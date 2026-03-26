@mod @mod_coursework @mod_coursework_feedback_rubrics
Feature: Adding feedback using the built in Moodle rubrics

  As a teacher
  I want to be able to give detailed feedback about specific parts of the students work
  in a standardised way
  So that I can grade the work faster, give more consistent responses and make the process more fair

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
      | manager1 | manager   | manager1 | manager1@example.com |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
      | manager1 | C1     | manager |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
    And I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I select "Advanced grading" from secondary navigation
    And I set the field "Change active grading method to" to "Rubric"
    And I follow "Define new grading form from scratch"
    And I set the following fields to these values:
      | Name        | Test rubric        |
      | Description | Rubric description |
    And I define the following rubric:
      | first criterion | Bad | 1 | Ok | 2 | Good | 3 |
    And I press "Save rubric and make it ready"

  @javascript
  Scenario: I should be able to grade a submission using a rubric and have the grade show up in the gradebook
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    When I click on "Add mark" "link" in the "student1" "table_row"
    And I grade by filling the rubric with:
      | first criterion | 3 | Criterion comment |
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I follow "Release the marks"
    And I press "Confirm"
    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "100" in the ".coursework-feedback" "css_element"
    And I should see "Criterion comment" in the ".coursework-feedback" "css_element"
    Then I should see "Good" in the ".coursework-feedback" "css_element"

    When I am on the "Course 1" "grades > User report > View" page
    Then I should see "100.00"
