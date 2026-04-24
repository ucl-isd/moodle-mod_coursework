@mod @mod_coursework @mod_coursework_grade_decimals
Feature: For the final grade the mark should be to the decimal point

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 2          |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | manager1 | Manager   | 1        | manager1@example.com |
      | student1 | John1     | student1 | student1@example.com |
      | student2 | Jane1     | student2 | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
      | teacher2 | C1     | editingteacher |
      | manager1 | C1     | manager        |
    And the following "mod_coursework > submissions" exist:
      | allocatable | coursework | finalisedstatus |
      | student1    | Coursework | 1               |
      | student2    | Coursework | 1               |

  Scenario: A manager can enter decimals for the final grade
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "58"
    And I press "Save and finalise"

    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the field "Mark" to "56.12"
    And I press "Save and finalise"
    Then I should see "56.12" in the "[data-behat-markstage='final_agreed']" "css_element"

  Scenario: A teacher can only enter integers but not decimals for the initial grades, then manager can enter decimal for agreed grade.
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on the "Add mark" link in the table row containing "John1"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"

    Then I should see "59" in the table row containing "John1"

    When I click on the "Add mark" link in the table row containing "Jane1"
    And I set the field "Mark" to "40.5"
    And I press "Save and finalise"
    # Decimal mark is cleaned to integer.
    Then I should see "40" in the table row containing "Jane1"
    And I log out

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    # Cannot see other teacher's mark - see "Marked" instead.
    And I should not see "59" in the table row containing "John1"
    And I should see "Marked" in the table row containing "John1"
    And I click on the "Add mark" link in the table row containing "John1"
    And I set the field "Mark" to "58"
    And I press "Save and finalise"
    Then I should see "58" in the table row containing "John1"
    And I log out

    # Manager can then enter agreed grade as a decimal.
    And I am on the "Coursework" "coursework activity" page logged in as "manager1"
    And I click on the "Agree marking" link in the table row containing "John1"
    And I set the field "Mark" to "56.9"
    And I press "Save and finalise"
    Then I should see "56.9" in the table row containing "John1"
    Then I should see "Ready for release" in the table row containing "John1"
