@mod @mod_coursework @RVC_PT_83106618
Feature: Deadlines extensions for submissions

  As an OCM admin
  I can add allow students to submit after the deadline
  So that late work can still be given a grade

  Background:
    Given there is a course
    And there is a coursework
    And the coursework individual extension option is enabled
    And there is a student

  Scenario: The student can submit after the deadline when the start date is disabled
    Given the coursework deadline has passed
    And there is an extension for the student that allows them to submit
    When I log in as a student
    And I visit the coursework page
    Then I should see the new submission button

  Scenario: The student can not submit when the start date is in the future
    Given the coursework deadline has passed
    And there is an extension for the student which has expired
    When I log in as a student
    And I visit the coursework page
    Then I should not see the new submission button

  Scenario: The teacher can add a deadline extension to an individual submission
    Given the coursework deadline has passed
    And I log in as a manager
    And I am on the coursework page
    When I add a new extension for the student
    Then I should be on the coursework page
    And I should see the extended deadline in the student row

  Scenario: The teacher can edit a deadline extension to an individual submission
    Given the coursework deadline has passed
    And there is an extension for the student which has expired
    And I log in as a manager
    And I am on the coursework page
    When I edit the extension for the student
    Then I should be on the coursework page
    And I should see the extended deadline in the student row
