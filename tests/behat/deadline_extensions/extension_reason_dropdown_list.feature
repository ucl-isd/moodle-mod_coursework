@mod @mod_coursework @RVC_PT_83738596
Feature: Deadline extension reasons dropdown list

  As an OCM admin
  I can create deadline extension reasons in a text box,
  so that the specific reason can be selected for the new cut off date.

  Background:
    Given there is a course
    And there is a coursework
    And there is a student
    And the coursework individual extension option is enabled

  Scenario: The teacher can add a reason for the deadline extension to an individual submission
    Given the coursework deadline has passed
    And there are some extension reasons configured at site level
    And I log in as a manager
    And I am on the coursework page
    When I add a new extension for the student
    Then I should be on the coursework page
    When I click on the edit extension icon for the student
    Then I should see the deadline reason in the deadline extension form
    And I should see the extra information in the deadline extension form

  Scenario: The teacher can edit a deadline extension and its reason to an individual submission
    Given the coursework deadline has passed
    And there are some extension reasons configured at site level
    And there is an extension for the student which has expired
    And I log in as a manager
    And I am on the coursework page
    When I edit the extension for the student
    Then I should be on the coursework page
    And I should see the new extended deadline in the student row
    When I click on the edit extension icon for the student
    Then I should see the new deadline reason in the dropdown
    And I should see the new extra deadline information in the deadline extension form

