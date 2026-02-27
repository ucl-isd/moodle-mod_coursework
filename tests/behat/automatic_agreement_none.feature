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
      | activity                   | coursework    |
      | course                     | C1            |
      | name                       | Coursework    |
      | numberofmarkers            | 2             |
      | automaticagreementstrategy | none          |
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

  Scenario: Only one grade in the submissions
    When I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    When I set the field "Mark" to "67"
    And I press "Save and finalise"
    Then "[data-behat-markstage='final_agreed']" "css_element" should not exist
    And I should not see "Automatically agreed" in the table row containing "student student1"

    When I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "63"
    And I press "Save and finalise"
    Then "[data-behat-markstage='final_agreed']" "css_element" should not exist
    And I should not see "Automatically agreed" in the table row containing "student student1"

    When I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I follow "Agree marking"
    Then I should see "67" in the "[data-behat-markstage='assessor_1']" "css_element"
    Then I should see "63" in the "[data-behat-markstage='assessor_2']" "css_element"

    When I set the field "Mark" to "70"
    And I press "Save and finalise"
    Then I should see "70" in the "[data-behat-markstage='final_agreed']" "css_element"
