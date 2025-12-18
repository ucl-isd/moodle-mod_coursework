@mod @mod_coursework @mod_coursework_automatic_agreement_release_marks
Feature: Automatic agreement for simple grades with marks in draft / released

    As a marker, when I add all marks to a submission and the auto agreed grade is calculated, initially it is a draft mark.
    As a manager, when I click release marks, any auto graded marks still in draft are changed to finalised as part of the release process.

  Background:
    Given there is a course
    And there is a coursework
    And the coursework "numberofmarkers" setting is "2" in the database
    And there is a student
    And there is a teacher
    And there is another teacher
    And the student has a submission
    And the submission is finalised
    And the coursework deadline has passed

  Scenario: Simple grades within 10% boundaries takes higher mark as a final "draft" grade
    Given the coursework "automaticagreementstrategy" setting is "percentage_distance" in the database
    Given the coursework "automaticagreementrange" setting is "10" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    When I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    When I set the field "Mark" to "63"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should see the final agreed grade as 67
    And I should see "Draft" in the "student1" "table_row"
    # Grade is ready for release even though it's in draft, as it's an auto grade
    And I should see "Ready for release" in the "student1" "table_row"
    And I log out

    And I log in as a manager
    And I visit the coursework page
    Then I should see the final agreed grade as 67
    And I should see the final agreed grade status "Draft"
    And I follow "Release the marks"
    Then I should see the final agreed grade as 67
    And I should see "Released" in the "student1" "table_row"
    And I should not see "Draft" in the "student1" "table_row"

  Scenario: Simple grades within 10% boundaries takes higher mark as a final "draft" grade, manager can finalise grade.
    Given the coursework "automaticagreementstrategy" setting is "percentage_distance" in the database
    Given the coursework "automaticagreementrange" setting is "10" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    When I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    When I set the field "Mark" to "63"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should see the final agreed grade as 67
    And I should see "Draft" in the "student1" "table_row"
    And I should see "Ready for release" in the "student1" "table_row"
    And I log out

    And I log in as a manager
    And I visit the coursework page
    Then I should see the final agreed grade as 67
    And I should see the final agreed grade status "Draft"
    And I click on "67" "link" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I click on "Save and finalise" "button"

    Then I should see the final agreed grade as 67
    # I clicked Save and finalise so it's no longer a draft.
    And I should not see "Draft" in the "student1" "table_row"
    And I should see "Ready for release" in the "student1" "table_row"
    And I should not see "Released" in the "student1" "table_row"
    And I follow "Release the marks"
    Then I should see the final agreed grade as 67
    And I should see "Released" in the "student1" "table_row"
    And I should not see "Draft" in the "student1" "table_row"
