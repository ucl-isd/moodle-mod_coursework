@mod @mod_coursework @mod_coursework_automatic_agreement_no_straddling
Feature: Automatic agreement for grades not straddling grade class boundaries

  As a user with add/edit coursework capability
  I can add an automatic agreement for double marking when both simple grades are adjacent within a specified range,
  and do not straddle grade class boundaries,
  so that the grades are averaged to produce an agreed grade

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
    And the admin setting for auto grade class boundaries is set using the example

  Scenario: An administrator can control the configuration of the agreed grade class bands - non numeric input and non-contiguous ranges are rejected
    Given I am logged in as "admin"
    And I navigate to "Plugins > Activity modules > Coursework" in site administration
    And I set the field "Average grade (no straddling class boundaries)" to "0|100"
    And I press "Save changes"
    And I should see "At least two grade classes must be defined. Please add more lines to the setting."
    And I set the field "Average grade (no straddling class boundaries)" to "50|100\n0|49.99" replacing line breaks
    And I press "Save changes"
    And I should see "Changes saved"
    And I set the field "Average grade (no straddling class boundaries)" to "50A|100\n0|49.99" replacing line breaks
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    # Example values provided are accepted.
    And I set the field "Average grade (no straddling class boundaries)" to "70.00|100.00\n60.00|69.99\n50.00|59.99\n40.00|49.99\n1.00|39.99\n0.00|0.99" replacing line breaks
    And I press "Save changes"
    And I should see "Changes saved"
    # Non-contiguous ranges rejected
    And I set the field "Average grade (no straddling class boundaries)" to "70.00|100.00\n60.00|69.98\n50.00|59.99\n40.00|49.99\n1.00|39.99\n0.00|0.99" replacing line breaks
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value on line 2 - the second value on this line (69.98) must be exactly 0.01 lower than the previous line's second value (70) (i.e. value gaps or overlaps between lines are not allowed)"
    And I set the field "Average grade (no straddling class boundaries)" to "70.00|100.00\n60.00|70\n50.00|59.99\n40.00|49.99\n1.00|39.99\n0.00|0.99" replacing line breaks
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value on line 2 - the second value on this line (70) must be exactly 0.01 lower than the previous line's second value (70) (i.e. value gaps or overlaps between lines are not allowed)"
    # 3 decimal place values rejected
    And I set the field "Average grade (no straddling class boundaries)" to "70.00|100.00\n60.00|69.999\n50.00|59.99\n40.00|49.99\n1.00|39.99\n0.00|0.99" replacing line breaks
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value '69.999' on line 2 - each line must have two whole numbers or decimals (with a maximum of two decimal places), separated by a | character"

  Scenario: Only one grade in the submissions - agreed grade does not appear
    And the coursework "automaticagreementstrategy" setting is "none" in the database
    Given I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "56"
    And I press "Save and finalise"
    Then I should not see the final grade on the student page

  Scenario: Simple grades within 10% of eachother and both in the same grade class, agreed grade is averaged.
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "63"
    And I press "Save and finalise"
    And I visit the coursework page
    # Agreed grade has been auto averaged.
    Then I should see the final agreed grade as 65
    And I should see "Automatically agreed" in the table row containing "student student1"

  Scenario: Simple grades within 10% of eachother and both in the same grade class, agreed grade is averaged.
    # (Similar to previous test but a different band)
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "50"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "57"
    And I press "Save and finalise"
    And I visit the coursework page
    # Agreed grade has been auto averaged.
    Then I should see the final agreed grade as 53.5
    And I should see "Automatically agreed" in the table row containing "student student1"

  Scenario: Simple grades within 10% of eachother, but in different grade classes, no auto agreed grade appears.
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "73"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should not see "Automatically agreed" in the table row containing "student student1"
    And I should not see "70" in the table row containing "student student1"

  Scenario: Simple grades *NOT* within 5% of eachother where they should be, but both in the same grade class, agreed grade does not appear
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "5" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "68"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "60"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should not see "64" in the table row containing "student student1"
    And I should not see "Automatically agreed" in the table row containing "student student1"

  Scenario: Simple grades are *NOT* within 5% of eachother, and in different grade classes, no auto agreed grade appears.
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "5" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "73"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should not see "Automatically agreed" in the table row containing "student student1"
    And I should not see "70" in the table row containing "student student1"

  Scenario: Simple grades within 10% of eachother and both in the same grade class, but setting is "none" - agreed grade does not appear.
    Given the coursework "automaticagreementstrategy" setting is "none" in the database
    And I am logged in as a teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 1
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I log in as the other teacher
    And I visit the coursework page
    And I click on the add feedback button for assessor 2
    And I set the field "Mark" to "63"
    And I press "Save and finalise"
    And I visit the coursework page
    Then I should not see "Automatically agreed" in the table row containing "student student1"
    And I should not see "65" in the table row containing "student student1"
