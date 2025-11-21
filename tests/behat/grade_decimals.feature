@mod @mod_coursework @mod_coursework_grade_decimals
Feature: For the final grade the mark should be to the decimal point

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a teacher
    And there is another teacher
    And there is a student called "John1"
    And there is a student called "Jane1"
    And the student called "John1" has a finalised submission
    And the student called "Jane1" has a finalised submission

  Scenario: Automatic agreement of grades = "average grade" should use decimal places
    Given the coursework "automaticagreementstrategy" setting is "average_grade" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "58"
    And I press "Save and finalise"
    Then I should see the final agreed grade as 58.5

  Scenario: A manager can enter decimals for the final grade
    Given I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "59"
    And I press "Save and finalise"
    And I log out
    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "58"
    And I press "Save and finalise"
    And I log out
    And I log in as a manager
    And I visit the coursework page
    And I click the new multiple final feedback button for the student
    And I grade the submission as 56.12 using the grading form
    Then I should see the final agreed grade as 56.12

  Scenario: A teacher can only enter integers but not decimals for the initial grades, then manager can enter decimal for agreed grade.
    Given I am logged in as a teacher
    And I visit the coursework page
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
    And I visit the coursework page
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
    And I visit the coursework page
    And I click on the "Agree marking" link in the table row containing "John1"
    And I set the field "Mark" to "56.9"
    And I press "Save and finalise"
    Then I should see "56.9" in the table row containing "John1"
    Then I should see "Ready for release" in the table row containing "John1"
