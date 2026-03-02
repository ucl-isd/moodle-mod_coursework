@mod @mod_coursework @mod_coursework_feedback_zero_grades
Feature: Zero grades should show up just like the others

    As a teacher
    I want to be abel to award a grade of zero
    So that in case there is no work submitted or the work is truly and irredeemably useless,
    the student will know

  Background:
    Given the following "course" exists:
      | fullname          | Course 1  |
      | shortname         | C1        |
    And the following "activity" exists:
      | activity | coursework |
      | course   | C1         |
      | name     | Coursework |
      | numberofmarkers   | 1          |
    And there is a student
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |

  Scenario: Single maker final feedback
    Given the coursework "grade" setting is "9" in the database
    Given I am logged in as a teacher
    When I am on the "Coursework" "coursework activity" page
    And I click on the add feedback button
    And I set the field "Mark" to "0"
    And I press "Save and finalise"
    Then I am on the "Coursework" "coursework activity" page
    And I wait until the page is ready
    And I should see the final grade as 0
