@mod @mod_coursework @mod_coursework_automatic_agreement_no_straddling
Feature: Automatic agreement for grades not straddling grade class boundaries

  As a user with add/edit coursework capability
  I can add an automatic agreement for double marking when both simple grades are adjacent within a specified range,
  and do not straddle grade class boundaries,
  so that the grades are averaged to produce an agreed grade

  Background:
    Given the following "course" exists:
      | fullname  | Course 1 |
      | shortname | C1       |
    And the following "activity" exists:
      | activity        | coursework |
      | course          | C1         |
      | name            | Coursework |
      | numberofmarkers | 2          |
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
    And I log in as "admin"
    And I navigate to "Plugins > Activity modules > Coursework" in site administration
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.99
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I log out

  Scenario: An administrator can control the configuration of the agreed grade class bands - non numeric input and non-contiguous ranges are rejected
    Given I am logged in as "admin"
    And I navigate to "Plugins > Activity modules > Coursework" in site administration
    And I set the field "Average grade (no straddling class boundaries)" to "0|100"
    And I press "Save changes"
    And I should see "At least two grade classes must be defined. Please add more lines to the setting."
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    50|100
    0|49.99
    """
    And I press "Save changes"
    And I should see "Changes saved"
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    50A|100
    0|49.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    # Example values provided are accepted.
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.99
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Changes saved"
    # Non-contiguous ranges rejected
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.98
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value on line 2 - the second value on this line (69.98) must be exactly 0.01 lower than the previous line's second value (70) (i.e. value gaps or overlaps between lines are not allowed)"
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|70
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value on line 2 - the second value on this line (70) must be exactly 0.01 lower than the previous line's second value (70) (i.e. value gaps or overlaps between lines are not allowed)"
    # 3 decimal place values rejected
    And I set the field "Average grade (no straddling class boundaries)" to multiline:
    """
    70.00|100.00
    60.00|69.999
    50.00|59.99
    40.00|49.99
    1.00|39.99
    0.00|0.99
    """
    And I press "Save changes"
    And I should see "Some settings were not changed due to an error."
    And I should see "Invalid value '69.999' on line 2 - each line must have two whole numbers or decimals (with a maximum of two decimal places), separated by a | character"

  Scenario: Simple grades within 10% of eachother and both in the same grade class, agreed grade is averaged.
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "63"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    # Agreed grade has been auto averaged.
    Then I should see "65" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "Automatically agreed" in the table row containing "student student1"

  Scenario: Simple grades within 10% of eachother and both in the same grade class, agreed grade is averaged.
    # (Similar to previous test but a different band)
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "50"
    And I press "Save and finalise"
    And I log out

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "57"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    # Agreed grade has been auto averaged.
    Then I should see "53.5" in the "[data-behat-markstage='final_agreed']" "css_element"
    And I should see "Automatically agreed" in the table row containing "student student1"

  Scenario: Simple grades within 10% of eachother, but in different grade classes, no auto agreed grade appears.
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "10" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "73"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Automatically agreed" in the table row containing "student student1"
    And I should not see "70" in the table row containing "student student1"

  Scenario: Simple grades *NOT* within 5% of eachother where they should be, but both in the same grade class, agreed grade does not appear
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "5" in the database
    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "68"
    And I press "Save and finalise"
    And I log out

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "60"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "64" in the table row containing "student student1"
    And I should not see "Automatically agreed" in the table row containing "student student1"

  Scenario: Simple grades are *NOT* within 5% of eachother, and in different grade classes, no auto agreed grade appears.
    Given the coursework "automaticagreementstrategy" setting is "average_grade_no_straddle" in the database
    And the coursework "automaticagreementrange" setting is "5" in the database

    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out


    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "73"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Automatically agreed" in the table row containing "student student1"
    And I should not see "70" in the table row containing "student student1"

  Scenario: Simple grades within 10% of eachother and both in the same grade class, but setting is "none" - agreed grade does not appear.
    Given the coursework "automaticagreementstrategy" setting is "none" in the database

    And I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "67"
    And I press "Save and finalise"
    And I log out


    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "63"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Automatically agreed" in the table row containing "student student1"
    And I should not see "65" in the table row containing "student student1"
