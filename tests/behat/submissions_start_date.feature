@mod @mod_coursework
Feature: Start date

  As a teacher
  I want to be able to restrict the start date of the coursework
  So that students will not begin to work on it until the right time

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity  | coursework |
      | course    | C1         |
      | name      | Coursework |
      | startdate | 0          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  Scenario: The student can submit when the start date is disabled
    Given I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Upload your submission"

    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "startdate" to "##tomorrow##"
    And I press "Save and display"

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should not see "Upload your submission"

    Given I am on the "Coursework" "coursework activity" page logged in as "admin"
    And I navigate to "Settings" in current page administration
    And I set the field "startdate" to "##yesterday##"
    And I press "Save and display"

    When I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "Upload your submission"
