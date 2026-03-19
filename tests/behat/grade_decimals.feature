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
      | student1 | John1     | student1 | student1@example.com |
      | student1 | Jane1     | student1 | student1@example.com |
    And the student called "John1" has a finalised submission
    And the student called "Jane1" has a finalised submission

  Scenario: Automatic agreement of grades = "average grade" should use decimal places
    Given the coursework "automaticagreementstrategy" setting is "average_grade" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I am on the "Coursework" "coursework activity" page
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "58"
    And I press "Save and finalise"
    Then I should see "58.5" in the "[data-behat-markstage='final_agreed']" "css_element"

  Scenario: A manager can enter decimals for the final grade
    GivenI am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I am on the "Coursework" "coursework activity" page
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "58"
    And I press "Save and finalise"
    And I log out
    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click on "Agree marking" "link" in the "student1" "table_row"
    And I set the following fields to these values:
      | Mark    | 56.12   |
    Then I should see "56.12" in the "[data-behat-markstage='final_agreed']" "css_element"

  Scenario: A teacher can only enter integers but not decimals for the initial grades, then manager can enter decimal for agreed grade.
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on the "Add mark" link in the table row containing "John1"
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    Then I should see "59" in the table row containing "John1"
    And I click on the "Add mark" link in the table row containing "Jane1"
    And I set the field "Mark" to "40.5"
    And I press "Save and finalise"
    # Decimal mark is cleaned to integer.
    Then I should see "40" in the table row containing "Jane1"
    And I log out

    And I log in as the other teacher
    And I am on the "Coursework" "coursework activity" page
    # Cannot see other teacher's mark - see "Marked" instead.
    And I should not see "59" in the table row containing "John1"
    And I should see "Marked" in the table row containing "John1"
    And I click on the "Add mark" link in the table row containing "John1"
    And I set the field "Mark" to "58"
    And I press "Save and finalise"
    Then I should see "58" in the table row containing "John1"
    And I log out

    # Manager can then enter agreed grade as a decimal.
    And I log in as a manager
    And I am on the "Coursework" "coursework activity" page
    And I click on the "Agree marking" link in the table row containing "John1"
    And I set the field "Mark" to "56.9"
    And I press "Save and finalise"
    Then I should see "56.9" in the table row containing "John1"
    Then I should see "Ready for release" in the table row containing "John1"
