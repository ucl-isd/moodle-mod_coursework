@mod @mod_coursework @mod_coursework_deadline_exten_reason
Feature: Deadline extension reasons dropdown list

  As an OCM admin
  I can create deadline extension reasons in a text box,
  so that the specific reason can be selected for the new cut off date.

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And the coursework individual extension option is enabled

  @javascript
  Scenario: The teacher can add a reason for the deadline extension to an individual submission
    Given the coursework deadline has passed
    And there are some extension reasons configured at site level
    And I log in as a manager
    And I visit the coursework page
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Submission extension" "link"
    And I set the following fields to these values:
      | Extended deadline | ##+1 weeks##         |
      | Extension reason  | first reason            |
      | Extra information | The dog ate my homework |
    When I click on "Save" "button"
    Then I should see "##+1 weeks##%d %B %Y##" in the "student student1" "table_row"
    Then I visit the coursework page
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Submission extension" "link"
    And I set the following fields to these values:
      | Extended deadline | ##+2 weeks##         |
      | Extension reason  | first reason            |
      | Extra information | The dog ate my homework |
    And I click on "Save" "button"
    Then I should see "##+2 weeks##%d %B %Y##" in the "student student1" "table_row"

  @javascript
  Scenario: The teacher can edit a deadline extension and its reason to an individual submission
    Given the coursework deadline has passed
    And there are some extension reasons configured at site level
    And there is an extension for the student which has expired
    And I log in as a manager
    And I visit the coursework page
    And I click on "Actions" "button" in the "student student1" "table_row"
    And I click on "Submission extension" "link"
    And I set the following fields to these values:
      | Extended deadline | ##+4 weeks##         |
      | Extension reason  | first reason            |
      | Extra information | The dog ate my homework |
    And I click on "Save" "button"
    Then I should see "##+4 weeks##%d %B %Y##" in the "student student1" "table_row"
