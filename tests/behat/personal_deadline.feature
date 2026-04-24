@mod @mod_coursework @mod_coursework_personal_deadline
Feature: When "Use the personal deadline" is enabled the deadline date should reflect any personal deadlines

  As a manager I can add personal deadlines

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity                | coursework    |
      | course                  | C1            |
      | name                    | Coursework    |
      | deadline                | ##+1 week##   |
      | personaldeadlineenabled | 1             |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | John1     | student1 | student1@example.com |
      | student2 | John2     | student2 | student2@example.com |
      | teacher1 | John2     | teacher1 | teacher1@example.com |
      | manager1 | John2     | manager1 | manager1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | teacher1 | C1     | teacher |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | manager1 | C1     | manager |

  Scenario: Teacher, and Students (with and without personal deadlines) should see appropriate
  information
    Given the following "mod_coursework > personaldeadlines" exist:
      | allocatable | coursework | deadline     |
      | student1    | Coursework | ##+2 weeks## |
    And I am on the "Coursework" "coursework activity" page logged in as "student1"
    Then I should see "##+2 weeks##%d %B %Y##" in the ".behat-duedate" "css_element"
    But I should not see "This is the default deadline that will be used if personal deadline was not specified"

    Given I am on the "Coursework" "coursework activity" page logged in as "student2"
    Then I should see "##+1 week##%d %B %Y##" in the ".behat-duedate" "css_element"
    But I should not see "This is the default deadline that will be used if personal deadline was not specified"

    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    Then I should see "##+2 weeks##%d %B %Y##" in the "John1" "table_row"
    And I should see "Personal deadline" in the "John1" "table_row"
    But I should not see "Personal deadline" in the "John2" "table_row"
    And I should see "This is the default deadline that will be used if personal deadline was not specified"

  @javascript
  Scenario: The teacher can add a personal deadline to an individual user
    Given I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Actions" "button" in the "John1" "table_row"
    And I wait until the page is ready
    And I wait "1" seconds
    And I click on "Personal deadline" "button"
    And I wait until the page is ready
    And I should see "New personal deadline for John1 student1"
    And I set the field "Personal deadline" to "##+2 weeks, 8:00 AM##"
    And I wait "2" seconds
    And I click on "Save" "button" in the "Personal deadline" "dialogue"
    And I wait until the page is ready
    And I should see "Personal deadline" in the table row containing "John1"
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the table row containing "John1"
    # Check still appears on page re-load
    Then I am on the "Coursework" "coursework activity" page
    And I should see "Personal deadline" in the table row containing "John1"
    And I should see "##+2 weeks##%d %B %Y, 8:00 AM##" in the table row containing "John1"
