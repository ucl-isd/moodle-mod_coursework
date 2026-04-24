@mod @mod_coursework @mod_coursework_automatic_agreement
Feature: Automatic agreement on average for simple grades where percentage distance is in range.

  As a user with add/edit coursework capability
  I can add an automatic agreement for double marking when both simple grades are adjacent within a specified range,
  so that the average grade is chosen for all cases apart from the fail grades.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                   | coursework    |
      | course                     | C1            |
      | name                       | Coursework    |
      | numberofmarkers            | 2             |
      | automaticagreementstrategy | average_grade |
      | deadline                   | ##yesterday## |
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

  Scenario: Automatic agreement of grades = "average grade" should use decimal places
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "58"
    And I press "Save and finalise"

    When I am on the "Coursework" "coursework activity" page
    Then I should see "58.5" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "Draft" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "Ready for release" in the "[data-behat-markstage='final_agreed']" "css_element"
