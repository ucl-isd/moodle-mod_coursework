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
      | activity                   | coursework                |
      | course                     | C1                        |
      | name                       | Coursework                |
      | numberofmarkers            | 2                         |
      | deadline                   | ##yesterday##             |
      | automaticagreementstrategy | average_grade_no_straddle |
      | automaticagreementrange    | 10                        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | teacher   | teacher1 | teacher1@example.com |
      | teacher2 | teacher   | teacher2 | teacher2@example.com |
      | student1 | student   | student1 | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role    |
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

  Scenario: Simple grades within 10% of each other and both in the same grade class, agreed grade is averaged.
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
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

  Scenario: Simple grades within 10% of each other, but in different grade classes, no auto agreed grade appears.
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
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

  Scenario: Simple grades *NOT* within 10% of each other where they should be, but both in the same grade class, agreed grade does not appear
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "10"
    And I press "Save and finalise"
    And I log out

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "21"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "64" in the table row containing "student student1"
    And I should not see "Automatically agreed" in the table row containing "student student1"

  Scenario: Simple grades are *NOT* within 5% of each other, and in different grade classes, no auto agreed grade appears.
    Given I am on the "Coursework" "coursework activity" page logged in as "teacher1"
    And I click on "Add mark" "link" in the "[data-behat-markstage='1']" "css_element"
    And I set the field "Mark" to "10"
    And I press "Save and finalise"
    And I log out

    And I am on the "Coursework" "coursework activity" page logged in as "teacher2"
    And I click on "Add mark" "link" in the "[data-behat-markstage='2']" "css_element"
    And I set the field "Mark" to "73"
    And I press "Save and finalise"
    And I am on the "Coursework" "coursework activity" page
    Then I should not see "Automatically agreed" in the table row containing "student student1"
    And I should not see "70" in the table row containing "student student1"
