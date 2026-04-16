@mod @mod_coursework @mod_coursework_deadline_exten_reason
Feature: Deadline extension reasons dropdown list

  As an OCM admin
  I can create deadline extension reasons in a text box,
  so that the specific reason can be selected for the new cut off date.

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity          | coursework    |
      | course            | C1            |
      | name              | Coursework    |
      | deadline          | ##yesterday## |
      | extensionsenabled | 1             |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | student   | student1 | student1@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student |
      | manager1 | C1     | manager        |
    And the following config values are set as admin:
      | config                            | value                       |
      | coursework_extension_reasons_list | first reason\nsecond reason |

  @javascript
  Scenario: The teacher can add a reason for the deadline extension to an individual submission
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Submission extension" "button"
    And I set the following fields to these values:
      | Extended deadline | ##+1 weeks##            |
      | Extension reason  | first reason            |
      | Extra information | The dog ate my homework |
    When I click on "Save" "button"
    Then I should see "##+1 weeks##%d %B %Y##" in the "student student1" "table_row"
    Then I am on the "Coursework" "coursework activity" page
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Submission extension" "button"
    And I set the following fields to these values:
      | Extended deadline | ##+2 weeks##            |
      | Extension reason  | first reason            |
      | Extra information | The dog ate my homework |
    And I click on "Save" "button"
    Then I should see "##+2 weeks##%d %B %Y##" in the "student student1" "table_row"

  @javascript
  Scenario: The teacher can edit a deadline extension and its reason to an individual submission
    Given the following "mod_coursework > deadline_extensions" exist:
      | allocatable | coursework | deadline    |
      | student1    | Coursework | ##-1 week## |
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Submission extension" "button"
    And I set the following fields to these values:
      | Extended deadline | ##+4 weeks##            |
      | Extension reason  | first reason            |
      | Extra information | The dog ate my homework |
    And I click on "Save" "button"
    Then I should see "##+4 weeks##%d %B %Y##" in the "student student1" "table_row"
