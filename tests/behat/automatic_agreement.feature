@mod @mod_coursework @mod_coursework_automatic_agreement
Feature: General automatic agreement feature.

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

  Scenario: Automatically agreed grade should not be released until it is finalised
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    When I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "60"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "20"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    Then I should see "40" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should not see "Ready for release" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "40" "link" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I press "Save and finalise"
    And I should see "Ready for release" in the "[data-behat-markstage='final_agreed']" "css_element"
